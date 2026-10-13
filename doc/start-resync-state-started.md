# Resync « start » après mise à jour du front : devices bloqués en `starting`

> **Statut** : analyse confirmée ; correctifs **front implémentés** (§6.1-6.3),
> correctifs **worker à implémenter** (§5), `InstanceManager::start()` (§6.4) reporté.
> **Déployé** : front (branche `dev`-like, PHP ≥ 8.5), workers `dev` / `Upgrade-2.5` (PHP ≥ 8.2).
> **Note** : le checkout local de `/home/florent/remotelabz-worker` est sur `master` (2.4.3) ;
> les numéros de lignes des sections « Worker » se réfèrent à `origin/dev` et `origin/Upgrade-2.5`
> (les lignes équivalentes sur `master` sont signalées en note).

## 1. Contexte et symptôme

Lors d'une mise à jour du front, un `InstanceActionMessage` (`ACTION_START`) est renvoyé à tous
les device instances en état `starting` ou `started` afin de réaligner la base de données et le
réel. Ce resync est déclenché par la réception d'un **`WorkerHandshakeMessage`** :

- Producteur : worker, `src/EventSubscriber/WorkerStartedEventSubscriber.php` (envoié au démarrage
  de `messenger:consume worker` ; id = `WORKER_IP` sur `dev`/`Upgrade-2.5`, littéral `'id'` sur
  `master` — le front compare l'IP, voir §4.5).
- Consommateur : front, `src/MessageHandler/WorkerHandshakeMessageHandler.php:48-122`.

Comportement attendu : le worker, voyant que le device est déjà démarré, **ne le redémarque pas**
(perte de données possible) et répond immédiatement `InstanceStateMessage` `STATE_STARTED`.

Comportement observé : **le worker ne renvoie pas toujours `STATE_STARTED`** ; les devices restent
bloqués en `starting` (aucune réconciliation côté front, voir §4.2).

> À noter : `bin/remotelabz-update.sh` ne redémarre que `remotelabz` et `remotelabz-proxy`
> (donc a priori pas de handshake pendant la mise à jour elle-même). Vérifier dans les logs front
> (`Received Worker Handshake message !`) d'où viennent les handshakes observés — handshake mis en
> file pendant que le consumer `messenger:consume front` est à l'arrêt, redémarrage d'un worker, etc.

## 2. Chaîne de messages

```
front                                    worker
─────                                    ──────
WorkerHandshakeMessageHandler
  findAllStartingOrStarted()             (DeviceInstanceRepository.php:84-99)
  setState(STARTING) + flush()           (:69-70)  ← état réécrit AVANT sérialisation
  serialize(LabInstance, groupe 'worker')(:77-79)  ← descripteur porte donc state: "starting"
  dispatch ACTION_START                  (:107-111, routing key = IP du worker)
        ───────────────────────────────▶ InstanceActionMessageHandler::__invoke
                                          ACTION_START (:58-79)
                                            startDeviceInstance / startRealDeviceInstance
                                          dispatch InstanceStateMessage (STATE_STARTED / STATE_ERROR)
        ◀───────────────────────────────
InstanceStateMessageHandler
  setState($message->getState()) aveugle (:407-409)
```

## 3. Constats — worker (`dev` / `Upgrade-2.5`)

### 3.1 [Bloquant] Garde « already started » : `return;` nu → aucune réponse

`src/Service/Instance/InstanceManager.php` :

- `startRealDeviceInstance()` — garde aux environs de la **ligne 257-269** (dev) :
  ```php
  $deviceInstance = array_filter($labInstance["deviceInstances"], function ($deviceInstance) use ($uuid) {
      return ($deviceInstance['uuid'] == $uuid && $deviceInstance['state'] != 'started');
  });

  if (!count($deviceInstance)) {
      $this->logger->info("Device instance is already started. Aborting.", ..., [
          'instance' => $deviceInstance['uuid']   // ← undefined index : tableau vide
      ]);
      return;                                     // ← retourne null !
  }
  ```
- `startDeviceInstance()` — même garde, **ligne 474-484** (dev) / ~472 (U2.5).

Conséquence dans `src/MessageHandler/InstanceActionMessageHandler.php` :

- Ligne **77** : `$returnState = $ReturnArray["state"];` → `null` (warning).
- Lignes **195-207** : `new InstanceStateMessage($instanceType, $ReturnArray["uuid"], $returnState, ...)`
  → paramètres `string` non nullables → **`TypeError`**.
- Ce `TypeError` est un `Error`, **hors** des `catch` du handler (voir §3.3) → **aucun
  `InstanceStateMessage` n'est expédié**.
- Messenger rejoue le message 3 fois (retry identique, payload figé avec `state: "started"`) →
  même `TypeError` à chaque fois → `failure_transport` désactivé → **message perdu silencieusement**
  → device **bloqué en `starting` définitivement**.

Ce chemin est *exactement* celui censé implémenter « device déjà démarré → répondre started sans
redémarrer ». Il se déclenche quand le descripteur porte `state == 'started'` **ou** quand l'uuid
est introuvable dans `deviceInstances` (filtre vide).

> Sur `master` (checkout local) : même défaut — garde à `src/Service/Instance/InstanceManager.php:214-219`
> (physique) et `:546-551` (virtuel), accès `$ReturnArray["state"]` à
> `src/MessageHandler/InstanceActionMessageHandler.php:76`, dispatch à `:171-176`.

### 3.2 [Bloquant] `array_key_exists("state", null)` sur un démarrage QEMU frais

`src/Service/Instance/InstanceManager.php`, dans `startDeviceInstance()` — **ligne 512 (U2.5) /
520 (dev)** (introduit par le commit `4b78925` « Avoid log when qemu already started ») :

```php
if (strtolower($deviceInstance['device']['hypervisor']['name']) === 'qemu') {
    $result = $this->create_qemu_device($deviceInstance, $bridgeName, $instancePath, $sandbox);
    if ($result["state"] === InstanceStateMessage::STATE_STARTED) {
        if (!array_key_exists("state", $result["options"])) {   // ← options === null ⇒ TypeError (PHP ≥ 8.1)
            $this->logger->info("This device can be configured on network:...");
        }
    }
}
```

`create_qemu_device()` renvoie `"options" => null` sur un **démarrage frais réussi**
(`qemu_start` retourne 0) — vérifié :

```
$ php -r 'var_dump(array_key_exists("state", null));'
TypeError: array_key_exists(): Argument #2 ($array) must be of type array, null given
```

- `TypeError` → s'échappe (§3.3) → pas de réponse pour **cette tentative**.
- Le retry (2 s plus tard) trouve la VM déjà dans son scope systemd (`qemu_start` → retour 1 →
  `options = ["state" => "already_running"]`) et répond `STATE_STARTED` : le symptôme est alors un
  démarrage « gelé » puis OK.
- En revanche, si les retries échouent aussi (VM qui ne reste pas up, scope non détecté,
  worker redémarré pendant les retries, etc.) → message perdu → **bloqué en `starting`**.
- Les chemins sains (réponse immédiate `STATE_STARTED`) sont : QEMU déjà running
  (`qemu_start` : contrôle `systemctl is-active qemu-<uuid>.scope`, ~L1239 U2.5 / ~L1274 dev),
  LXC déjà running (`lxc_start` : `lxc_is_running`, ~L1589 dev, options `already_running`),
  switch `natif`.

> Note : la vérification juste au-dessus (L542 dev / L534 U2.5) est correcte
> (`!is_null($result["options"]) && !array_key_exists(...)`).

### 3.3 `catch (Exception)` insuffisant + `failure_transport` désactivé

`src/MessageHandler/InstanceActionMessageHandler.php` :

- Ligne **146** : `catch (ProcessFailedException $e)` ; ligne **158** : `catch (Exception $e)`.
  Aucun `catch (\Throwable)` → tout `Error`/`TypeError` s'échappe, **aucune réponse**.
- `config/packages/messenger.yaml` : `# failure_transport: failed` commenté (idem `failed:`) →
  après 3 retries, le message est **rejeté/disparu sans trace exploitable**.

### 3.4 Sur `master` uniquement (non déployé) : réponses `STATE_ERROR` non idempotentes

À titre de référence, `master` n'a pas les garde-fous « already running » de `dev` :
LXC déjà lancé → `lxc-start` échoue → `STATE_ERROR` (~`lxc_start`, L1766-1801) ;
websockify déjà sur le port → `remote_access_start` en erreur → `STATE_ERROR` (~L739-778) ;
seul le verrou d'image QEMU est toléré (`qemu_start` L1573-1589 → `STATE_STARTED`).

## 4. Constats — front

### 4.1 Le garde worker n'est jamais atteint (le worker fait un démarrage complet)

`src/MessageHandler/WorkerHandshakeMessageHandler.php:69-79` : l'état est passé à `starting`
**avant** la sérialisation. Le descripteur envoyé porte donc `state: "starting"`, le filtre
worker (`state != 'started'`) laisse passer, et le worker tente un **démarrage complet** — c'est
précisément le chemin qui déclenche le `TypeError` de §3.2 au lieu du raccourci « déjà running ».

### 4.2 Aucune réconciliation d'un device bloqué en `starting`

- Le front n'a **ni timeout ni watchdog** qui ramène un device en `starting` vers `error` :
  `RouteMonitorCommand` ne touche que les routes, `ProxyServiceMonitor` ne touche que les proxies,
  `ScheduledActionService` se contente de garder-fous d'état.
- Conséquence : **toute réponse perdue = device bloqué à jamais en `starting`**.

### 4.3 `array_key_exists("state", null)` côté front

`src/MessageHandler/InstanceStateMessageHandler.php:421` :

```php
case InstanceStateMessage::STATE_STARTED:
    if (!array_key_exists("state", $options)) {   // $options peut être null (fresh start)
        $this->notificationService->success(...);
    }
```

Le worker envoie `"options" => null` sur un démarrage frais → `TypeError` (front PHP ≥ 8.5).
L'état est **déjà appliqué** et flushé (L407-409) avant le switch, donc le device passe bien en
`started`, mais : notification « started successfully » jamais envoyée, handler en échec,
message rejoué puis perdu (`catch (\Exception)` à L519 ne catch pas le `TypeError`).

### 4.4 Un device en `starting`/`started` ne peut pas être relancé depuis l'UI

`src/Service/Instance/InstanceManager.php:334-340` : si l'état est `creating|starting|started|resetting`,
la fonction logge « already running » mais le `throw` est **commenté** et tout le dispatch est dans
le `else` → **aucun message n'est envoyé**. Un device bloqué en `starting` ne peut être relancé
qu'après un stop. À documenter / corriger (voir §6).

### 4.5 Garde du handshake sur l'IP

`WorkerHandshakeMessageHandler.php:67` : `if ($workerIp == $message->getId())`. Fonctionne car les
workers `dev`/`Upgrade-2.5` envoient `WORKER_IP` (commit `edfea87`). **Ne pas régresser** : sur
`master`, l'envoi vaut `'id'` et le resync ne se déclencherait jamais.

## 5. Corrections à faire — worker (prioritaire)

Branche déployée : `dev` / `Upgrade-2.5`.

1. **Faire du garde « already started » une réponse explicite** — `InstanceManager::startRealDeviceInstance()`
   et `startDeviceInstance()` remplacer le `return;` par :
   ```php
   return [
       "state"   => InstanceStateMessage::STATE_STARTED,
       "uuid"    => $uuid,
       "options" => ["state" => "already_running"],
   ];
   ```
   (mêmes options que le chemin `qemu_start` déjà running → le front sait que c'est un déjà-lancé
   et n'affiche pas « started successfully »). Corriger au passage le log `'instance' => $deviceInstance['uuid']`
   (tableau vide) → utiliser `$uuid`.

2. **Rendre le §3.2 null-safe** — ligne 512 (U2.5) / 520 (dev) :
   ```php
   if (!isset($result["options"]["state"])) { ... }
   ```
   (ou `is_array($result["options"] ?? null) && !array_key_exists("state", $result["options"])`).

3. **Toujours répondre** — dans `InstanceActionMessageHandler::__invoke` :
   - ajouter `catch (\Throwable $e)` (en plus de `Exception`) qui positionne `STATE_ERROR` ;
   - **avant** le dispatch (L200), normaliser défensivement :
     ```php
     $returnState   = $ReturnArray["state"]   ?? InstanceStateMessage::STATE_ERROR;
     $returnUuid    = $ReturnArray["uuid"]    ?? $message->getUuid();
     $returnOptions = $ReturnArray["options"] ?? null;
     ```
     et les utiliser dans `new InstanceStateMessage($instanceType, $returnUuid, $returnState, $returnOptions)`.
     Un handler `ACTION_*` ne doit **jamais** se terminer sans envoyer d'`InstanceStateMessage`.

4. **Rendre les pertes visibles** — `config/packages/messenger.yaml` : décommenter
   `failure_transport: failed` et le transport `failed` (au minimum en log/alerte).

5. *(Option)* Placer le contrôle « déjà running » (`systemctl is-active` scope / `lxc_is_running`)
   **en tête** de `startDeviceInstance()`, avant OVS/iptables/téléchargement, pour un raccourci
   propre et rapide du resync.

## 6. Corrections à faire — front

1. **`InstanceStateMessageHandler.php:421`** : remplacer par `if (!isset($options["state"]))`
   (null-safe, mêmes intentions). — **✅ fait**

2. **Réconciliation / watchdog (indispensable)** : commande périodique (type `RouteMonitorCommand`,
   timer systemd toutes les 5 min) qui repasse en `error` (+ notification) tout `DeviceInstance`
   en `starting` depuis plus de N minutes (ex. 10 min), ou éventuellement rejoue **une** fois le
   start. Sans cela, toute perte de message (worker §3, RabbitMQ, mise à jour) laisse le device
   bloqué à jamais. — **✅ fait** : `app:instance:reconcile-starting`
   (`src/Command/ReconcileStartingInstancesCommand.php`, `--min-age` défaut 10 min, `--dry-run`),
   reposant sur `device_instance.state_changed_at` (migration `Version20261013120000`, mis à jour
   par `DeviceInstance::setState()`), repo `DeviceInstanceRepository::findStuckStartingBefore()`,
   timer systemd `bin/systemd/remotelabz-instance-reconcile.{service,timer}` (toutes les 5 min).

3. **Handshake : rollback d'état** — `WorkerHandshakeMessageHandler.php` : enregistrer l'état
   précédent de chaque device et le restaurer si `serialize`/`dispatch` lève (try/catch par device)
   pour ne pas laisser un device en `starting` sans message envoyé. — **✅ fait** (`catch (\Throwable)`
   par device, restauration de l'état précédent).

4. **`InstanceManager::start()` (§4.4)** : soit réactiver le `throw` (et renvoyer une 409 à
   l'utilisateur), soit, pour les états `starting`/`started`, **rejouer le message de start**
   (c'est la fonction de résiliation idéale d'un device bloqué). Au minimum, documenter le
   contournement : stop puis start. — ⏳ **à faire** (le watchdog couvre l'impasse côté UI).

5. *(Ne pas changer)* garder la sérialisation avec `starting` : c'est le worker qui doit être
   fiable (§5.1/§5.2) pour transformer ce « démarrage » en réponse `started` sans redémarrage.

6. **Handshake IP** : ne pas régresser sur un worker `master` (id `'id'`) — voir §4.5.

## 7. Diagnostics (confirmer quel chemin se déclenche en production)

Côté worker (`journalctl -u remotelabz-worker`) :

| Message / trace | Signification |
|---|---|
| `Device instance is already started. Aborting.` | garde §3.1 → ensuite un `TypeError` au dispatch |
| `array_key_exists(): Argument #2 ($array) must be of type array, null given` | §3.2 |
| `Error thrown while handling message ... (event #...)` + 3 repeats | retry puis perte |
| `QEMU scope already active` / `Already started` / `LXC container is already running` | chemin sain, réponse envoyée |
| `State ... send back to the front` | le handler a tenté d'envoyer une réponse (ligne 173-175) |

Côté front (`var/log/prod.log`) :

| Message | Signification |
|---|---|
| `Received Worker Handshake message !` | déclencheur du resync (horodatage = quand ?) |
| `Device instance ... is in state Starting or Started` + `Sending start message to ...` | resync émis |
| `No error received from :... instance message state:started` | réponse appliquée |
| `array_key_exists(): Argument #2 ($array) must be of type array, null given` | §4.3 |

Interprétation : device en `starting` **sans** `State ... send back to the front` après le start →
réponse jamais produite (§3.1/§3.2/§3.3) ; **avec** « send back » mais sans `No error received` →
problème front/livraison (§4).

## 8. Tests de non-régression

1. **Resync d'un device réellement démarré** (QEMU, LXC, physique) : réponse `STATE_STARTED`
   immédiate avec `options.state = already_running`, aucune reconfiguration visible, notification
   absente côté UI, état `started` conservé.
2. **Démarrage frais QEMU** : premier `ACTION_START` → `STATE_STARTED` avec `options = null`,
   notification « started successfully », aucun retry dans les logs worker.
3. **Démarrage frais LXC / physique / switch natif** : idem.
4. **Device réellement mort mais `started` en base** (cas du resync) : le worker redémarre
   réellement et répond `STATE_STARTED`.
5. **Injection d'une erreur** (ex. couper le PDU / renommer une image) : réponse `STATE_ERROR`,
   front → `error` + notification ; **jamais** de device sans réponse.
6. **Perte de réponse simulée** (stopper le worker après réception) : le watchdog front ramène le
   device en `error` après N minutes.
7. **Redémarrage du worker** : handshake → resync → tous les devices reviennent en `started`.
