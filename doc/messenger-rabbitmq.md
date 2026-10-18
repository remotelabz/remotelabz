# RabbitMQ / Symfony Messenger — communication front ↔ worker

> Documentation des files de messages qui relient le front (`remotelabz`) et les
> workers (`remotelabz-worker`) via RabbitMQ.
>
> Fichiers de référence :
> `config/packages/messenger.yaml` (front et worker),
> `lib/remotelabz-message-bundle` (classes de messages),
> `src/MessageHandler` (front) et `src/MessageHandler` (worker).
>
> Compléments : `README-LabPlacement.md`, `doc/start-resync-state-started.md`,
> `doc/instance-ip-collection.md` (collecte d'IP),
> `lib/remotelabz-message-bundle/shared-labs-message-bundle-plan.md`.

## 1. Vue d'ensemble

Le front et les workers sont deux applications Symfony distinctes qui dialoguent
uniquement par messages asynchrones, jamais en HTTP :

```
  Front (remotelabz)                    RabbitMQ                     Worker (remotelabz-worker)
  ──────────────────                    ────────                     ───────────────────────────

  dispatch InstanceActionMessage ─┐
  dispatch SecurityMessage ───────┤
  dispatch DeviceInfoRequestMessage
                                  └─► exchange « worker » (direct) ──► queue messages_worker1
                                      routing key = IP du worker         │
                                                                         └─► messenger:consume worker
                                                                             ├─ InstanceActionMessageHandler
                                                                             ├─ SecurityMessageHandler
                                                                             └─ DeviceInfoRequestMessageHandler

  messenger:consume front
  ├─ InstanceStateMessageHandler  ◄── exchange « front » (fanout) ◄──── dispatch InstanceStateMessage
  ├─ InstanceLogMessageHandler    ◄── queue messages_front ◄─────────── dispatch InstanceLogMessage
  ├─ WorkerHandshakeMessageHandler◄──────────────────────────────────── dispatch WorkerHandshakeMessage
  ├─ DeviceInfoMessageHandler     ◄──────────────────────────────────── dispatch DeviceInfoMessage
  └─ LabLaunchRequestMessageHandler
       (file « front » alimentée par le front lui-même)
```

- **Front → worker** : `InstanceActionMessage` (ordres : créer/supprimer/démarrer…),
  `SecurityMessage` (topologie des labs partagés) et `DeviceInfoRequestMessage`
  (demande d'IP d'une instance).
- **Worker → front** : `InstanceStateMessage` (changements d'état),
  `InstanceLogMessage` (logs d'instance), `WorkerHandshakeMessage`
  (démarrage du consumer worker) et `DeviceInfoMessage` (réponse à une demande
  d'IP).
- **Front → front** : `LabLaunchRequestMessage` (placement d'un lab, volontairement
  sérialisé dans une file pour éviter les courses entre créations simultanées).

## 2. Protocole et sérialisation

| Élément | Valeur |
|---|---|
| Protocole | **AMQP 0-9-1** (RabbitMQ), TCP port **5672** |
| Client PHP | `php-amqplib` via le bridge `symfony/amqp-messenger` |
| DSN | `MESSENGER_TRANSPORT_DSN="amqp://remotelabz-amqp:password-amqp@127.0.0.1:5672/%2f/messages"` (`.env` / `.env.local`) |
| Vhost | `/` (encodé `%2f`) |
| Segment final du chemin DSN | `messages` — nom d'**exchange par défaut** du bridge AMQP. **Inutilisé** : tous les transports déclarent explicitement leur exchange (`worker`, `front`), donc cet échange par défaut n'est plus créé (voir §3). |
| Sérialisation | **`PhpSerializer`** (sérialiseur natif de Messenger, défaut) : l'enveloppe complète est passée à `serialize()`, puis `addslashes()` (base64 si le résultat n'est pas de l'UTF-8) |
| Format dans la file | Binaire PHP sérialisé — **pas du JSON** (le JSON n'apparaît qu'à l'intérieur des champs `content` de certains messages) |
| Durabilité | Exchanges et queues déclarés durables (défaut Symfony) ; messages persistants |
| Routage | Exchange `worker` de type **direct** : la routing key (`AmqpStamp`) est **l'IP du worker**, c'est elle qui choisit la queue |

Conséquences importantes de la sérialisation PHP native :

- Les **classes de messages doivent être identiques des deux côtés** : c'est
  précisément le rôle du bundle partagé `lib/remotelabz-message-bundle`
  (namespace `Remotelabz\Message\Message`, en *path repository* Composer dans le
  front **et** dans le worker).
- Une classe de message absente côté récepteur fait échouer le décodage
  (`Message class ... not found during decoding`). D'où l'ordre de déploiement :
  **bundle → workers → front** (cf. `shared-labs-message-bundle-plan.md` §7).
- La routing key n'est jamais stockée dans le message lui-même : elle est posée
  au `dispatch()` avec `new AmqpStamp($workerIp, AMQP_NOPARAM, [])`.

## 3. Topologie RabbitMQ

### 3.1 Exchanges et queues

| Exchange | Type | Queue(s) | Binding | Rôle |
|---|---|---|---|---|
| `worker` | `direct` | `messages_worker1` | `192.168.11.132` (IP du worker) | Front → worker. La routing key = IP choisit la queue du bon worker. |
| `front` | *(fanout — défaut)* | `messages_front` | clé par défaut (`null`), sans effet en fanout | Worker → front (états, logs, handshake) **et** front → front (placement). |
| `delays` | `direct` | `delay_<exchange>_<routingKey>_<delay>` | créées à la volée | Rejeu des messages en échec après un délai (`x-message-ttl` + dead-letter vers l'exchange d'origine). |
| `failed` | `fanout` | `messages_failed` | — | **Worker uniquement** : messages échoués après épuisement des retries (`failure_transport`). |

Côté front, le transport `failed` est en base de données
(`doctrine://default?queue_name=failed`, table `messenger_messages`) mais
`failure_transport` n'est **pas activé** : après les retries, un message front
échoué est perdu (voir §7.4). Côté worker, il n'y a pas de base de données :
les échecs partent dans l'exchange AMQP `failed`.

### 3.2 Un binding par worker

Chaque worker possède sa propre queue, reliée à l'exchange `worker` par sa
propre IP :

```yaml
# config/packages/messenger.yaml (extrait)
worker:
    dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
    options:
        exchange:
            name: worker
            type: direct
        queues:
            messages_worker1:
                binding_keys: [192.168.11.132]
```

Ajouter un worker = ajouter une queue avec `binding_keys: [<IP du worker>]` dans
la configuration **du front et du worker** (même nom de queue), puis redémarrer
les consumers.

## 4. Configuration Symfony Messenger

### 4.1 Front — `config/packages/messenger.yaml`

```yaml
framework:
    messenger:
        transports:
            worker:                                      # front → worker
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    exchange: { name: worker, type: direct }
                    queues:
                        messages_worker1:
                            binding_keys: [192.168.11.132]
                retry_strategy:                          # 3 essais, 5 s entre chaque
                    max_retries: 3
                    delay: 5000
                    multiplier: 1
                    max_delay: 0
            front:                                       # worker → front (+ front → front)
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    exchange: { name: front }            # fanout par défaut
                    queues:
                        messages_front: null
            failed: 'doctrine://default?queue_name=failed'   # déclaré, non activé (pas de failure_transport)
        routing:
            Remotelabz\Message\Message\SecurityMessage: worker
            Remotelabz\Message\Message\InstanceActionMessage: worker
            Remotelabz\Message\Message\InstanceStateMessage: front
            Remotelabz\Message\Message\LabLaunchRequestMessage: front
            Remotelabz\Message\Message\DeviceInfoRequestMessage: worker
            Remotelabz\Message\Message\DeviceInfoMessage: front
```

### 4.2 Worker — `config/packages/messenger.yaml`

Même topologie, vue du worker :

```yaml
framework:
    messenger:
        failure_transport: failed                    # activé côté worker

        transports:
            # ... worker / front : mêmes exchanges et queues que le front ...
            failed:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    exchange: { name: failed, type: fanout }
                    queues: { messages_failed: ~ }

        routing:
            Remotelabz\Message\Message\InstanceActionMessage: worker
            Remotelabz\Message\Message\InstanceLogMessage: front
            Remotelabz\Message\Message\InstanceStateMessage: front
            Remotelabz\Message\Message\WorkerHandshakeMessage: front
            Remotelabz\Message\Message\DeviceInfoMessage: front
```

### 4.3 Routage

Le routage Messenger ne s'applique qu'à l'**émission** (`dispatch()`) : il choisit
l'exchange où publier. La réception dépend uniquement de la queue consommée :

| Message | Routé vers (front) | Routé vers (worker) | Queue réellement consommée |
|---|---|---|---|
| `InstanceActionMessage` | `worker` | `worker` | `messages_worker1` |
| `SecurityMessage` | `worker` | — | `messages_worker1` |
| `InstanceStateMessage` | `front` | `front` | `messages_front` |
| `InstanceLogMessage` | — | `front` | `messages_front` |
| `WorkerHandshakeMessage` | — | `front` | `messages_front` |
| `LabLaunchRequestMessage` | `front` | — | `messages_front` |
| `DeviceInfoRequestMessage` | `worker` | — | `messages_worker1` |
| `DeviceInfoMessage` | `front` | `front` | `messages_front` |

### 4.4 Retries

- **Transport `worker` (front)** : 3 essais, délai 5000 ms fixe. Un message en
  échec est republié dans une queue de retard
  (ex. `delay_worker_192.168.11.132_5000`), puis revient via dead-letter dans
  `messages_worker1`.
- **Transport `front`** : `retry_strategy` non défini → défaut Symfony
  (3 essais, 1 s, multiplicateur 2).
- Côté worker, les transports `worker` et `front` ont chacun leur
  `retry_strategy` explicite (3 essais, 2 s).
- Un message qui échoue définitivement suit le `failure_transport` :
  `failed` (AMQP) sur le worker, **perdu** sur le front.

## 5. Les messages du bundle `remotelabz-message-bundle`

Le bundle (`lib/remotelabz-message-bundle`, PSR-4 `Remotelabz\Message\`) ne
contient **que des DTO** : aucun code d'exécution, aucune dépendance Symfony ou
Doctrine. C'est le contrat partagé entre le front et le worker.

| Message | Sens | Champs | Producteur (front) | Consommateur (handler) |
|---|---|---|---|---|
| `InstanceActionMessage` | front → worker | `content`, `uuid`, `action` | `InstanceManager`, `LabLaunchRequestMessageHandler`, `Files2WorkerManager`, `ConfigWorkerController`, `LabInternetManager`, `WorkerHandshakeMessageHandler` | worker : `InstanceActionMessageHandler` |
| `SecurityMessage` | front → worker | `content` (JSON), `group` | `SharedLabSecurityManager::syncGroup()` | worker : `SecurityMessageHandler` |
| `InstanceStateMessage` | worker → front | `type`, `uuid`, `state`, `options` | worker : `InstanceActionMessageHandler` | front : `InstanceStateMessageHandler` |
| `InstanceLogMessage` | worker → front | `content`, `uuid`, `type`, `scope` | worker : `LogDispatcher` | front : `InstanceLogMessageHandler` |
| `WorkerHandshakeMessage` | worker → front | `id` (IP du worker) | worker : `WorkerStartedEventSubscriber` | front : `WorkerHandshakeMessageHandler` |
| `LabLaunchRequestMessage` | front → front | `labInstanceUuid`, `autoStartDevices` | `InstanceManager::create()` | front : `LabLaunchRequestMessageHandler` |
| `DeviceInfoRequestMessage` | front → worker | `content` (JSON du lab, groupe `worker`), `uuid`, `requestId` | `CollectInstanceIpsCommand` | worker : `DeviceInfoRequestMessageHandler` |
| `DeviceInfoMessage` | worker → front | `uuid`, `requestId`, `info` (`ips`, `collectedAt`), `error` | worker : `DeviceInfoRequestMessageHandler` | front : `DeviceInfoMessageHandler` |

### 5.1 `InstanceActionMessage` — ordres envoyés au worker

`content` = JSON de l'instance (sérialisation JMS, groupe `worker`),
`uuid` = UUID du device ou du lab concerné, `action` = une des constantes :

| Constante | Valeur | Effet côté worker |
|---|---|---|
| `ACTION_CREATE` | `create` | Crée l'instance de lab (bridge OVS, réseau…) |
| `ACTION_DELETE` | `delete` | Supprime l'instance de lab |
| `ACTION_START` | `start` | Démarre un device (ou resynchronise après handshake) |
| `ACTION_STOP` | `stop` | Arrête un device |
| `ACTION_RESET` | `reset` | Redémarre un device |
| `ACTION_CONNECT` | `connect` | Connecte le lab à Internet |
| `ACTION_DISCONNECT` | `disconnect` | Déconnecte le lab d'Internet |
| `ACTION_EXPORT_LAB` | `export_lab` | Exporte une instance de lab |
| `ACTION_EXPORT_DEV` | `export_device` | Exporte un device |
| `ACTION_DELETEDEV` | `delete_device` | Supprime un device |
| `ACTION_DELETEOS` | `delete_os` | Supprime une image OS |
| `ACTION_RENAMEOS` | `rename_os` | Renomme une image OS |
| `ACTION_COPY2WORKER_DEV` | `copy_os` | Copie une image OS vers un worker |
| `ACTION_COPYFROMFRONT` | `copyfromfront` | Copie un fichier du front vers le worker |
| `ACTION_DELETEISO` | `delete_iso` | Supprime une ISO |
| `ACTION_RENAMEISO` | `rename_iso` | Renomme une ISO |

Le constructeur refuse toute action hors de cette liste
(`InvalidArgumentException`). Pour `CONNECT`/`DISCONNECT`, le worker ne renvoie
volontairement **aucun** `InstanceStateMessage` (c'est le front qui suit
`internetConnected`).

### 5.2 `InstanceStateMessage` — états renvoyés par le worker

| Champ | Valeurs |
|---|---|
| `type` | `lab`, `device` |
| `state` | `creating`, `created`, `deleting`, `deleted`, `starting`, `started`, `stopping`, `stopped`, `resetting`, `reset`, `exporting`, `exported`, `renamed`, `os_copied`, `os_deleted`, `iso_copied`, `iso_deleted`, `file_copied`, `error` |
| `options` | tableau contextuel : sur erreur, contient au minimum `state` = action échouée (`rename_os`, `export_device`, `copy_os`…) ; certains cas portent `old_name`/`new_name`, `worker_dest_ip`… |

Le handler front (`InstanceStateMessageHandler`) met à jour l'état en base,
notifie les utilisateurs et, sur `error`, déclenche les compensations
(annulation d'export, de renommage…). Sur `STATE_DELETED` d'un lab, il
ressynchronise aussi la topologie des labs partagés
(`SharedLabSecurityManager::syncLabInstance()`).

> Point d'attention : le **constructeur** accepte toutes les constantes
> `STATE_*`, mais `setState()` n'en accepte qu'un sous-ensemble
> (`created, creating, deleted, deleting, error, started, starting, stopped,
> stopping, reset, resetting`). Utiliser le constructeur pour les autres.

### 5.3 `InstanceLogMessage` — logs d'instance

| Champ | Valeurs |
|---|---|
| `content` | texte du log |
| `uuid` | UUID du device instance |
| `type` | `info`, `debug`, `warning`, `error` |
| `scope` | `public` (affiché aux utilisateurs) ou `private` (défaut, réservé à l'admin) |

Émetteur : `LogDispatcher` côté worker — seuls `error()`, `warning()`, `info()`
et `log()` avec un contexte `instance` sont expédiés ; `debug()`/`notice()`
restent dans les logs locaux du worker. Le front persiste chaque message dans
`DeviceInstanceLog`.

### 5.4 `SecurityMessage` — topologie des labs partagés

- `content` : JSON de la topologie d'**un seul groupe** :

  ```json
  {
    "group": "<uuid du groupe>",
    "links": [
      {
        "a": { "uuid": "…", "network": "10.11.0.0/24", "workerIp": "192.168.11.132" },
        "b": { "uuid": "…", "network": "10.11.1.0/24", "workerIp": "192.168.11.133" }
      }
    ]
  }
  ```

- `group` : UUID du groupe, également clé d'état côté worker.
- Émetteur : `SharedLabSecurityManager::syncGroup()` — un message **par groupe
  et par worker disponible** (routing key = IP du worker). Déclencheurs :
  placement d'un lab, start/stop d'un device, suppression d'instance, création/
  suppression d'une règle de partage, retrait d'un lab d'un groupe, handshake.
- Récepteur : `SecurityMessageHandler` → `InstanceManager::updateSharedSecurity()`.
  Sémantique côté worker : le message **remplace** l'entrée du groupe ; l'état
  global est l'union de tous les groupes ; `links: []` purge les règles et
  routes du groupe. Les liens sont non orientés (le worker génère les deux sens).

### 5.5 `WorkerHandshakeMessage` — resynchronisation au démarrage

Émis par le worker à chaque démarrage du consumer (`WorkerStartedEventSubscriber`
sur `WorkerStartedEvent`), `id` = IP du worker. Le front
(`WorkerHandshakeMessageHandler`) :

1. retrouve les device instances `starting`/`started` placées sur ce worker ;
2. leur renvoie un `InstanceActionMessage::ACTION_START` (resync idempotent :
   si le device tourne déjà, le worker répond `started` sans redémarrer) ;
3. resynchronise la topologie des labs partagés des groupes concernés
   (`SecurityMessage`).

### 5.6 `LabLaunchRequestMessage` — placement asynchrone d'un lab

Émis par `InstanceManager::create()`, consommé par le **front lui-même** via la
queue `messages_front`. Rôle : sortir la décision de placement du cycle de la
requête HTTP et **sérialiser** les placements (un seul consumer `front`), pour
que les réservations mémoire (`LabPlacementCache`) soient prises en compte avant
le calcul suivant (cf. `README-LabPlacement.md`). Le handler choisit le meilleur
worker, pose la route IP, puis émet les `InstanceActionMessage`
(`create`, éventuellement `start` des devices et `connect` Internet) et les
`SecurityMessage`.

### 5.7 `DeviceInfoRequestMessage` / `DeviceInfoMessage` — collecte de l'IP d'une instance

Couple requête/réponse pour récupérer l'IP réelle d'une `DeviceInstance` (VM
QEMU ou conteneur LXC), connue du seul worker qui l'héberge. L'IP n'est **pas
persistée en base** : elle transite par messages puis est mise en cache Redis
côté front. Voir `doc/instance-ip-collection.md` pour le plan complet.

| Champ | `DeviceInfoRequestMessage` | `DeviceInfoMessage` |
|---|---|---|
| `content` | JSON du `LabInstance` (sérialisation JMS, groupe `worker`), identique à `ACTION_START` : la demande est auto-portante, le worker ne conserve aucun état | — |
| `uuid` | UUID de la `DeviceInstance` concernée | UUID de la `DeviceInstance` concernée |
| `requestId` | identifiant de corrélation généré par le front | identifiant renvoyé tel quel (libère le verrou anti-spam) |
| `info` | — | `['ips' => [['name', 'mac', 'ipv4' => [], 'ipv6' => []], …], 'collectedAt' => int]` |
| `error` | — | `null` en succès, message d'erreur sinon |

Résolution côté worker (`DeviceIpResolver`) : `lxc-info -n <uuid> -iH` pour un
conteneur LXC ; pour une VM QEMU, qemu-guest-agent en priorité
(`guest-network-get-interfaces`), puis repli sur la table ARP du pont (par MAC)
ou les baux DHCP Kea. Le handler **répond toujours**, y compris en erreur, sinon
le verrou anti-spam du front expire sans réponse.

Déclenchement : le timer front `remotelabz-instance-ips.timer` exécute
`app:instance:collect-ips` toutes les minutes ; une entrée n'est redemandée que
si elle est absente ou périmée (`INSTANCE_IP_MAX_AGE`, 600 s par défaut), avec un
verrou anti-spam de 10 s par instance.

## 6. Scénarios de bout en bout

### 6.1 Création d'un lab par un utilisateur

1. HTTP → `InstanceManager::create()` : persiste l'instance (`creating` +
   réservation mémoire) puis `dispatch(LabLaunchRequestMessage)`.
2. **Front** : `LabLaunchRequestMessageHandler` choisit un worker
   (`WorkerManager::getFreeWorker`), renseigne `workerIp`, ajoute la route IP,
   puis `dispatch(InstanceActionMessage, ACTION_CREATE)` avec
   `AmqpStamp(<IP worker>)`.
3. `SharedLabSecurityManager::syncLabInstance()` diffuse les `SecurityMessage`
   des groupes concernés.
4. Si demandé : `ACTION_START` pour chaque device (les devices cachés démarrent
   toujours), puis `ACTION_CONNECT` si le lab est autorisé à sortir sur Internet.
   Tous ces messages vont dans la même queue (FIFO) : le worker les exécute dans
   l'ordre.
5. **Worker** : `InstanceActionMessageHandler` exécute l'action et renvoie
   `InstanceStateMessage(created, lab)`.
6. **Front** : `InstanceStateMessageHandler` met à jour la base, notifie les
   utilisateurs et réaligne la topologie partagée.

### 6.2 Démarrer / arrêter / réinitialiser un device

`InstanceManager::start()/stop()/reset()` → `InstanceActionMessage`
(`start`/`stop`/`reset`) → worker → `InstanceStateMessage`
(`started`/`stopped`/`stopping`… + `reset`) → front → base + notification.

### 6.3 Redémarrage d'un worker (ou du consumer)

`WorkerHandshakeMessage` → front → resync `ACTION_START` des devices concernés →
états `started` réémis. Voir `doc/start-resync-state-started.md` (analyse des
pertes de messages et watchdog).

### 6.4 Logs d'une instance

Worker : `LogDispatcher->error()/warning()/info()` →
`InstanceLogMessage` → front → ligne `DeviceInstanceLog` (affichée selon
`scope`).

### 6.5 Labs partagés

Toute modification (règle de partage, démarrage/arrêt d'instance…) →
`SharedLabSecurityManager::syncGroup()` → un `SecurityMessage` par worker et par
groupe → le worker reconstruit ses règles iptables/routes sur l'union des
groupes.

### 6.6 Collecte de l'IP d'une instance

1. Le timer front `remotelabz-instance-ips.timer` exécute
   `app:instance:collect-ips` (toutes les minutes).
2. La commande parcourt les device instances `started` placées sur un worker ;
   pour chaque entrée absente ou périmée (`INSTANCE_IP_MAX_AGE`), elle pose un
   verrou anti-spam (Redis) puis `dispatch(DeviceInfoRequestMessage)` avec
   `AmqpStamp(<IP worker>)`.
3. **Worker** : `DeviceInfoRequestMessageHandler` résout l'IP (`DeviceIpResolver`)
   et `dispatch(DeviceInfoMessage)` — **toujours**, même en erreur.
4. **Front** : `DeviceInfoMessageHandler` (consumer `front`) écrit
   `device_info.<uuid>` dans Redis et libère le verrou.
5. Le front expose l'IP : champ `ip`/`ips` des réponses de polling
   (`DeviceController`) et listener JMS `post_serialize` sur `DeviceInstance`
   pour les réponses `api_get_lab_instance`.
6. À l'arrêt, à la suppression, sur erreur ou au (re)démarrage, l'entrée est
   supprimée (`DeviceInfoCache::forget()`) : une IP périmée n'est jamais servie.

## 7. Exploitation et dépannage

### 7.1 Consumers

| Processus | Commande | Service systemd |
|---|---|---|
| Front | `php bin/console messenger:consume front --memory-limit=128M --time-limit=3600` | `remotelabz.service` |
| Worker | `php bin/console messenger:consume worker` | `remotelabz-worker.service` |

Redémarrage propre après un déploiement (les consumers s'arrêtent en fin de
traitement) :

```bash
php bin/console messenger:stop-workers
```

> **Un seul consumer `front` à la fois** : le cache de placement est en
> lecture-modification-écriture ; deux consumers concurrents peuvent écraser
> leurs réservations respectives (`README-LabPlacement.md`). Ne pas lancer un
> second `messenger:consume front` à la main en plus du service.

### 7.2 Inspection RabbitMQ

```bash
rabbitmqctl list_exchanges name type durable
rabbitmqctl list_queues name messages messages_ready messages_unacknowledged consumers
rabbitmqctl list_bindings source_name destination_name routing_key
rabbitmqctl list_consumers
```

### 7.3 Messages en échec

Worker (transport `failed` en AMQP) :

```bash
php bin/console messenger:failed:show
php bin/console messenger:failed:retry
```

Front : le transport `doctrine://default?queue_name=failed` existe mais
`failure_transport` n'est pas activé — les messages ne sont pas conservés.
Pour activer la collecte, ajouter `failure_transport: failed` sous
`framework.messenger` dans `config/packages/messenger.yaml`.

### 7.4 Points d'attention

- **Ordre** : FIFO par queue, donc par worker. L'ordre `create` → `start` →
  `connect` est garanti par l'ordre d'émission vers une même queue.
- **Idempotence** : les retries rejouent le message ; les handlers sont tolérants
  (placement déjà fait ignoré, start sur device déjà démarré → `started` sans
  redémarrage…).
- **Perte de message** : un message échoué définitivement côté front est perdu
  (pas de failure transport) ; le watchdog `ReconcileStartingInstancesCommand`
  rattrape les devices bloqués en `starting` (voir
  `doc/start-resync-state-started.md`).
- **Version du bundle** : les classes de messages doivent être identiques des
  deux côtés (sérialisation PHP native). Déployer le bundle, puis les workers,
  puis le front.
- **Ajout d'un worker** : déclarer sa queue (`messages_workerN`,
  `binding_keys: [<IP>]`) dans **les deux** projets, puis redémarrer les
  consumers.
- **Sécurité** : credentials AMQP dans `.env` ; connexion en clair sur le
  réseau interne (port 5672), pas de TLS.
