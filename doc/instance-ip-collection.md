# Collecte à la demande de l'IP des instances (front ↔ worker, cache Redis)

> **Statut** : document de conception unique (fusion des deux plans de collecte
> d'IP qui existaient séparément). Rien n'est encore codé.
> **Décisions retenues** :
> 1. **Nouveau couple de messages** requête/réponse (`DeviceInfoRequestMessage`
>    front → worker, `DeviceInfoMessage` worker → front), un message par
>    `DeviceInstance` ;
> 2. **Aucune persistance en base** : le résultat vit dans **Redis** côté front,
>    avec TTL, invalidé aux transitions d'état ;
> 3. **Déclenchement par un timer systemd côté front** (`app:instance:collect-ips`,
>    toutes les minutes), qui rafraîchit si l'entrée est absente ou périmée
>    (péremption configurable, **10 min** par défaut) ;
> 4. **VM QEMU** : ajout du canal **qemu-guest-agent** (`guest-network-get-interfaces`)
>    avec **repli ARP / bail DHCP** ; **conteneurs LXC** : `lxc-info -n <uuid> -iH`.

---

## 1. Objectif

Rendre disponible, dans les réponses JSON que le front sert déjà à l'UI, l'adresse
IP de chaque **instance** (`DeviceInstance`) de VM QEMU ou de conteneur LXC.

L'IP n'est **connue que du worker** qui héberge l'instance. Le front ne dialogue
avec les workers que par **messages asynchrones** RabbitMQ
(`lib/remotelabz-message-bundle`). On ajoute donc un **aller-retour de messages** :
le front demande, le worker résout et répond, le front met le résultat en cache
Redis et l'expose dans les endpoints interrogés par l'UI.

Contraintes :

- **non bloquant** : aucun endpoint HTTP n'attend le worker ; l'UI n'est jamais
  ralentie par un worker lent ou hors ligne ;
- **sans BDD** : pas de colonne `DeviceInstance.ips`, pas de migration ;
- **dégradation propre** : worker indisponible ou agent absent → IP absente, pas
  d'erreur (même philosophie que `LabPlacementCache`).

---

## 2. État des lieux (constats vérifiés dans le code)

### 2.1 Où se trouve l'IP

| Couche | Entité | IP connue ? |
|---|---|---|
| Modèle d'équipement (template) | `Device` | `Device::$ip` existe mais ne concerne que le **physique** (PDU/console série) |
| Instance en cours d'exécution | `DeviceInstance` | **Aucun champ IP** aujourd'hui |
| Worker qui héberge l'instance | — | **Seul le worker connaît l'IP réelle** |

L'IP est donc **par instance**, jamais par modèle.

### 2.2 Attention au faux point d'entrée « état du device »

`GET /api/devices/{id}` (`DeviceController::showAction()`, l. 379) renvoie
l'entité **`Device`** (le modèle) : appelée une seule fois au montage, ce n'est
**pas** un polling. `GET /api/devices` (`indexAction()`, l. 124) ne liste que des
**templates sans lab**.

Le **polling réel de l'UI** se fait ailleurs :

| Endpoint | Appelant / période | Handler |
|---|---|---|
| `POST /api/labs/{id}/nodes` | `Editor2/.../functions.js`, `STATUSINTERVAL = 5000` (`javascript.js:22`) | `DeviceController::indexActionTest()` (l. 245) |
| `GET /api/instances/by-uuid/{uuid}` | `OptimizedInstanceList.js` (5 s, l. 272) | `InstanceController::fetchByUuidAction()` (l. 837) |
| `GET /api/instances/lab/{labUuid}/by-user\|by-guest\|by-group/...` | `InstanceManager.js` (10 s, l. 55) | `fetchLabInstanceBy{User,Guest,Group}Action()` (l. 858/883/909) |
| `GET /api/instances` | `AllInstancesList.js` (60 s) | `InstanceController::apiIndexAction()` (l. 182) |

C'est **sur ces réponses**, et non sur `GET /api/devices`, qu'il faut exposer l'IP
(§7.5).

### 2.3 Le canal naturel : RabbitMQ

Le front et le worker échangent déjà (`config/packages/messenger.yaml` de chaque
dépôt) :

- front → worker : `InstanceActionMessage`, `SecurityMessage` (routing key =
  IP du worker via `AmqpStamp`) ;
- worker → front : `InstanceStateMessage`, `InstanceLogMessage`,
  `WorkerHandshakeMessage`.

La sérialisation est **PHP native** (`PhpSerializer`, défaut Symfony) : les
classes de messages doivent être **identiques des deux côtés** → le bundle partagé
`lib/remotelabz-message-bundle`, et un **ordre de déploiement imposé** :
**bundle → workers → front** (cf. `lib/remotelabz-message-bundle/shared-labs-message-bundle-plan.md` §7).

### 2.4 D'où le worker peut tirer l'IP

| Cas | Source | Commande / fichier |
|---|---|---|
| LXC à IP statique (`scripts/template.txt`, conteneur « Service », sandbox) | le worker la calcule déjà (`$ip_addr` dans `create_lxc_device()`) | valeur en mémoire |
| LXC sans IP (`scripts/template-noip.txt`) | DHCP | `lxc-info -n <uuid> -iH` |
| VM QEMU | agent invité | socket QGA `guest-network-get-interfaces` |
| VM QEMU sans QGA | ARP / bail DHCP | `ip -4 neigh show dev <bridge>` par MAC, bail Kea/dnsmasq |

La **MAC** est connue du front et du worker :
`deviceInstance['networkInterfaceInstances'][k]['macAddress']`
(`NetworkInterfaceInstance::$macAddress`, groupes `worker`, `api_get_lab_instance`).
Le worker génère le template avec cette MAC (`build_template()`, l. 985/1107).

La résolution QEMU via le guest-agent ne dépend que du worker ; le repli ARP/DHCP
permet de couvrir les VM déjà démarrées (qui n'ont pas le canal QGA) et les images
sans `qemu-guest-agent`.

---

## 3. Architecture retenue

```
  Timer systemd (front, 1 min)
  app:instance:collect-ips
        │
        │  pour chaque DeviceInstance "started" :
        │    cache Redis absent ou périmé (> MAX_AGE) ?
        │      → verrou 10 s (device_info_pending.<uuid>)
        │      → dispatch DeviceInfoRequestMessage  ──AmqpStamp(workerIp)──▶ queue messages_workerN
        ▼                                                                          │
  ┌───────────────── FRONT (remotelabz) ─────────────────┐            ┌───────── WORKER ─────────────┐
  │ DeviceInfoMessageHandler  ──▶ DeviceInfoCache (Redis) │◀── front ──│ DeviceInfoRequestMessageHandler│
  │ DeviceInfoCache.get(uuid)                            │            │   └─ DeviceIpResolver          │
  │   ↑ injecté dans le JSON (polling 5/10/60 s)         │            │        ├─ QemuGuestAgentClient │
  └──────────────────────────────────────────────────────┘            │        ├─ repli ip neigh / bail│
                                                                       │        └─ lxc-info -iH          │
                                                                       └────────────────────────────────┘
```

Principes :

1. **Push/pull par messages uniquement** (pas de HTTP dans le chemin de rendu) ;
2. **Cache Redis côté front** avec TTL : l'UI lit une latence constante ;
3. **Anti-spam** : verrou court (TTL) avant chaque dispatch ; le timer ne
   redemande pas si l'entrée est fraîche ;
4. **Idempotence / extensibilité** : `DeviceInfoMessage.info` est un tableau
   libre (IP aujourd'hui, uptime, nombre de NIC… demain sans nouveau message) ;
5. **Décorrélation** : le front ne connaît pas QEMU/LXC, le worker ne connaît pas
   la base.

---

## 4. Bundle partagé `remotelabz-message-bundle`

Dépôt : `/home/florent/remotelabz-message-bundle` (path repository `lib/remotelabz-message-bundle`
côté front **et** côté worker).

Ajouter deux DTO dans `Message/` (namespace `Remotelabz\Message\Message`), sur le
modèle de `InstanceActionMessage`/`InstanceStateMessage` (DTO purs, sans dépendance
Symfony/Doctrine), plus leur test (le bundle a déjà `Tests/`).

### 4.1 `Message/DeviceInfoRequestMessage.php` (front → worker)

```php
<?php

namespace Remotelabz\Message\Message;

/**
 * Demande de collecte d'informations sur une DeviceInstance.
 *
 * $content   = descripteur JSON du LabInstance (sérialisation JMS, groupe
 *              "worker"), identique à celui de ACTION_START : la demande est
 *              auto-portante, le worker ne conserve aucun état.
 * $uuid      = UUID de la DeviceInstance concernée.
 * $requestId = identifiant de corrélation (UUID généré par le front) ; renvoyé
 *              tel quel par le worker pour libérer le verrou côté front.
 */
class DeviceInfoRequestMessage
{
    private string $content;
    private string $uuid;
    private string $requestId;

    public function __construct(string $content, string $uuid, string $requestId)
    {
        $this->content = $content;
        $this->uuid = $uuid;
        $this->requestId = $requestId;
    }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): self { $this->content = $content; return $this; }

    public function getUuid(): string { return $this->uuid; }
    public function setUuid(string $uuid): self { $this->uuid = $uuid; return $this; }

    public function getRequestId(): string { return $this->requestId; }
    public function setRequestId(string $requestId): self { $this->requestId = $requestId; return $this; }
}
```

### 4.2 `Message/DeviceInfoMessage.php` (worker → front)

```php
<?php

namespace Remotelabz\Message\Message;

/**
 * Réponse à DeviceInfoRequestMessage.
 *
 * $info  = ["ips" => [ {name, mac, ipv4[], ipv6[]}, ... ], "collectedAt" => int]
 *          Tableau libre et extensible (uptime, nb de NIC, état QEMU/LXC…).
 * $error = null en succès, message d'erreur sinon (device inconnu, échec worker…).
 */
class DeviceInfoMessage
{
    private string $uuid;
    private string $requestId;
    private array $info;
    private ?string $error;

    public function __construct(string $uuid, string $requestId, array $info = [], ?string $error = null)
    {
        $this->uuid = $uuid;
        $this->requestId = $requestId;
        $this->info = $info;
        $this->error = $error;
    }

    public function getUuid(): string { return $this->uuid; }
    public function getRequestId(): string { return $this->requestId; }
    public function getInfo(): array { return $this->info; }
    public function getError(): ?string { return $this->error; }
}
```

Fichiers :
- `Message/DeviceInfoRequestMessage.php` (créer)
- `Message/DeviceInfoMessage.php` (créer)
- `Tests/DeviceInfoRequestMessageTest.php`, `Tests/DeviceInfoMessageTest.php` (créer)
- `composer.json` : **bumper la version** (ex. `1.0.9` → `1.0.10`)

> **Pourquoi deux classes plutôt que réutiliser `InstanceStateMessage`** : ses
> champs `state`/`options` sont conçus pour les **transitions d'état** ; un « état »
> `device_info` violerait la sémantique (le constructeur accepte toutes les
> constantes `STATE_*` mais `setState()` n'en accepte qu'un sous-ensemble). Un
> couple requête/réponse dédié découple la collecte du cycle de vie des devices.

---

## 5. Routage Messenger

> Rappel : le routage ne concerne que l'**émission** (`dispatch()`). La réception
> dépend uniquement de la queue consommée.

### 5.1 FRONT — `config/packages/messenger.yaml`

Dans `routing:` (aujourd'hui l. 26-30) :

```yaml
        routing:
            Remotelabz\Message\Message\SecurityMessage: worker
            Remotelabz\Message\Message\InstanceActionMessage: worker
            Remotelabz\Message\Message\InstanceStateMessage: front
            Remotelabz\Message\Message\LabLaunchRequestMessage: front
            # nouveaux :
            Remotelabz\Message\Message\DeviceInfoRequestMessage: worker
            Remotelabz\Message\Message\DeviceInfoMessage: front
```

- `DeviceInfoRequestMessage → worker` : routé par **IP du worker** (`AmqpStamp`),
  comme `InstanceActionMessage` ; le worker le consomme sans configuration
  supplémentaire (queue `messages_worker1`).
- `DeviceInfoMessage → front` : le front ne l'émet pas, mais le déclarer évite
  toute erreur « Invalid Messenger routing configuration » et laisse la porte
  ouverte.

### 5.2 WORKER — `config/packages/messenger.yaml`

Dans `routing:` (aujourd'hui l. 63-68) :

```yaml
        routing:
            'Remotelabz\Message\Message\InstanceActionMessage': worker
            'Remotelabz\Message\Message\InstanceLogMessage': front
            'Remotelabz\Message\Message\InstanceStateMessage': front
            'Remotelabz\Message\Message\WorkerHandshakeMessage': front
            # nouveau :
            'Remotelabz\Message\Message\DeviceInfoMessage': front
```

> Un worker qui répond sur `front` alimente bien la queue `messages_front`
> consommée par le service `remotelabz.service`
> (`messenger:consume front`). Un seul consumer `front` : c'est lui qui écrit le
> cache côté front, jamais un endpoint HTTP (aucun blocage).

---

## 6. Worker (`/home/florent/remotelabz-worker`)

### 6.1 Canal qemu-guest-agent dans `create_qemu_device()`

`src/Service/Instance/InstanceManager.php`, l. 5222. Les buckets du tableau
`$parameters` (l. 5238) sont aplatis **dans l'ordre du littéral** par la boucle de
`qemu_start()` (l. 1303). Ajouter un bucket `agent` :

```php
        $parameters = [
            'system' => [],
            'smp' => ['-smp'],
            'network' => [],
            'local' => [],
            'usb' => [],
            'agent' => [],        // ← nouveau
            'access' => [],
            'uefi' => [],
            'cdrom' => [],
            'other_options' => []
        ];
```

Puis, après le bloc réseau (vers l. 5477), avant l'ajout des interfaces factices :

```php
        // Canal qemu-guest-agent : socket Unix par instance. Le socket existe
        // dès le lancement, mais l'agent doit être présent ET démarré dans l'invité.
        $qgaDir = '/run/remotelabz';
        if (!is_dir($qgaDir)) {
            @mkdir($qgaDir, 0750, true);
        }
        $qgaSocket = $qgaDir . '/qga-' . $deviceInstance['uuid'] . '.sock';
        array_push(
            $parameters['agent'],
            '-chardev', 'socket,id=qga0,path=' . $qgaSocket . ',server=on,wait=off',
            '-device',  'virtio-serial-pci',
            '-device',  'virtserialport,chardev=qga0,name=org.qemu.guest_agent.0'
        );
```

Points d'attention :

- `/run/remotelabz` doit exister et appartenir au service. Deux options :
  un `tmpfiles.d` (`d /run/remotelabz 0750 root root -`) ou un `mkdir` au
  démarrage du worker (le `mkdir` ci-dessus est un filet de sécurité). Le service
  `remotelabz-worker.service` tourne en root : permissions `0750` suffisent.
- **Rétroactivité** : les VM **déjà lancées** avant la mise à jour n'ont pas le
  canal → elles retombent sur le **repli ARP/DHCP** (§6.4) jusqu'au prochain
  démarrage.
- **Images** : `qemu-guest-agent` doit être installé dans l'OS invité. À
  documenter dans `docs/` et à intégrer aux images de référence.
- Le bucket `agent` est accepté par `qemu_start()` : la boucle parcourt toutes les
  clés de `$parameters`, aucune liste blanche.

### 6.2 `src/Service/Instance/QemuGuestAgentClient.php` (créer)

Le canal QGA parle JSON-RPC ligne à ligne sur le socket Unix. L'agent peut
émettre des **évènements** avant la réponse : boucler jusqu'à lire une ligne
contenant `"return"` ou `"error"`.

```php
<?php

namespace App\Service\Instance;

use Psr\Log\LoggerInterface;

class QemuGuestAgentClient
{
    public function __construct(private LoggerInterface $logger) {}

    /**
     * @return array<int, array{name:string, mac:?string, ipv4:string[], ipv6:string[]}>|null
     *         null si le socket est absent / l'agent ne répond pas.
     */
    public function getInterfaces(string $uuid, float $timeout = 2.0): ?array
    {
        $path = '/run/remotelabz/qga-' . $uuid . '.sock';
        if (!file_exists($path)) {
            return null;
        }

        $sock = @stream_socket_client('unix://' . $path, $errno, $errstr, $timeout);
        if (!$sock) {
            $this->logger->debug('[QemuGuestAgentClient]::Socket inaccessible', ['instance' => $uuid, 'error' => $errstr]);
            return null;
        }
        stream_set_timeout($sock, (int) $timeout);

        fwrite($sock, json_encode(['execute' => 'guest-network-get-interfaces']) . "\n");

        $deadline = microtime(true) + $timeout;
        $result = null;
        while (microtime(true) < $deadline) {
            $line = fgets($sock);
            if ($line === false) {
                break;
            }
            $data = json_decode(trim($line), true);
            if (isset($data['return'])) {
                $result = $data['return'];
                break;
            }
        }
        fclose($sock);

        if (!is_array($result)) {
            return null;
        }

        $interfaces = [];
        foreach ($result as $iface) {
            $ipv4 = [];
            $ipv6 = [];
            foreach (($iface['ip-addresses'] ?? []) as $addr) {
                $ip = $addr['ip-address'] ?? null;
                if ($ip === null) {
                    continue;
                }
                if (($addr['ip-address-type'] ?? '') === 'ipv4' && $ip !== '127.0.0.1') {
                    $ipv4[] = $ip;
                } elseif (($addr['ip-address-type'] ?? '') === 'ipv6'
                    && $ip !== '::1' && !str_starts_with(strtolower($ip), 'fe80:')) {
                    $ipv6[] = $ip;
                }
            }
            $interfaces[] = [
                'name' => $iface['name'] ?? '',
                'mac'  => isset($iface['hardware-address'])
                    ? strtolower($iface['hardware-address']) : null,
                'ipv4' => $ipv4,
                'ipv6' => $ipv6,
            ];
        }

        return $interfaces;
    }
}
```

### 6.3 `src/Service/Instance/DeviceIpResolver.php` (créer)

Service dédié (garde `InstanceManager` — déjà 6270 lignes — plus léger). Il
contient la fonction utilitaire `resolveDeviceInstanceIps($deviceInstance, $bridgeName)`
des deux plans.

```php
<?php

namespace App\Service\Instance;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class DeviceIpResolver
{
    public function __construct(
        private QemuGuestAgentClient $qga,
        private LoggerInterface $logger
    ) {}

    /**
     * Résout les IP d'une DeviceInstance décodée depuis le descripteur "worker".
     *
     * @param array  $deviceInstance fragment deviceInstances[i] du descripteur
     * @param string $bridgeName     pont OVS du lab (LabInstance::getBridgeName())
     *
     * @return array<int, array{name:string, mac:?string, ipv4:string[], ipv6:string[]}>
     */
    public function resolveDeviceInstanceIps(array $deviceInstance, string $bridgeName): array
    {
        $hypervisor = strtolower($deviceInstance['device']['hypervisor']['name'] ?? '');
        $uuid = (string) ($deviceInstance['uuid'] ?? '');

        try {
            if ($hypervisor === 'lxc') {
                return $this->collectLxc($uuid, $deviceInstance);
            }
            if ($hypervisor === 'qemu') {
                return $this->collectQemu($uuid, $bridgeName, $deviceInstance);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[DeviceIpResolver]::Échec de résolution d\'IP', [
                'instance' => $uuid,
                'error' => $e->getMessage(),
            ]);
        }

        return []; // natif / switch : pas d'IP d'instance
    }

    /** Conteneur : une commande par conteneur, sans parcours global (pas de lxc-ls -f). */
    private function collectLxc(string $uuid, array $deviceInstance): array
    {
        $process = new Process(['lxc-info', '-n', $uuid, '-iH']);
        $process->run();
        if (!$process->isSuccessful()) {
            return [];
        }

        $ipv4 = [];
        $ipv6 = [];
        foreach (preg_split('/\R/', trim($process->getOutput())) as $line) {
            $ip = trim($line);
            if ($ip === '') {
                continue;
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ipv4[] = $ip;
            } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
                && !str_starts_with(strtolower($ip), 'fe80:')) {
                $ipv6[] = $ip;
            }
        }

        return $this->zipWithMacs($deviceInstance, $ipv4, $ipv6);
    }

    /** VM : QGA d'abord, puis repli ARP / bail DHCP par MAC. */
    private function collectQemu(string $uuid, string $bridgeName, array $deviceInstance): array
    {
        $interfaces = $this->qga->getInterfaces($uuid);
        if ($interfaces !== null && count($interfaces) > 0) {
            return $interfaces;
        }

        // Repli 1 : table ARP du pont, filtrée par MAC connue
        $byMac = $this->neighByMac($bridgeName);

        // Repli 2 : baux DHCP (Kea memfile)
        if (empty($byMac)) {
            $byMac = $this->leasesByMac();
        }

        $result = [];
        foreach ($deviceInstance['networkInterfaceInstances'] ?? [] as $nic) {
            $mac = isset($nic['macAddress']) ? strtolower($nic['macAddress']) : null;
            $ip = ($mac !== null && isset($byMac[$mac])) ? $byMac[$mac] : null;
            $result[] = [
                'name' => $nic['networkInterface']['name'] ?? '',
                'mac'  => $mac,
                'ipv4' => $ip !== null ? [$ip] : [],
                'ipv6' => [],
            ];
        }

        return $result;
    }

    /** @return array<string,string> MAC (minuscule) => IPv4 */
    private function neighByMac(string $bridgeName): array
    {
        $process = new Process(['ip', '-4', 'neigh', 'show', 'dev', $bridgeName]);
        $process->run();
        if (!$process->isSuccessful()) {
            return [];
        }
        $map = [];
        foreach (preg_split('/\R/', $process->getOutput()) as $line) {
            if (preg_match('/^(\d+\.\d+\.\d+\.\d+)\s+.*lladdr\s+([0-9a-f:]+)/i', trim($line), $m)) {
                $map[strtolower($m[2])] = $m[1];
            }
        }
        return $map;
    }

    /** @return array<string,string> MAC (minuscule) => IPv4 (baux Kea, memfile CSV) */
    private function leasesByMac(): array
    {
        $file = '/var/lib/kea/kea-leases4.csv';
        if (!is_readable($file)) {
            return [];
        }
        $map = [];
        // Format memfile : address,hwaddr,client_id,valid_lifetime,expire,subnet_id,...,state
        // (la MAC est le 2e champ ; on ignore les baux expirés/released si l'état est exploitable)
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $cols = str_getcsv($line);
            if (count($cols) < 2 || !filter_var($cols[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                continue;
            }
            $map[strtolower($cols[1])] = $cols[0];
        }
        return $map;
    }

    private function zipWithMacs(array $deviceInstance, array $ipv4, array $ipv6): array
    {
        $nics = $deviceInstance['networkInterfaceInstances'] ?? [];
        $result = [];
        $count = max(count($nics), 1);
        for ($i = 0; $i < $count; $i++) {
            $result[] = [
                'name' => $nics[$i]['networkInterface']['name'] ?? ('eth' . $i),
                'mac'  => isset($nics[$i]['macAddress']) ? strtolower($nics[$i]['macAddress']) : null,
                // lxc-info ne rend pas la MAC : on rattache les IP au premier NIC
                'ipv4' => $i === 0 ? $ipv4 : [],
                'ipv6' => $i === 0 ? $ipv6 : [],
            ];
        }
        return $result;
    }
}
```

> Le repli « bail dnsmasq » (conteneur « Service » : `/var/lib/lxc/<uuid>/rootfs/var/lib/misc/dnsmasq.leases`)
> est **optionnel** et n'est utile que pour les LXC sans IP fixe : il est ignoré ici
> car `lxc-info -iH` couvre déjà ce cas pour les conteneurs.

### 6.4 Handler worker — `src/MessageHandler/DeviceInfoRequestMessageHandler.php` (créer)

Modèle : `SecurityMessageHandler` (handler simple, `MessageHandlerInterface`).

```php
<?php

namespace App\MessageHandler;

use App\Service\Instance\DeviceIpResolver;
use Psr\Log\LoggerInterface;
use Remotelabz\Message\Message\DeviceInfoMessage;
use Remotelabz\Message\Message\DeviceInfoRequestMessage;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class DeviceInfoRequestMessageHandler implements MessageHandlerInterface
{
    public function __construct(
        private DeviceIpResolver $resolver,
        private MessageBusInterface $bus,
        private LoggerInterface $logger
    ) {}

    public function __invoke(DeviceInfoRequestMessage $message): void
    {
        $uuid = $message->getUuid();
        $requestId = $message->getRequestId();

        try {
            $labInstance = json_decode($message->getContent(), true, 4096, JSON_OBJECT_AS_ARRAY);
            if (!is_array($labInstance)) {
                throw new \InvalidArgumentException('Invalid JSON descriptor');
            }

            $deviceInstance = null;
            foreach (($labInstance['deviceInstances'] ?? []) as $candidate) {
                if (($candidate['uuid'] ?? null) === $uuid) {
                    $deviceInstance = $candidate;
                    break;
                }
            }
            if ($deviceInstance === null) {
                throw new \RuntimeException('Device instance not found in descriptor');
            }

            $bridgeName = (string) ($labInstance['bridgeName'] ?? '');
            $ips = $this->resolver->resolveDeviceInstanceIps($deviceInstance, $bridgeName);

            $info = ['ips' => $ips, 'collectedAt' => time()];
        } catch (\Throwable $e) {
            // Toujours répondre, y compris en erreur : sinon le verrou front expire
            // sans que l'UI ne voie jamais d'IP ni d'erreur.
            $this->logger->error('[DeviceInfoRequestMessageHandler]::' . $e->getMessage(), ['instance' => $uuid]);
            $this->bus->dispatch(new DeviceInfoMessage($uuid, $requestId, [], $e->getMessage()));
            return;
        }

        $this->logger->debug('[DeviceInfoRequestMessageHandler]::IP collectées', [
            'instance' => $uuid,
            'ips' => array_column($ips, 'ipv4'),
        ]);
        $this->bus->dispatch(new DeviceInfoMessage($uuid, $requestId, $info));
    }
}
```

Points d'attention :

- **Toujours répondre**, même en erreur (`catch \Throwable`) : c'est le
  contre-pied du `start-resync-state-started.md` §5 (ne jamais laisser le front
  sans réponse) ;
- nouveau service `DeviceIpResolver` + `QemuGuestAgentClient` auto-câblés par
  autowiring (`config/services.yaml` : `App\:` avec `autowire: true`), rien à
  déclarer ;
- la réponse part sur le transport `front` (routage §5.2).

---

## 7. Front (`/home/florent/remotelabz`)

### 7.1 Pool Redis dédié — `config/packages/cache.yaml`

Ajouter un pool à côté de `lab_placement` (l. 21-23) :

```yaml
        pools:
            lab_placement:
                adapter: cache.adapter.redis
            device_info:                       # ← nouveau
                adapter: cache.adapter.redis
```

`default_redis_provider: '%env(REDIS_DSN)%'` est déjà défini (l. 18) ;
`REDIS_DSN=redis://127.0.0.1:6379` est déjà dans `.env`/`.env.local`.

### 7.2 `src/Service/Instance/DeviceInfoCache.php` (créer)

Sur le modèle de `src/Service/Worker/LabPlacementCache.php` : `AdapterInterface`
injecté, **tolérance aux pannes** (une panne Redis ne doit jamais casser le
polling ; on log un warning et on renvoie null).

| Clé | Contenu | TTL |
|---|---|---|
| `device_info.<deviceInstanceUuid>` | `['ips' => [...], 'fetchedAt' => int, 'workerIp' => string]` | 12 h (12 * 3600) |
| `device_info_pending.<deviceInstanceUuid>` | verrou anti-spam (`requestId`) | 10 s |

```php
<?php

namespace App\Service\Instance;

use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;

class DeviceInfoCache
{
    private const PREFIX  = 'device_info.';
    private const PENDING = 'device_info_pending.';
    private const TTL     = 43200; // 12 h
    private const LOCK_TTL = 10;   // s

    public function __construct(private AdapterInterface $pool, private LoggerInterface $logger) {}

    /** @return array{ips:array, fetchedAt:int, workerIp:string}|null */
    public function get(string $uuid): ?array
    {
        try {
            $item = $this->pool->getItem(self::PREFIX . $uuid);
            return $item->isHit() ? $item->get() : null;
        } catch (\Throwable $e) {
            $this->logger->warning('[DeviceInfoCache]::cache indisponible : ' . $e->getMessage());
            return null;
        }
    }

    /** @param array<int,string> $uuids @return array<string,array> indexé par uuid */
    public function getMany(array $uuids): array
    {
        $out = [];
        foreach ($uuids as $uuid) {
            $value = $this->get($uuid);
            if ($value !== null) {
                $out[$uuid] = $value;
            }
        }
        return $out;
    }

    public function store(string $uuid, array $info, string $workerIp): void
    {
        try {
            $item = $this->pool->getItem(self::PREFIX . $uuid);
            $item->set([
                'ips'       => $info['ips'] ?? [],
                'fetchedAt' => time(),
                'workerIp'  => $workerIp,
            ]);
            $item->expiresAfter(self::TTL);
            $this->pool->save($item);
        } catch (\Throwable $e) {
            $this->logger->warning('[DeviceInfoCache]::écriture impossible : ' . $e->getMessage());
        }
    }

    public function forget(string $uuid): void
    {
        try {
            $this->pool->deleteItem(self::PREFIX . $uuid);
        } catch (\Throwable $e) {
            $this->logger->warning('[DeviceInfoCache]::suppression impossible : ' . $e->getMessage());
        }
    }

    /** @return string|null requestId si le verrou a été posé, null si une requête est déjà en vol */
    public function lockAndGetRequestId(string $uuid): ?string
    {
        try {
            $key = self::PENDING . $uuid;
            $item = $this->pool->getItem($key);
            if ($item->isHit()) {
                return null; // déjà en vol
            }
            $requestId = bin2hex(random_bytes(16));
            $item->set($requestId);
            $item->expiresAfter(self::LOCK_TTL);
            $this->pool->save($item);
            return $requestId;
        } catch (\Throwable $e) {
            $this->logger->warning('[DeviceInfoCache]::verrou impossible : ' . $e->getMessage());
            return null;
        }
    }

    public function release(string $uuid, string $requestId): void
    {
        try {
            $key = self::PENDING . $uuid;
            $item = $this->pool->getItem($key);
            if ($item->isHit() && $item->get() === $requestId) {
                $this->pool->deleteItem($key);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[DeviceInfoCache]::libération impossible : ' . $e->getMessage());
        }
    }

    public function isStale(string $uuid, int $maxAge = 600): bool
    {
        $info = $this->get($uuid);
        if ($info === null || !isset($info['fetchedAt'])) {
            return true;
        }
        return (time() - (int) $info['fetchedAt']) > $maxAge;
    }
}
```

Câblage `config/services.yaml` (à côté de `LabPlacementCache`, l. 276-278) :

```yaml
    App\Service\Instance\DeviceInfoCache:
        arguments:
            $pool: '@device_info'
            $logger: '@logger'
```

### 7.3 `src/MessageHandler/DeviceInfoMessageHandler.php` (créer)

Consommé par `messenger:consume front`. Il écrit le cache et **libère le verrou**.

```php
<?php

namespace App\MessageHandler;

use App\Repository\DeviceInstanceRepository;
use App\Service\Instance\DeviceInfoCache;
use Psr\Log\LoggerInterface;
use Remotelabz\Message\Message\DeviceInfoMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DeviceInfoMessageHandler
{
    public function __construct(
        private DeviceInfoCache $cache,
        private DeviceInstanceRepository $deviceInstanceRepository,
        private LoggerInterface $logger
    ) {}

    public function __invoke(DeviceInfoMessage $message): void
    {
        $uuid = $message->getUuid();

        if ($message->getError() !== null) {
            $this->logger->warning('[DeviceInfoMessageHandler]::' . $message->getError(), ['instance' => $uuid]);
            $this->cache->release($uuid, $message->getRequestId()); // autoriser un nouvel essai
            return;
        }

        $deviceInstance = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid]);
        $workerIp = $deviceInstance?->getLabInstance()?->getWorkerIp() ?? '';

        $this->cache->store($uuid, $message->getInfo(), $workerIp);
        $this->cache->release($uuid, $message->getRequestId());

        // Informations recues (interfaces, IP) tracees en debug.
        $this->logger->debug('[DeviceInfoMessageHandler]::IP recue', [
            'instance' => $uuid,
            'workerIp' => $workerIp,
            'device_info' => $message->getInfo(),
        ]);
    }
}
```

### 7.4 Invalidation aux transitions d'état

Sans BDD, le cache doit être purgé quand l'IP n'est plus valable.

- `src/MessageHandler/InstanceStateMessageHandler.php` (l. 172) : injecter
  `DeviceInfoCache` (constructeur l. 45-72) et, sur
  `STATE_STOPPED` / `STATE_DELETED` / `STATE_ERROR` (device), appeler
  `$this->deviceInfoCache->forget($uuid)` — à ajouter dans le `switch` de la
  branche « pas d'erreur » (l. 412) et dans la branche `STATE_ERROR`.
- `src/Service/Instance/InstanceManager.php`, `start()` (l. 331) : après
  `setState(STARTING)` (l. 358), appeler `$this->deviceInfoCache->forget($uuid)`
  → le tick suivant du timer (≤ 1 min) collecte l'IP sans attendre la péremption.
  (Injecter `DeviceInfoCache` dans le constructeur, l. 100-127.)

> `forget()` sur `started` n'est pas nécessaire : l'entrée précédente a déjà été
> purgée au stop/start. En cas de reset, `forget()` au démarrage garantit une
> recollecte.

### 7.5 Commande + timer systemd front

`src/Command/CollectInstanceIpsCommand.php` (créer) — modèle
`ReconcileStartingInstancesCommand` (attribut `#[AsCommand]`, `--dry-run`,
`-v`).

```php
#[AsCommand(
    name: 'app:instance:collect-ips',
    description: 'Demande aux workers l\'IP des instances démarrées et rafraîchit le cache Redis',
)]
```

Algorithme :

1. Récupérer les `DeviceInstance` en état `started` (voir repository ci-dessous),
   avec `labInstance.workerIp` non nul ;
2. Regrouper par `LabInstance` pour reconstruire **un seul descripteur par lab**
   (sérialisation JMS, groupe `worker` — mêmes réglages que
   `LabLaunchRequestMessageHandler` l. 139-140 / `WorkerHandshakeMessageHandler` l. 79-81) ;
3. Pour chaque `DeviceInstance` : si `isStale($uuid, $maxAge)` **et**
   `lockAndGetRequestId($uuid)` renvoie un `requestId`, construire
   `DeviceInfoRequestMessage($labJson, $uuid, $requestId)` et
   `$this->bus->dispatch($msg, [new AmqpStamp($workerIp, AMQP_NOPARAM, [])])` ;
   en cas d'échec du dispatch, `release($uuid, $requestId)` pour ne pas laisser
   de verrou orphelin ;
4. Ne rien faire pour les instances dont le cache est frais (< `$maxAge`) ou déjà
   en vol (verrou posé).

Ajouter au repository `src/Repository/DeviceInstanceRepository.php` une méthode
`findStartedWithWorker()` (DQL `di.state = 'started' AND li.workerIp IS NOT NULL`,
jointure `di.labInstance li`).

Paramètre configurable : `INSTANCE_IP_MAX_AGE` (secondes, défaut `600` = 10 min)
dans `.env`/`.env.local`, exposé en paramètre `app.instance_ip.max_age` et injecté
`$deviceInfoMaxAge` dans la commande (`config/services.yaml`). L'option
`--max-age=N` permet de surcharger ponctuellement la valeur.

Unités systemd (modèle : `bin/systemd/remotelabz-instance-reconcile.{service,timer}`) :

`bin/systemd/remotelabz-instance-ips.service`

```ini
[Unit]
Description=RemoteLabz Collect Instance IPs Service
After=network.target

[Service]
Type=oneshot
User=www-data
Group=www-data
WorkingDirectory=/opt/remotelabz
ExecStart=/usr/bin/php /opt/remotelabz/bin/console app:instance:collect-ips --no-interaction
StandardOutput=journal
StandardError=journal
TimeoutStartSec=5min
PrivateTmp=true
```

`bin/systemd/remotelabz-instance-ips.timer`

```ini
[Unit]
Description=RemoteLabz Collect Instance IPs Timer
Requires=remotelabz-instance-ips.service

[Timer]
OnBootSec=2min
OnUnitActiveSec=1min
Unit=remotelabz-instance-ips.service

[Install]
WantedBy=timers.target
```

> La collecte **par minute** + péremption **10 min** réalise exactement le besoin :
> première IP dans la minute qui suit un `start` (cache vidé par §7.4), puis
> rafraîchissement toutes les 10 min. La fréquence est modifiable dans le timer
> (`OnUnitActiveSec`) et la péremption par `INSTANCE_IP_MAX_AGE`.
> Mettre à jour `bin/systemd/README.md` (tableau des services) et
> `lib/remotelabz/Installer.php` (symlinks + `enableServices()`).

### 7.6 Exposition dans le JSON

Deux points d'injection, sans modifier le schéma.

#### a) Éditeur — `DeviceController::indexActionTest()` (l. 245)

Injecter `DeviceInfoCache` dans le constructeur du contrôleur (l. 80-118) et, dans
la construction de `$data[$device->getId()]` (l. 340), ajouter :

```php
            // $ips = interfaces brutes du cache : [{name, mac, ipv4, ipv6}]
            $ips = [];
            $ip = null;
            if ($nodeData['edition'] == 0 && isset($uuid)) {
                $cached = $this->deviceInfoCache->get($uuid);
                if (null !== $cached) {
                    $ips = $cached['ips'] ?? [];
                    $ip = $this->firstIp($ips);
                }
            }
```

`$ip`/`$ips` sont ajoutés aux deux tableaux de réponse
(`$data[$device->getId()]` et `$data`). Idem dans `showActionTest()` (l. 512) :
l'IP n'est injectée que si `isset($uuid)`.

#### b) React (`api_get_lab_instance`) — listener JMS

`fetchByUuidAction()` (l. 837), `fetchLabInstanceByUserAction()` (l. 858),
`fetchLabInstanceByGuestAction()` (l. 883), `fetchLabInstanceByGroupAction()`
(l. 909) et `apiIndexAction()` (l. 182) sérialisent tous du JMS avec les groupes
`api_get_lab_instance` / `api_get_device_instance`. Plutôt que de dupliquer une
injection dans chaque méthode, créer un **listener JMS `post_serialize` sur
`DeviceInstance`** (modèle : `src/EventListener/LabInstanceSerializationListener.php`) :

```php
<?php

namespace App\EventListener;

use App\Entity\DeviceInstance;
use App\Service\Instance\DeviceInfoCache;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface as JMSEventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\Metadata\StaticPropertyMetadata;

class DeviceInstanceSerializationListener implements JMSEventSubscriberInterface
{
    public function __construct(private DeviceInfoCache $cache) {}

    public static function getSubscribedEvents(): array
    {
        return [[
            'event' => 'serializer.post_serialize',
            'class' => DeviceInstance::class,
            'method' => 'onPostSerialize',
        ]];
    }

    public function onPostSerialize(ObjectEvent $event): void
    {
        $context = $event->getContext();
        if (!method_exists($context, 'hasAttribute') || !$context->hasAttribute('groups')) {
            return;
        }
        $groups = (array) $context->getAttribute('groups');

        // Le worker et le sandbox n'ont pas besoin de l'IP : ne pas alourdir leur payload
        if (in_array('worker', $groups, true) || in_array('sandbox', $groups, true)) {
            return;
        }
        if (!in_array('api_get_lab_instance', $groups, true)
            && !in_array('api_get_device_instance', $groups, true)) {
            return;
        }

        /** @var DeviceInstance $deviceInstance */
        $deviceInstance = $event->getObject();
        $cached = $this->cache->get($deviceInstance->getUuid());
        $ips = $cached['ips'] ?? [];

        // visitProperty() prend la VALEUR en 2eme argument (le
        // StaticPropertyMetadata ne porte que le nom et les groupes).
        $firstIp = null;
        foreach ($ips as $interface) {
            if (!empty($interface['ipv4'][0])) {
                $firstIp = $interface['ipv4'][0];
                break;
            }
        }

        $visitor = $event->getVisitor();
        $visitor->visitProperty(new StaticPropertyMetadata(
            DeviceInstance::class,
            'ips',
            $ips,
            ['api_get_lab_instance', 'api_get_device_instance']
        ), $ips);
        $visitor->visitProperty(new StaticPropertyMetadata(
            DeviceInstance::class,
            'ip',
            $firstIp,
            ['api_get_lab_instance', 'api_get_device_instance']
        ), $firstIp);
    }
}
```

> Les listeners JMS du dossier `src/EventListener` sont auto-configurés (le bundle
> JMS auto-tague les `EventSubscriberInterface`) : rien à ajouter dans
> `config/services.yaml` (comme `LabInstanceSerializationListener`).

Forme ajoutée par instance :

```json
"ips": [
  { "name": "eth0", "mac": "52:54:00:aa:bb:01", "ipv4": ["10.11.2.34"], "ipv6": [] }
],
"ip": "10.11.2.34"
```

> **Ne pas** chercher à mettre l'IP dans `GET /api/devices` (templates sans lab) ni
> dans `GET /api/devices/{id}` (appel unique au montage) : l'IP est par instance
> (§2.2).

### 7.7 Affichage React (ligne du device)

L'IP est affichée sur la ligne de chaque device, sous l'UUID, dans le même style
que les autres informations atténuées (`text-muted small`), avec une info-bulle
listant toutes les adresses (`ips`, IPv4 puis IPv6) :

- `assets/js/components/Instances/InstanceListItem.js` (page « Instances » du
  lab, rafraîchie toutes les 10 s par `InstanceManager`) : div `IP: x` alimenté
  par `instance.ip` ;
- `assets/js/components/Instances/OptimizedInstanceList.js` (modal « Details »
  de la liste de toutes les instances, rafraîchie toutes les 5 s) : même div
  alimenté par `deviceInstance.ip`.

Le div n'apparaît que lorsque l'IP est connue (worker ayant répondu) : au
démarrage d'une VM, il s'affiche tout seul au rafraîchissement suivant, sans
placeholder « en attente ».

---

## 8. Format de donnée échangé

`DeviceInfoMessage.info` (sérialisé par Messenger, PHP natif) :

```json
{
  "ips": [
    {
      "name": "eth0",
      "mac": "52:54:00:aa:bb:01",
      "ipv4": ["10.11.2.34"],
      "ipv6": []
    }
  ],
  "collectedAt": 1760000000
}
```

Valeur stockée en Redis (`device_info.<uuid>`) :

```json
{
  "ips": [ { "name": "eth0", "mac": "52:54:00:aa:bb:01", "ipv4": ["10.11.2.34"], "ipv6": [] } ],
  "fetchedAt": 1760000000,
  "workerIp": "192.168.11.132"
}
```

---

## 9. Cycle complet

1. Le timer front exécute `app:instance:collect-ips` (toutes les minutes).
2. Pour chaque `DeviceInstance` `started` : cache Redis absent ou périmé
   (> `INSTANCE_IP_MAX_AGE`) **et** aucune requête en vol → `lockAndGetRequestId()`
   pose un verrou (10 s) puis `dispatch(DeviceInfoRequestMessage)`
   (`AmqpStamp(<workerIp>)`).
3. Le worker reçoit la demande sur `messages_workerN`, résout l'IP
   (`DeviceIpResolver` : QGA → repli ARP/bail pour QEMU ; `lxc-info -iH` pour LXC)
   et répond `DeviceInfoMessage` (toujours, même en erreur).
4. `DeviceInfoMessageHandler` (front, consumer `front`) écrit `device_info.<uuid>`
   et libère le verrou.
5. Les endpoints de polling (`POST /api/labs/{id}/nodes`,
   `GET /api/instances/...`) renvoient `ips`/`ip` depuis le cache, sans contacter
   le worker.
6. Sur `stopped`/`deleted`/`error` : `forget(uuid)` ; sur `start` :
   `forget(uuid)` → recollecte dans la minute.
7. L'UI (pages instances / modal « Details ») affiche l'IP au rafraîchissement
   suivant (5-10 s), sans requête supplémentaire vers le worker.

---

## 10. Recette / tests

1. **VM QEMU avec agent** : lancer une VM, vérifier `ls -l /run/remotelabz/qga-<uuid>.sock`,
   puis (worker) une résolution manuelle ; côté front, au premier tick après le
   `start`, l'IP apparaît (≤ 1 min) puis n'est plus redemandée pendant 10 min.
2. **VM QEMU sans agent** : l'IP est obtenue par le repli ARP/Kea (si la VM émet
   du trafic / a un bail) ; sinon `ips: []` sans erreur.
3. **LXC** : `lxc-info -n <uuid> -iH` en ligne de commande ; vérifier
   `device_info.<uuid>` dans Redis.
4. **Anti-spam** : une seule `DeviceInfoRequestMessage` traitée par fenêtre de 10 s
   pour un même uuid (verrou) ; logs worker.
5. **Worker arrêté** : l'UI continue de recevoir les autres champs, `ips` reste
   vide/obsolète, aucun blocage HTTP ; le verrou expire et un nouvel essai a lieu.
6. **Multi-NIC** : une entrée par interface (nom + MAC + IP) ; `ip` = 1re IPv4.
7. **stop → start** : l'IP disparaît du cache puis est recollectée.
8. **Panne Redis** (`REDIS_DSN` injoignable) : le polling continue, warning loggé.
9. **Handshake worker** (redémarrage) : prochaine collecte OK (aucun état worker).
10. **Tests unitaires** : parsing sortie `lxc-info -iH` ; parsing réponse QGA ;
    `ip neigh` par MAC ; lecture bail Kea CSV ; dédup/verrou du cache.

---

## 11. Checklist d'implémentation

> Statut : **implémenté** sur la branche `Get-IP-From-Worker` des trois dépôts
> (cases cochées ci-dessous).

**Bundle** (`/home/florent/remotelabz-message-bundle`)
- [x] `Message/DeviceInfoRequestMessage.php` (créer)
- [x] `Message/DeviceInfoMessage.php` (créer)
- [x] `Tests/*` (la version reste portée par les tags Git : créer le tag `1.0.10`)

**Worker** (`/home/florent/remotelabz-worker`)
- [x] `InstanceManager::create_qemu_device()` : bucket `agent` + args QGA
- [x] `/run/remotelabz` (`config/tmpfiles/remotelabz-worker.conf` + `bin/install.sh`)
- [x] `src/Service/Instance/QemuGuestAgentClient.php` (créer)
- [x] `src/Service/Instance/DeviceIpResolver.php` (créer : LXC `lxc-info -iH`, QGA, repli ARP/Kea)
- [x] `src/MessageHandler/DeviceInfoRequestMessageHandler.php` (créer, répond **toujours**)
- [x] `config/packages/messenger.yaml` : `DeviceInfoMessage → front`
- [x] `docs/qemu-guest-agent.md` : QGA + `qemu-guest-agent` dans les images

**Front** (`/home/florent/remotelabz`)
- [x] `config/packages/messenger.yaml` : `DeviceInfoRequestMessage → worker`, `DeviceInfoMessage → front`
- [x] `config/packages/cache.yaml` : pool `device_info`
- [x] `config/services.yaml` : `DeviceInfoCache` (`$pool: '@device_info'`) + `app.instance_ip.max_age`
- [x] `src/Service/Instance/DeviceInfoCache.php` (créer)
- [x] `src/MessageHandler/DeviceInfoMessageHandler.php` (créer)
- [x] `src/MessageHandler/InstanceStateMessageHandler.php` : `forget()` sur stopped/deleted/error/start
- [x] `src/Service/Instance/InstanceManager.php` `start()` : `forget()` sur start
- [x] `src/Command/CollectInstanceIpsCommand.php` (créer)
- [x] `src/Repository/DeviceInstanceRepository.php` : `findStartedWithWorker()`
- [x] `bin/systemd/remotelabz-instance-ips.{service,timer}` (créer) + `bin/systemd/README.md` + `lib/remotelabz/Installer.php`
- [x] `src/Controller/DeviceController.php` : `ips`/`ip` dans `indexActionTest()` (+ `showActionTest()`)
- [x] `src/EventListener/DeviceInstanceSerializationListener.php` (créer, `post_serialize`)
- [x] `assets/js/components/Instances/InstanceListItem.js` : div `IP:` sur la ligne du device
- [x] `assets/js/components/Instances/OptimizedInstanceList.js` : idem dans le modal « Details »
- [x] `src/MessageHandler/DeviceInfoMessageHandler.php` : log debug des informations reçues (`device_info`)
- [x] `.env` : `INSTANCE_IP_MAX_AGE=600`
- [x] `doc/` : ce document, `doc/messenger-rabbitmq.md` (nouveaux messages)
- [x] `tests/Service/IpSerializationTest.php` (créer)

**Déploiement (ordre imposé)**
- [ ] bundle → workers → front (sérialisation PHP native ; un récepteur sans la
      classe échoue au décodage)

---

## 12. Points d'attention / limites

1. **Agent non démarré au moment du `STARTED`** : l'IP arrive via le tick suivant
   (≤ 1 min) ou via le repli ARP ; le div `IP:` de l'UI n'apparaît qu'à ce
   moment-là (pas de placeholder « en attente »).
2. **Images sans `qemu-guest-agent`** : IP `null` → repli ARP/bail ou installation
   de l'agent dans les images de référence.
3. **VM déjà lancées** au moment du déploiement : pas de canal QGA avant
   redémarrage (repli ARP/bail en attendant).
4. **`GET /api/devices`** ne concerne pas les instances : ne pas y chercher l'IP live.
5. **LXC** : `lxc-info -iH` donne les IP vues par le noyau hôte ; pour un conteneur
   sans IP statique (DHCP dnsmasq du conteneur « Service »), l'IP peut être vide si
   le bail n'est pas encore attribué (rattrapé au tick suivant).
6. **Verrou** : garde-fou anti-spam (TTL 10 s), pas un verrou de correction stricte ;
   une réponse tardive après expiration reste valable (elle met simplement à jour
   le cache).
7. **Un seul consumer `front`** (cf. `README-LabPlacement.md`) : ne jamais bloquer un
   endpoint HTTP en attendant la réponse.
8. **Sérialisation** : toute modification d'une classe du bundle doit redéployer
   **bundle → workers → front** ; un `bin/console` front échoue si le routage
   référence une classe absente du bundle.
9. **Charge** : un message par `DeviceInstance` démarré toutes les 10 min ; si une
   installation compte beaucoup d'instances, prévoir de **regrouper par worker**
   (plusieurs `uuid` dans un même message) — le format `info` est déjà extensible,
   mais le couple requête/réponse devrait alors porter une liste d'`uuid`.
10. **Extensibilité** : `DeviceInfoMessage.info` permet d'ajouter uptime, nb de
    NIC, état QEMU/LXC… sans nouveau message.

---

## 13. Annexe — alternatives écartées

- **Option A : l'IP dans `InstanceStateMessage.options`.** Plus économique (aucun
  nouveau message) mais ne rafraîchit l'IP qu'aux transitions d'état, et aurait
  nécessité un champ `DeviceInstance.ips` + migration. Écartée au profit de la
  collecte à la demande + Redis, sans persistance (cf. décision).
- **Option C : HTTP direct front → worker.** Casse le principe « messages
  uniquement », impose N appels HTTP par device et gère mal un worker hors ligne.
  Le HTTP vers `http://<worker>:8081/stats/...` reste réservé au monitoring
  (`WorkerManager::checkWorkersAction()`).
- **Push par un timer worker (`InstanceIpMessage`).** Le worker pousserait
  spontanément les IP ; moins de contrôle côté front, collecte inutile pour les
  instances non observées, et duplication d'état (dédup) côté worker. Écartée au
  profit du timer front + pull à la demande.
