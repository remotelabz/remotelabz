# Processus de lancement d'un lab — et le risque « route vers le mauvais worker »

> **Statut** : analyse du code (aucun changement apporté). Les références de
> lignes sont celles du checkout courant.
>
> **Question** : est-il possible qu'un lab se lance sur un worker alors que la
> route (front → réseau du lab) est construite vers un *autre* worker ?
>
> **Réponse courte** : **oui, le risque existe**. La route du noyau est indexée
> par le **sous-réseau /28 du lab (CIDR)**, et non par l'instance du lab. Deux
> mécanismes peuvent produire une route vers le mauvais worker :
>
> 1. **collision de sous-réseau** — `NetworkManager::getAvailableSubnet()`
>    n'a ni verrou ni contrainte d'unicité : deux instances créées en
>    parallèle peuvent recevoir le *même* /28. La route n'est créée que pour
>    la première (celle dont le placement est traité en premier) ; le second
>    lab, placé sur un autre worker, est injoignable depuis le front.
> 2. **route obsolète** — le handler teste `IPTools::routeExists($network)`
>    (existence d'une route vers le CIDR, **quel que soit le gateway**) et
>    saute `routeAdd` si une route existe déjà. Une route résiduelle vers un
>    worker mort (ou vers l'ancien lab qui occupait ce /28) est conservée.
>
> Un filet de sécurité existe (`app:route:monitor`, toutes les 5 min, qui
> corrige via `routeReplace`) mais il ne soigne pas la collision elle-même et
> est conditionné à la santé du worker. Détails au §5 et §6.

---

## 1. Architecture en un coup d'œil

| Composant | Rôle |
|-----------|------|
| **Front** (ce dépôt) | UI/API Symfony, base MySQL, placement, routes noyau, proxy, planification |
| **Worker** (`remotelabz-worker`, dépôt séparé) | VMs QEMU + conteneurs LXC, ponts OVS (`br-<uuid8>`), Kea DHCP ; API HTTP (`/stats/hardwarelight`, `/os`, …) |
| **remotelabz-proxy** (service séparé) | Reverse proxy des consoles `/device/<uuid>` (VNC noVNC, ttyd) |
| **RabbitMQ** | Bus de messages : exchange direct `worker` (routing key = **IP du worker** → file `messages_workerN`) ; fanout `front` (file `messages_front`, **1 seul consommateur**) |
| **Redis** | Pools `lab_placement` (réservations mémoire) et `device_info` (IPs des devices) |

Le front et le worker ne communiquent **que par messages RabbitMQ**, jamais
par HTTP (sauf les stats de placement et le healthcheck). Voir
`doc/messenger-rabbitmq.md`.

Le worker est enregistré dans la table `ConfigWorker`
(`src/Entity/ConfigWorker.php`) : `IPv4` (unique), `queueName`
(`messages_workerN`, unique), `available` (bool). L'enregistrement crée la
file RabbitMQ et le binding `routing_key = IP du worker`
(`src/Controller/ConfigWorkerController.php:107-150`, `createQueue()` :334,
`bindQueue()` :394).

---

## 2. Le processus de lancement, étape par étape

```
UI « Join lab »            PHP-FPM (front)                    File "front" (1 consommateur)              Worker
─────────────              ─────────────────                  ──────────────────────────────              ──────
POST /api/instances/create
  InstanceController
  ::createAction ─────────► InstanceManager::create()
     (InstanceController.php:525)
                             ├─ anti-doublon par instancier (184-198)
                             ├─ hasAvailableWorker() (204)
                             ├─ LabInstance::create()+populate() (209-242)
                             ├─ getAvailableSubnet()  ◄── /28 libre, SANS verrou (NetworkManager.php:40)
                             ├─ state=creating, workerIp=NULL
                             ├─ persist + flush  (252-253)   ◄── la ligne Network est persistée ici
                             ├─ placementCache->add(uuid, RAM)  (259, Redis)
                             └─ dispatch LabLaunchRequestMessage ──► LabLaunchRequestMessageHandler
                                                                          (LabLaunchRequestMessageHandler.php:81)
                                                                        ├─ idempotence : workerIp déjà
                                                                        │   renseigné ? → skip (95-99)
                                                                        ├─ getFreeWorker(lab)  (107)
                                                                        │    ├─ stats HTTP de chaque worker
                                                                        │    └─ + réservations Redis
                                                                        ├─ worker = '' ? → state=error (110-123)
                                                                        ├─ setWorkerIp(W) + flush  (125-127) ◄── SEUL point d'écriture
                                                                        ├─ SI route CIDR absente :
                                                                        │    routeAdd(CIDR via W) (131-135)
                                                                        ├─ dispatch ACTION_CREATE
                                                                        │    (routing key = W) (142-146) ────► pont OVS br-<uuid8>,
                                                                        ├─ placementCache->assign(uuid, W) (150)   réseau, devices
                                                                        ├─ syncLabInstance (security) (154)
                                                                        ├─ ACTION_START par device (156-177) ──►
                                                                        └─ ACTION_CONNECT si internet (186-195) ─►
                                                                        ◄─────────── InstanceStateMessage (STATE_CREATED)
                                                                        InstanceStateMessageHandler
                                                                          ├─ state=created en base
                                                                          └─ placementCache->remove(uuid)
```

### Étape 1 — Requête HTTP

`POST /api/instances/create` → `InstanceController::createAction`
(`src/Controller/InstanceController.php:525-590`) : résout le `Lab` et
l'instancier (user / guest / group), vérifie les permissions, appelle
`InstanceManager::create()`.

Variante groupée : un `ScheduledAction` de type `ACTION_START` exécute
`ScheduledActionService::execute()`
(`src/Service/Instance/ScheduledActionService.php:112-182`) qui appelle
`create()` **séquentiellement, dans un seul process**, pour chaque utilisateur
du groupe (boucle 140-151). Pas de concurrence *à l'intérieur* d'un même
batch — mais un batch planifié peut coïncider avec des requêtes HTTP
(§5.2).

### Étape 2 — `InstanceManager::create()`

`src/Service/Instance/InstanceManager.php:179-270` :

1. **Anti-doublon** (184-198) : une instance du même lab pour le même
   instancier ? → `null`. (Remarque : ce test n'est pas atomique — un
   double-clic dans la fenêtre avant le `flush` passe deux fois, cf. le
   commentaire ligne 183.)
2. **Fail-fast** (204) : `hasAvailableWorker()` (comptage DB des workers
   `available`).
3. **Création** (209-242) : `LabInstance::create()` + `populate()` (une
   `DeviceInstance` par device du lab) ; `setNetwork(getAvailableSubnet())`.
4. **Persistance** (252-253) : `persist` + `flush`. À ce moment
   `workerIp = NULL`, `state = creating`. La ligne `Network` associée est
   persistée par cascade (`src/Entity/LabInstance.php:43`,
   `cascade: ['persist','remove']`).
5. **Réservation mémoire** (258-259) : `placementCache->add(uuid, RAM)` dans
   le pool Redis `lab_placement` (cf. `README-LabPlacement.md`).
6. **Délégation** (263-265) : `dispatch(LabLaunchRequestMessage)` → file
   `front`.

**L'allocation du sous-réseau** (`NetworkManager::getAvailableSubnet()`,
`src/Service/Network/NetworkManager.php:40-56`) : lit **toutes** les lignes
`Network` (`findAll()`), puis parcourt `10.11.0.0/16` par pas de /28
(`LAB_NETWORK_NETMASK`) et renvoie le premier libre. **Aucun verrou, aucune
contrainte d'unicité** sur l'entité `Network`
(`lib/network-bundle/Entity/Network.php:9-23` : `id`, `ip`, `netmask`, rien
d'autre). Deux process qui appellent `create()` en parallèle peuvent lire le
même état de la table et choisir le **même** /28 avant que l'un des deux
n'ait flushé.

### Étape 3 — `LabLaunchRequestMessageHandler` (placement + route + lancement)

`src/MessageHandler/LabLaunchRequestMessageHandler.php:81-204`. Consommé par
**un seul** processus (`bin/systemd/remotelabz.service` :
`messenger:consume front`) : les placements sont donc **sérialisés** — c'est
ce qui rend le mécanisme de réservations fiable (cf.
`README-LabPlacement.md`).

1. Chargement de l'instance par uuid ; introuvable → drop + libération de la
   réservation (87-92).
2. **Idempotence** : `workerIp` déjà renseigné (message rejoué) → skip +
   libération (95-99).
3. **Choix du worker** (104-108) : `WorkerManager::getFreeWorker($lab)`
   (`src/Service/Worker/WorkerManager.php:201-238`) — stats HTTP live de
   chaque worker disponible + réservations Redis, score
   `0.3×mémoire + 0.1×disque + 0.3×CPU + 0.3×lxcfs`, le plus haut gagne.
4. Aucun worker → `state = error`, notification, libération (110-123).
5. **`setWorkerIp($worker)` + flush** (125-127) — **le seul point d'écriture
   de l'attribution worker dans tout le codebase**.
6. **Route noyau** (129-137), uniquement si `!singleServer` :

   ```php
   $network = $labInstance->getNetwork();
   if (IPTools::routeExists($network)) {          // ligne 131 : existence, n'importe quel gateway
       // debug log « Route to … exists, via … »
   } else {
       IPTools::routeAdd($network, $worker);      // ligne 135 : ip route add <CIDR> via <worker>
   }
   ```

7. Sérialisation `LabInstance` (groupe JMS `worker`) + `dispatch(ACTION_CREATE)`
   avec `AmqpStamp($worker)` → routing key = IP du worker → file
   `messages_workerN` (139-146).
8. `placementCache->assign(uuid, $worker)` (150) — la réservation est
   désormais imputée à ce worker.
9. `securityManager->syncLabInstance()` (154) — topologie shared-lab
   (iptables/routes) incluant le `workerIp`.
10. `ACTION_START` par device (devices cachés toujours, autres si
    `autoStartDevices`) vers la **même** file worker (FIFO : create → start →
    connect) (156-177).
11. Lab internet-autorisé : `ACTION_CONNECT` + `internetConnected = true`
    (186-195).

### Étape 4 — Côté worker (dépôt séparé)

Le worker crée le pont OVS `br-<uuid8>` (`LabInstance::getBridgeName()`,
`src/Entity/LabInstance.php:99-102`), le réseau du lab, les devices ; répond
`InstanceStateMessage(type=lab, state=created)` → file `front`.

### Étape 5 — `InstanceStateMessageHandler`

`src/MessageHandler/InstanceStateMessageHandler.php` : met à jour l'état en
base ; aux états finaux `created` / `deleted` / `error` d'un lab →
`placementCache->remove(uuid)` ; à `deleted` → suppression de la ligne
`LabInstance` (et de la ligne `Network` par cascade → **le /28 redevient
libre**).

### Étape 6 — Le lab est « joignable »

Trois canaux, avec trois sources de vérité différentes :

| Canal | Mécanisme | Source de vérité |
|-------|-----------|------------------|
| **Trafic front → devices du lab** | route noyau `<CIDR> via <workerIP>` sur le front | **le CIDR** (table 254) — pas l'instance ! |
| **Consoles (VNC/ttyd)** | `InstanceController::viewInstanceAction` → `ProxyManager::createDeviceInstanceProxyRoute(uuid, port, workerIp)` → proxy : `ws://<workerIP>:<port+1000>` | `LabInstance.workerIp` (DB) |
| **IPs des devices** | cron `CollectInstanceIpsCommand` → `DeviceInfoRequestMessage` → worker → `DeviceInfoCache` (Redis) | `LabInstance.workerIp` (DB) |

Seule la **route noyau** n'est pas indexée par l'instance : c'est elle qui
peut diverger du worker réel (§5).

---

## 3. Où le worker est stocké / transmis

| Lieu | Forme | Remarque |
|------|-------|----------|
| `LabInstance.workerIp` (DB) | IP | **Source de vérité unique** ; écrite uniquement à `LabLaunchRequestMessageHandler.php:125` |
| Routing key RabbitMQ (`AmqpStamp`) | IP | Tous les `InstanceActionMessage` partent vers la file du worker de la DB |
| `ConfigWorker.queueName ↔ IPv4` | IP ↔ `messages_workerN` | Binding RabbitMQ + `messenger.yaml` |
| `LabPlacementCache` (Redis) | `uuid → {memory, workerIp, createdAt}` | Réservations de placement |
| `DeviceInfoCache` (Redis) | `uuid → {ips, workerIp, …}` | IPs collectées |
| **Route noyau front** | **`<CIDR> via <IP>`** | **Indexée par le CIDR du lab, pas par l'instance** |
| Routes proxy | cible = `workerIP:port` | Recréées à la vue et au redémarrage du proxy (`ProxyServiceMonitor::injectRoutes()`) |

Tous les canaux lisent `workerIp` de la DB **sauf la route noyau**, qui est
cléée sur le sous-réseau. C'est précisément ce qui ouvre le risque.

---

## 4. Ce que font les fonctions de route

`src/Bridge/Network/IPTools.php` :

| Fonction | Lignes | Comportement |
|----------|--------|--------------|
| `routeAdd($route, $gateway)` | 125-138 | `ip route add <CIDR> via <gw>` — **échoue** si une route vers le CIDR existe déjà |
| `routeExists($route)` | 275-286 | `ip route show table 254` + regex `^<CIDR>(?=\s|$)` — **vrai si une route vers le CIDR existe, quel que soit le gateway** |
| `routeExistsVia($route, $gateway)` | 296-311 | regex `^<CIDR>\s+via\s+<gw>` — vrai seulement si le next-hop est bien le gateway attendu |
| `routeReplace($route, $gateway)` | 322-330 | `ip route replace` — idempotent, corrige une route erronée |
| `routeDelete($route, $gateway=null)` | 164-177 | `ip route del <CIDR> [via <gw>]` — **sans gateway, supprime n'importe quelle route vers le CIDR** |

---

## 5. Analyse : la route peut-elle pointer vers le mauvais worker ?

### 5.1 Observation clé

La route noyau est **partagée par tous les labs qui ont le même /28**. Le
handler de placement (ligne 131) se demande « une route vers ce CIDR existe-t-
elle ? » et non « la route vers ce CIDR pointe-t-elle vers *mon* worker ? ».
Deux situations rendent la réponse « oui » alors que la route est fausse pour
l'instance en cours :

- un **autre lab** occupe le même CIDR (collision, §5.2) ;
- une **route résiduelle** d'un lab précédent ou d'un worker mort (§5.3).

### 5.2 Scénario A — Collision de sous-réseau (le plus grave)

**Préconditions** : deux appels `InstanceManager::create()` **concurrents**,
dans deux process distincts (PHP-FPM gère les requêtes en parallèle) :

- deux utilisateurs qui rejoignent un lab au même instant (même lab, ou deux
  labs) ;
- un démarrage groupé planifié (`ScheduledActionService`, process CLI) qui
  coïncide avec une requête HTTP ;
- un double-clic « Join lab » (l'anti-doublon n'est pas atomique, §2 étape 2.1).

**Chronologie** :

```
T0  process 1 : getAvailableSubnet() → lit la table Network → choisit 10.11.0.0/28
T1  process 2 : getAvailableSubnet() → lit la table Network (le process 1 n'a pas
                encore flushé) → choisit aussi 10.11.0.0/28
T2  les deux flushent → 2 lignes Network identiques (aucune contrainte d'unicité),
    2 LabInstances (workerIp=NULL)
T3  consommateur front (séquentiel) : placement instance 1 → worker W1
    → routeExists(10.11.0.0/28) = false → routeAdd(10.11.0.0/28 via W1)
T4  placement instance 2 → worker W2 (load balancing : W1 a une réservation,
    W2 gagne) → routeExists(10.11.0.0/28) = TRUE (la route via W1 existe)
    → routeAdd SAUTÉ
```

**Résultat** : le lab 2 tourne sur **W2**, mais la route du front pointe vers
**W1**. Le trafic front → devices du lab 2 arrive sur W1, où il n'y a pas de
pont `br-<uuid8>` du lab 2 : les paquets sont perdus. Le lab 2 est injoignable
en réseau depuis le front (les consoles VNC/ttyd fonctionnent encore, car le
proxy lit `workerIp` de la DB — le lab est donc « visible mais muet », ce qui
rend le diagnostic difficile).

La collision a en plus des effets indépendants de la route : deux ponts OVS
avec le même /28 sur deux workers, pools DHCP Kea superposés, adresses IP en
double.

**Fenêtre de course** : entre `findAll()` (NetworkManager.php:43) et
`flush()` (InstanceManager.php:253) — elle inclut `populate()` (création de
toutes les `DeviceInstance`, d'autant plus longue que le lab a de devices).
C'est une fenêtre de l'ordre de la dizaine à la centaine de millisecondes :
rare, mais réelle, et **systématique** si deux process créent des instances
simultanément (batch planifié + requête HTTP).

**Aggravation à la suppression** : `InstanceManager::delete()` supprime la
route **sans gateway** (`routeDelete($network, null)`,
`src/Service/Instance/InstanceManager.php:305`). Si le lab 1 (W1) est
supprimé pendant que le lab 2 (W2, même CIDR) tourne, la route disparaît pour
les deux : le lab 2 devient injoignable jusqu'au passage du cron
(§6). Symétriquement, tant que les deux labs coexistent, la route ne peut
pointer que vers un seul des deux workers : **un des deux labs est toujours
injoignable**.

### 5.3 Scénario B — Route obsolète (stale route)

**Préconditions** : une route `<CIDR> via W1` subsiste dans la table du front
alors que le CIDR est de nouveau libre en base. Cas concrets :

- le worker W1 a été réinstallé / nettoyé à la main : le worker a supprimé
  les labs lui-même, a renvoyé `STATE_DELETED` (ou le front a purgé la base),
  la ligne `Network` est supprimée par cascade → le /28 redevient libre, mais
  **personne n'a supprimé la route noyau** (la suppression se fait dans
  `InstanceManager::delete()`, qui n'a pas été appelé) ;
- le front a été redémarré / la table de routage a été modifiée à la main ;
- un worker a changé d'IP (§5.5).

**Chronologie** :

```
T0  route résiduelle : 10.11.0.0/28 via W1 (W1 mort, ou lab disparu)
T1  nouveau lab C : getAvailableSubnet() → 10.11.0.0/28 (libre en base)
T2  placement : W1 est exclu (indisponible/saturé) → lab C placé sur W2
T3  routeExists(10.11.0.0/28) = TRUE (la route via W1 existe) → routeAdd SAUTÉ
```

**Résultat** : lab C sur W2, route vers W1. Injoignable depuis le front
jusqu'au passage du `RouteMonitorCommand` (§6), qui fait bien
`routeExistsVia` + `routeReplace` — mais seulement si W2 répond au healthcheck
`GET /os`.

### 5.4 Scénario C — Suppression « sans gateway » (détail)

Même sans collision, `routeDelete($network, null)` (InstanceManager.php:305)
supprime **toute** route vers le CIDR. Si une route vers ce CIDR avait été
ajoutée pour une autre raison (autre lab en collision, route manuelle,
mode mono-serveur §5.6), elle disparaît. C'est un comportement « trop
large » : la suppression devrait être `routeDelete($network, $workerIP)`
(avec gateway), comme l'indique d'ailleurs le log de debug ligne 302
(« exists via $workerIP ») et le commentaire en ligne 304.

### 5.5 Scénario D — Changement d'IP d'un worker

`ConfigWorkerController::updateAction()` (src/Controller/ConfigWorkerController.php:152)
re-binde la file RabbitMQ sur la nouvelle IP (ligne ~294), mais :

- les `LabInstance.workerIp` existantes gardent l'**ancienne** IP ;
- les routes noyau et les routes proxy pointent vers l'ancienne IP ;
- les messages sont routés vers une file qui n'existe plus (ou vers la
  machine qui a repris l'IP).

C'est un cas d'administration, mais il produit exactement le symptôme
« lab sur un worker, route vers un autre ».

### 5.6 Scénario E — Mode mono-serveur (`DEPLOY_SINGLE_SERVER`)

Dans ce mode, le handler **saute** la création de route
(LabLaunchRequestMessageHandler.php:129). Les routes sont (re)créées par
`WorkerServiceMonitor::start()` (`src/Service/Monitor/WorkerServiceMonitor.php:55-97`)
qui utilise le **paramètre statique `workerServer`** (l'IP configurée du
worker unique) et le même test `routeExists()` sans gateway (ligne 80). Avec
un seul worker le risque est faible, mais le même antipattern est présent.

### 5.7 Ce qui protège déjà (et leurs limites)

| Protection | Où | Limite |
|------------|----|--------|
| Placement sérialisé (1 consommateur `front`) | `bin/systemd/remotelabz.service` | Ne protège pas l'allocation du /28, qui se fait en PHP-FPM **avant** le message |
| `RouteManagerService::syncLabInstanceRoute()` : `routeExistsVia` + `routeReplace` | `src/Service/Network/RouteManagerService.php:62-127` | Ne s'exécute que via le cron ; si le worker ne répond pas au healthcheck (`GET /os`), la route n'est **pas** corrigée (`worker_unavailable`) |
| Cron `app:route:monitor` | `bin/systemd/remotelabz-route-monitor.timer` : **toutes les 5 min** | Fenêtre de latence jusqu'à ~5 min ; en cas de collision (§5.2), chaque exécution laisse la route sur le worker de la **dernière** instance (dans l'ordre de `findAll()`) qui porte ce CIDR : l'autre lab reste injoignable **en permanence**, pas seulement transitoirement |
| Idempotence du handler (`workerIp` déjà set → skip) | LabLaunchRequestMessageHandler.php:95-99 | Ne rejoue pas la route : un message rejoué ne corrige pas une route erronée |
| Anti-doublon d'instance | InstanceManager.php:184-198 | Non atomique (double-clic dans la fenêtre avant flush) |

---

## 6. Conclusion

**Oui, il est possible qu'un lab se lance sur un worker et que la route soit
construite vers le mauvais worker.** Ce n'est pas un bug de placement (le
placement est sérialisé et fiable, cf. `README-LabPlacement.md`) : c'est un
bug de **modèle de la route**, qui est indexée par le CIDR du lab au lieu
d'être indexée par l'instance.

| Scénario | Probabilité | Impact | Durée |
|----------|------------|--------|-------|
| A — collision de /28 (créations concurrentes) | Faible mais réelle (batch planifié + HTTP, double-clic) | Lab injoignable en réseau **en permanence** (le cron ne peut pas corriger : deux instances légitimes partagent le CIDR) ; consoles OK | Indéfini, jusqu'à suppression d'un des deux labs |
| B — route obsolète (worker mort / lab purgé sans suppression de route) | Possible après incident | Lab injoignable | ≤ ~5 min (cron) si le worker est sain |
| C — `routeDelete` sans gateway | Conséquence de A | Route disparue pour un lab innocent | ≤ ~5 min (cron) |
| D — changement d'IP worker | Cas d'admin | Tous les labs du worker injoignables | Jusqu'à correction manuelle |
| E — mono-serveur | Faible (1 worker) | Idem B | ≤ ~5 min |

Le symptôme observable : un lab dont les **consoles fonctionnent** (proxy,
basé sur `workerIp` de la DB) mais dont le **réseau est muet** (route noyau,
basée sur le CIDR). C'est le piège diagnostique principal.

---

## 7. Correctifs recommandés

1. **Le handler doit vérifier le gateway, pas seulement l'existence**
   (corrige B, C et rend le placement idempotent) :

   ```php
   // LabLaunchRequestMessageHandler.php:131
   if (IPTools::routeExistsVia($network, $worker)) {
       // route correcte, rien à faire
   } else {
       IPTools::routeReplace($network, $worker);   // ajoute OU corrige
   }
   ```

   Même changement dans `WorkerServiceMonitor::start()` (ligne 80-87).

2. **Suppression de route avec gateway** (corrige C) :

   ```php
   // InstanceManager.php:305
   IPTools::routeDelete($network, $workerIP);
   ```

3. **Allocation de sous-réseau sans collision** (corrige A — le seul correctif
   qui traite la cause racine) :
   - contrainte d'unicité `(ip, netmask)` sur la table `network` + retry en
     cas de violation (le perdant re-choisit le /28 suivant) ;
   - ou allocation atomique (compteur dédié, `SELECT … FOR UPDATE`, lock
     Redis) dans `NetworkManager::getAvailableSubnet()` ;
   - en complément, une **détection en fin de placement** : si une autre
     `LabInstance` active porte déjà le même CIDR, passer l'instance en
     erreur avec un message explicite plutôt que de lancer un lab
     injoignable.

4. **Surveillance** : le `RouteMonitorCommand` existe déjà ; ajouter un
   contrôle de **doublons de CIDR** entre `LabInstance` actives (une requête
   SQL suffit) pour détecter le scénario A a posteriori.

---

## 8. Vérification sur une installation réelle

```bash
# Routes actuelles vers les labs (table 254)
ip route show table 254 | grep '^10\.11\.'

# Audit sec : routes manquantes ou pointant vers le mauvais worker
php bin/console app:route:monitor --dry-run
#   « Routes wrong (would be corrected) » > 0 ⇒ route vers le mauvais worker

# Détecter les collisions de /28 entre instances actives
# (colonnes imbriquées Doctrine : ip_addr / netmask_addr)
mysql -e "SELECT ip_addr, netmask_addr, COUNT(*) c FROM network
          WHERE id IN (SELECT network_id FROM lab_instance WHERE state <> 'deleted')
          GROUP BY ip_addr, netmask_addr HAVING c > 1;"

# Attribution effective des derniers labs
grep -E "launched on worker" var/log/*.log | tail -30

# Comparer avec les routes : pour chaque « launched on worker X »,
# la route du CIDR de l'instance doit passer par X
grep -E "Route to .* (exists, via|doesn't exist, adding it via)" var/log/*.log | tail -30
```

Un lab « muet » (consoles OK, réseau KO) avec une route `via` une IP qui
n'est pas celle de son `workerIp` en base est la signature exacte du
scénario A ou B.
