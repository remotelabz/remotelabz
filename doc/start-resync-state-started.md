# Resync « start » au handshake worker : devices bloqués en `starting`

> **Statut** : correctifs **worker et front implémentés** (§4, §5), à l'exception
> de `InstanceManager::start()` (§5.4, reporté).
>
> **Déployé** : front (PHP ≥ 8.5) ; worker sur `dev` (la version 2.5) et la
> branche `Get-IP-From-Worker` (PHP ≥ 8.2).
> **Note branches worker** : `master` ne porte aucun des correctifs. Les
> références de lignes ci-dessous sont celles du checkout local
> `Get-IP-From-Worker`.

## 1. Objectif du resync

Après une mise à jour du front ou un redémarrage d'un worker, il faut
**réaligner la base de données et le réel** : les device instances en état
`starting` ou `started` placées sur le worker reçoivent un
`InstanceActionMessage` (`ACTION_START`).

Comportement attendu du worker : si le device est déjà démarré, **ne pas le
redémarrer** (perte de données possible) et répondre immédiatement
`InstanceStateMessage` `STATE_STARTED` avec
`options = ["state" => "already_running"]`.

Le mécanisme complet, tel qu'implémenté :

```
worker (démarrage de messenger:consume worker)
   │
   ▼  WorkerStartedEventSubscriber → WorkerHandshakeMessage (id = IP du worker)
front : WorkerHandshakeMessageHandler
   │  pour chaque DeviceInstance starting/started placée sur ce worker :
   │    1. état précédent enregistré
   │    2. setState(STARTING) + flush
   │    3. sérialisation LabInstance (groupe 'worker')
   │    4. dispatch ACTION_START (routing key = IP du worker)
   │    5. si 2-4 lèvent : rollback de l'état précédent (try/catch par device)
   ▼
worker : InstanceActionMessageHandler → startDeviceInstance / startRealDeviceInstance
   │  garde « déjà démarré » → réponse STATE_STARTED + already_running
   │  démarrage réel sinon
   ▼
front : InstanceStateMessageHandler → setState + notification
```

En plus du resync, un **watchdog front** rattrape tout device resté bloqué en
`starting` (perte de message, worker tombé, …) : §5.2.

## 2. Chaîne de messages

```
front                                    worker
─────                                    ──────
WorkerHandshakeMessageHandler
  findAllStartingOrStarted()
  setState(STARTING) + flush             ← état réécrit AVANT sérialisation
  serialize(LabInstance, groupe 'worker') ← descripteur porte donc state: "starting"
  dispatch ACTION_START
         ───────────────────────────────▶ InstanceActionMessageHandler::__invoke
                                           ACTION_START
                                             startDeviceInstance / startRealDeviceInstance
                                           dispatch InstanceStateMessage (STATE_STARTED / STATE_ERROR)
         ◀───────────────────────────────
InstanceStateMessageHandler
  setState($message->getState())
```

Le descripteur porte `state: "starting"` (l'état est réécrit avant
sérialisation, `WorkerHandshakeMessageHandler.php:69-70`) : le worker tente
donc un démarrage complet, que ses gardes « déjà running » convertissent en
réponse `started` sans redémarrage.

## 3. Le défaut d'origine (analyse confirmée)

Avant les correctifs, le chemin « device déjà démarré » ne produisait **aucune
réponse**, et le device restait bloqué en `starting` définitivement.

### 3.1 Garde « already started » : `return;` nu → `TypeError` au dispatch

`InstanceManager::startRealDeviceInstance()` / `startDeviceInstance()`
(worker) : le filtre `state != 'started'` renvoyait un tableau vide, et la
garde se terminait par un `return;` (avec en prime un log référençant
`$deviceInstance['uuid']` sur un tableau vide). Dans
`InstanceActionMessageHandler`, `$ReturnArray["state"]` valait alors `null` →
`new InstanceStateMessage(…)` (paramètres `string` non nullables) →
**`TypeError`**, un `Error` hors des `catch (Exception)` → aucun
`InstanceStateMessage expédié`. Messenger rejouait le message 3 fois (même
`TypeError`), puis le message était perdu → device bloqué en `starting`.

### 3.2 `array_key_exists("state", null)` sur un démarrage QEMU frais

Dans `startDeviceInstance()`, après `create_qemu_device()` :

```php
if (!array_key_exists("state", $result["options"])) {  // options === null ⇒ TypeError (PHP ≥ 8.1)
```

`create_qemu_device()` renvoie `"options" => null` sur un démarrage frais
réussi → `TypeError` → pas de réponse pour cette tentative. Le retry trouvait
la VM déjà active et répondait `started` (symptôme : démarrage « gelé » puis
OK) — ou, si les retries échouaient aussi, le message était perdu.

### 3.3 `catch (Exception)` insuffisant + `failure_transport` désactivé

Aucun `catch (\Throwable)` dans le handler, et
`# failure_transport: failed` commenté : après 3 retries, le message était
rejeté **sans trace exploitable**.

### 3.4 Côté front

- **Aucun watchdog** : tout device resté en `starting` y restait à jamais.
- **`InstanceStateMessageHandler`** : même `array_key_exists("state", null)`
  sur `STATE_STARTED` avec `options = null` (démarrage frais) — l'état était
  déjà appliqué, mais la notification « started successfully » n'était jamais
  envoyée et le message rejoué puis perdu.
- **Handshake** : si `serialize`/`dispatch` levait, le device restait en
  `starting` **sans message envoyé** (pas de rollback).
- **`InstanceManager::start()`** : pour un device `creating|starting|started|resetting`,
  le log « already running » était posé mais le `throw` commenté et le dispatch
  dans le `else` → aucun message envoyé (un device bloqué ne pouvait être
  relancé qu'après un stop).
- **Garde du handshake sur l'IP** : `if ($workerIp == $message->getId())`
  (`WorkerHandshakeMessageHandler.php:67`) — fonctionne car les workers
  `dev` (la version 2.5) envoie `WORKER_IP` ; sur `master` l'envoi vaut `'id'`
  et le resync ne se déclencherait jamais. **Ne pas régresser.**

## 4. Correctifs worker — implémentés

Branche de référence : `dev` (et `Get-IP-From-Worker`, checkout local).

### 4.1 Garde « already started » = réponse explicite ✅

`InstanceManager::startRealDeviceInstance()` (`:263-275`) et
`startDeviceInstance()` (`:483-495`) : le `return;` nu est remplacé par :

```php
return [
    "state"   => InstanceStateMessage::STATE_STARTED,
    "uuid"    => $uuid,
    "options" => ["state" => "already_running"],
];
```

(mêmes options que le chemin `qemu_start` déjà running : le front sait que
c'est un déjà-lancé et n'affiche pas « started successfully »). Le log
`'instance' => $uuid` est corrigé (plus de tableau vide).

Les raccourcis « déjà running » existants restent en place :
`qemu_start` (`systemctl is-active <scope>`, `:1273-1285`), `lxc_start`
(`lxc_is_running`, `:1671-1701`), switch natif.

### 4.2 Vérification null-safe ✅

`startDeviceInstance()` (`:531-533`) :

```php
if (!isset($result["options"]["state"])) {
```

au lieu de `array_key_exists("state", $result["options"])`.

### 4.3 Toujours répondre ✅

`InstanceActionMessageHandler::__invoke` :

- **`catch (\Throwable $e)`** en plus de `ProcessFailedException` /
  `Exception` (`:159`, `:171`, `:183`) : tout `Error`/`TypeError` positionne
  `STATE_ERROR` ;
- **normalisation défensive avant le dispatch** (`:208-212`) :

  ```php
  $returnState   = $returnState ?: ($ReturnArray["state"] ?? InstanceStateMessage::STATE_ERROR);
  $returnUuid    = $ReturnArray["uuid"]    ?? $message->getUuid();
  $returnOptions = $ReturnArray["options"] ?? [];
  ```

  (`options` par défaut `[]` et non `null` : un front non corrigé ne tombe pas
  sur `array_key_exists("state", null)`).

  Un handler `ACTION_*` ne se termine **jamais** sans envoyer
  d'`InstanceStateMessage` — sauf `CONNECT`/`DISCONNECT`, qui ne renvoient
  volontairement aucun état (`ACTIONS_WITHOUT_STATE`) : les échecs y sont
  loggés en critical.

### 4.4 Pertes visibles ✅

`config/packages/messenger.yaml` (worker) : `failure_transport: failed`
activé, transport `failed` en exchange AMQP `failed` (fanout, queue
`messages_failed`) — le worker n'ayant pas de base de données, le transport
doctrine n'est pas disponible.

## 5. Correctifs front — implémentés

### 5.1 `InstanceStateMessageHandler` null-safe ✅

`src/MessageHandler/InstanceStateMessageHandler.php:441` :
`if (!isset($options["state"]))` (mêmes intentions que l'original).

### 5.2 Watchdog de réconciliation ✅

`src/Command/ReconcileStartingInstancesCommand.php`
(`app:instance:reconcile-starting`) :

- repasse en `error` (+ notification) tout `DeviceInstance` en `starting`
  depuis plus de `--min-age` minutes (défaut **10**) ; `--dry-run` disponible ;
- reposse sur `device_instance.state_changed_at` (migration
  `Version20261013120000`, mis à jour par `DeviceInstance::setState()`) et
  `DeviceInstanceRepository::findStuckBefore()` ;
- timer systemd : `bin/systemd/remotelabz-instance-reconcile.{service,timer}`
  (toutes les 5 min).

### 5.3 Handshake : rollback d'état ✅

`src/MessageHandler/WorkerHandshakeMessageHandler.php` : l'état précédent de
chaque device est enregistré (`:69`) ; si `setState`/`serialize`/`dispatch`
lève, `catch (\Throwable)` par device restaure l'état précédent
(`:114-119`) — plus de device en `starting` sans message envoyé.

### 5.4 `InstanceManager::start()` — ⏳ reporté

`src/Service/Instance/InstanceManager.php:339-343` : pour un device
`creating|starting|started|resetting`, le log « already running » est posé, le
`throw` reste commenté et le dispatch est dans le `else` → aucun message.
À faire : soit réactiver le `throw` (409 à l'utilisateur), soit **rejouer le
message de start** (résiliation idéale d'un device bloqué). En attendant, le
watchdog (§5.2) couvre l'impasse côté UI.

### 5.5 Garde du handshake sur l'IP — à conserver

Ne pas régresser sur un worker `master` (id `'id'`) : voir §3.4.

## 6. Diagnostics

Côté worker (`journalctl -u remotelabz-worker`) :

| Message / trace | Signification |
|---|---|
| `Device instance is already started. Aborting.` | garde « déjà démarré » → réponse `STATE_STARTED` + `already_running` (chemin sain) |
| `QEMU scope already active` / `LXC container is already running` | raccourci déjà running, réponse envoyée |
| `State … send back to the front` | le handler a envoyé une réponse |
| `Action "…" throwed a throwable.` (log worker, verbatim) | `catch (\Throwable)` : `STATE_ERROR` envoyé (plus de perte silencieuse) |
| `Error thrown while handling message …` + 3 repeats | retry puis échec définitif → transport `failed` (inspectable) |

Côté front (`var/log/prod.log`) :

| Message | Signification |
|---|---|
| `Received Worker Handshake message !` | déclencheur du resync |
| `Device instance … is in state Starting or Started` + `Sending start message to …` | resync émis |
| `No error received from :… instance message state:started` | réponse appliquée |

Interprétation : device en `starting` **sans** `State … send back to the front`
après le start → réponse jamais produite (worker) ; **avec** « send back » mais
sans `No error received` → problème front/livraison.

## 7. Tests de non-régression

1. **Resync d'un device réellement démarré** (QEMU, LXC, physique) : réponse
   `STATE_STARTED` immédiate avec `options.state = already_running`, aucune
   reconfiguration, notification absente, état `started` conservé.
2. **Démarrage frais QEMU** : premier `ACTION_START` → `STATE_STARTED` avec
   `options = null`, notification « started successfully », aucun retry.
3. **Démarrage frais LXC / physique / switch natif** : idem.
4. **Device réellement mort mais `started` en base** : le worker redémarre
   réellement et répond `STATE_STARTED`.
5. **Injection d'une erreur** (couper le PDU / renommer une image) : réponse
   `STATE_ERROR`, front → `error` + notification ; jamais de device sans
   réponse.
6. **Perte de réponse simulée** (stopper le worker après réception) : le
   watchdog front ramène le device en `error` après N minutes.
7. **Redémarrage du worker** : handshake → resync → tous les devices reviennent
   en `started`.
