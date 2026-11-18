# Processus de lancement d'un lab

Ce document décrit le parcours complet du lancement d'une instance de lab,
de la requête HTTP « Join lab » jusqu'au lab joignable. Les références de
lignes sont celles du checkout courant (branche `Potential-route-issues`).

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
(`src/Controller/ConfigWorkerController.php:109-151` — `newAction()`,
`createQueue()` :334, `bindQueue()` :394).

---

## 2. Le processus de lancement, étape par étape

```
UI « Join lab »            PHP-FPM (front)                    File "front" (1 consommateur)              Worker
─────────────              ─────────────────                  ──────────────────────────────              ──────
POST /api/instances/create
  InstanceController
  ::createAction ─────────► InstanceManager::create()
     (InstanceController.php:527)
                             ├─ anti-doublon par instancier (184-202)
                             ├─ hasAvailableWorker() (204)
                             ├─ LabInstance::create() + instancier (209-231)
                             ├─ getAvailableSubnet() (240)  ◄── /28 libre, persisté+flushé
                             │    IMMÉDIATEMENT (NetworkManager.php:69)
                             ├─ setNetwork+populate, state=creating (246-251)
                             ├─ persist + flush  (261-262)
                             │    (en cas d'erreur : libération du /28,
                             │     263-281)
                             ├─ placementCache->add(uuid, RAM)  (287, Redis)
                             └─ dispatch LabLaunchRequestMessage ──► LabLaunchRequestMessageHandler
                                                                           (LabLaunchRequestMessageHandler.php:81)
                                                                         ├─ idempotence : workerIp déjà
                                                                         │   renseigné ? → skip (95-99)
                                                                         ├─ getFreeWorker(lab)  (107)
                                                                         │    ├─ stats HTTP de chaque worker
                                                                         │    └─ + réservations Redis
                                                                         ├─ worker = '' ? → state=error (110-123)
                                                                         ├─ setWorkerIp(W) + flush  (125-127)
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
(`src/Controller/InstanceController.php:527-590`) : résout le `Lab` et
l'instancier (user / guest / group), vérifie les permissions, appelle
`InstanceManager::create()`.

Variante groupée : un `ScheduledAction` de type `ACTION_START` exécute
`ScheduledActionService::execute()`
(`src/Service/Instance/ScheduledActionService.php:112`, branche START) qui
appelle `create()` **séquentiellement, dans un seul process**, pour chaque
utilisateur du groupe (boucle 140-151).

### Étape 2 — `InstanceManager::create()`

`src/Service/Instance/InstanceManager.php:179-298` :

1. **Anti-doublon** (184-202) : une instance du même lab pour le même
   instancier ? → `null`.
2. **Fail-fast** (204) : `hasAvailableWorker()` (comptage DB des workers
   `available`).
3. **Construction de l'instance** (209-231) : `LabInstance::create()` +
   `setLab()` + instancier (user / guest / group).
4. **Allocation du sous-réseau** (240) : `getAvailableSubnet()` — voir
   ci-dessous ; `null` → `NoNetworkAvailableException` (242-244).
5. **Persistance** (246-262) : `setNetwork()` + `populate()` (une
   `DeviceInstance` par device du lab) + timer éventuel, puis `persist` +
   `flush`. À ce moment `workerIp = NULL`, `state = creating`. En cas
   d'erreur (263-281) : **libération du /28 réservé** par un
   `DELETE FROM network WHERE id = ?` brut via la `Connection` (indépendant
   du UnitOfWork), puis re-jeu de l'exception.
6. **Réservation mémoire** (286-287) : `placementCache->add(uuid, RAM)` dans
   le pool Redis `lab_placement` (cf. `README-LabPlacement.md`).
7. **Délégation** (291-293) : `dispatch(LabLaunchRequestMessage)` → file
   `front`.

**L'allocation du sous-réseau** (`NetworkManager::getAvailableSubnet()`,
`src/Service/Network/NetworkManager.php:69-111`) : à chaque essai
(3 maximum, `MAX_ALLOCATION_ATTEMPTS`, ligne 24), `pickFreeSubnet()`
(115-137) parcourt la base réseau configurée par pas de /28
(`LAB_NETWORK_NETMASK`) et renvoie le premier /28 absent du snapshot de la
table `Network` (`findAll()`), dans les limites de la base réseau. Le /28
choisi est **persisté + flushé immédiatement** : la réservation est commitée
avant que tout autre appel à `create()` ne puisse continuer. L'index unique
`uniq_network_ip_netmask (ip__long, netmask__long)` garantit qu'un /28 n'est
alloué qu'une seule fois ; en cas de conflit, l'entité échouée est
dés-scheduleée (`remove()`, aucun SQL) et l'allocation est retentée avec
relecture de la table. Après 3 conflits : `NoNetworkAvailableException`.

Le /28 redevient libre à la suppression de l'instance (cascade
`LabInstance` → `Network`) ; `app:cron:nightly`
(`CronNightlyCommand::cleanOrphanNetworks()`) nettoie les éventuelles lignes
`network` orphelines.

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
   if (IPTools::routeExists($network)) {          // ligne 131 : existence de la route
        // rien à faire (un log trace ce cas)
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

Trois canaux :

| Canal | Mécanisme | Source de vérité |
|-------|-----------|------------------|
| **Trafic front → devices du lab** | route noyau `<CIDR> via <workerIP>` sur le front | le CIDR (table 254) |
| **Consoles (VNC/ttyd)** | `InstanceController::viewInstanceAction` → `ProxyManager::createDeviceInstanceProxyRoute(uuid, port, workerIp)` → proxy : `ws://<workerIP>:<port+1000>` | `LabInstance.workerIp` (DB) |
| **IPs des devices** | cron `CollectInstanceIpsCommand` → `DeviceInfoRequestMessage` → worker → `DeviceInfoCache` (Redis) | `LabInstance.workerIp` (DB) |

---

## 3. Où le worker est stocké / transmis

| Lieu | Forme | Remarque |
|------|-------|----------|
| `LabInstance.workerIp` (DB) | IP | **Source de vérité** ; écrite uniquement à `LabLaunchRequestMessageHandler.php:125` |
| Routing key RabbitMQ (`AmqpStamp`) | IP | Tous les `InstanceActionMessage` partent vers la file du worker de la DB |
| `ConfigWorker.queueName ↔ IPv4` | IP ↔ `messages_workerN` | Binding RabbitMQ + `messenger.yaml` |
| `LabPlacementCache` (Redis) | `uuid → {memory, workerIp, createdAt}` | Réservations de placement |
| `DeviceInfoCache` (Redis) | `uuid → {ips, workerIp, …}` | IPs collectées |
| Route noyau front | `<CIDR> via <IP>` | Cléée par le sous-réseau /28 du lab |
| Routes proxy | cible = `workerIP:port` | Recréées à la vue et au redémarrage du proxy (`ProxyServiceMonitor::injectRoutes()`) |

### Fonctions de route du front

`src/Bridge/Network/IPTools.php` :

| Fonction | Lignes | Comportement |
|----------|--------|--------------|
| `routeAdd($route, $gateway)` | 125-138 | `ip route add <CIDR> via <gw>` |
| `routeExists($route)` | 275-286 | `ip route show table 254` : existe-t-il une route vers le CIDR ? |
| `routeExistsVia($route, $gateway)` | 296-311 | une route vers le CIDR passe-t-elle par ce gateway ? |
| `routeReplace($route, $gateway)` | 322-330 | `ip route replace` — idempotent |
| `routeDelete($route, $gateway=null)` | 164-177 | `ip route del <CIDR> [via <gw>]` |
