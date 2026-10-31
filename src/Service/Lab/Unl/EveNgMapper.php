<?php

namespace App\Service\Lab\Unl;

use App\Repository\ControlProtocolTypeRepository;
use App\Repository\FlavorRepository;
use App\Repository\HypervisorRepository;
use App\Repository\OperatingSystemRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Applique la table de correspondance EVE-NG -> RemoteLabz
 * (config/eve-ng/templates.yaml + tables d'icones, flavours et protocoles) et
 * convertit la topologie d'un lab EVE-NG en JSON "Lab RemoteLabz" pret pour
 * LabImporter::import().
 *
 * Responsabilites :
 *  - traduire chaque <node> en device RLZ (type, hyperviseur, template, icone,
 *    flavour, systeme d'exploitation, protocoles de controle) ;
 *  - convertir les <network> et <interface> en NetworkInterface.connection
 *    (les interfaces partageant un meme network_id obtiennent la meme
 *    connection) et materialiser les reseaux systeme (nat/pnet/cloud) en
 *    devices "Natif" dedies ;
 *  - convertir les <textobject> en TextObject.
 */
class EveNgMapper
{
    private const EVE_ICON_MAP = [
        'router.png' => 'Router.png',
        'switch l3.png' => 'Switch L3.png',
        'switch.png' => 'Switch.png',
        'desktop.png' => 'Desktop.png',
        'desktop2.png' => 'Desktop2.png',
        'desktop3.png' => 'Desktop3.png',
        'desktop4.png' => 'Desktop4.png',
        'firewall.png' => 'Firewall.png',
        'firewall2.png' => 'Firewall2.png',
        'firewall3.png' => 'Firewall3.png',
        'linux-1.png' => 'Linux.png',
        'cloud.png' => 'Cloud.png',
        'global.png' => 'Cloud.png',
    ];

    private const FALLBACK_ICON = 'Server_Linux.png';

    private LoggerInterface $logger;
    private HypervisorRepository $hypervisorRepository;
    private FlavorRepository $flavorRepository;
    private ControlProtocolTypeRepository $controlProtocolTypeRepository;
    private OperatingSystemRepository $operatingSystemRepository;
    private string $projectDirectory;
    private string $iconsDirectory;

    /** @var array<string, int>|null */
    private ?array $hypervisorIds = null;
    /** @var array<int, array{id: ?int, name: ?string, memory: int}> */
    private array $flavors = [];
    /** @var array<string, int> */
    private array $controlProtocolIds = [];
    /** @var array<int, array<string, mixed>> */
    private array $drivers = [];
    /** @var array<string, mixed> */
    private array $catalogue = [];
    /** @var array<string, bool> */
    private array $availableIcons = [];

    public function __construct(
        LoggerInterface $logger,
        HypervisorRepository $hypervisorRepository,
        FlavorRepository $flavorRepository,
        ControlProtocolTypeRepository $controlProtocolTypeRepository,
        OperatingSystemRepository $operatingSystemRepository,
        string $projectDirectory,
        string $iconsDirectory
    ) {
        $this->logger = $logger;
        $this->hypervisorRepository = $hypervisorRepository;
        $this->flavorRepository = $flavorRepository;
        $this->controlProtocolTypeRepository = $controlProtocolTypeRepository;
        $this->operatingSystemRepository = $operatingSystemRepository;
        $this->projectDirectory = $projectDirectory;
        $this->iconsDirectory = $iconsDirectory;
    }

    /**
     * Construit la liste des images requises par le lab (une entree par
     * identifiant d'image distinct).
     *
     * @return array<int, array{eve_image: string, hypervisor: ?string, arch: string, device_type: string, console: string, cpu: int, ram: int, icon: string, os_hint: string[], applicable: bool, suggested_action: string, used_by: int[]}>
     */
    public function buildRequirements(array $parsed): array
    {
        $requirements = [];

        foreach ($parsed['nodes'] as $node) {
            $entry = $this->catalogueEntry($node['template'], $node['type']);
            $applicable = !$this->isImageLessNode($node, $entry);
            $eveImage = $node['image'];

            if (!$applicable && '' === $eveImage) {
                continue;
            }

            if ('' === $eveImage) {
                continue;
            }

            if (!isset($requirements[$eveImage])) {
                $requirements[$eveImage] = [
                    'eve_image' => $eveImage,
                    // L'hyperviseur est deduit de l'attribut `type` du <node>
                    // (qemu -> qemu, docker -> lxc...) avec repli sur le template.
                    'hypervisor' => $this->hypervisorForNode($node, $entry),
                    'arch' => $this->archForNode($node),
                    // Parametres repris du <node> pour creer un device template
                    // lorsqu'aucune image ne correspond (cf. UnlImporter).
                    'device_type' => $entry['device_type'] ?? 'vm',
                    'console' => (string) ($node['console'] ?? ''),
                    'cpu' => max(1, (int) ($node['cpu'] ?? 1)),
                    'ram' => (int) ($node['ram'] ?? 0),
                    'icon' => $this->resolveIcon((string) ($node['icon'] ?? ''), $entry['icon'] ?? self::FALLBACK_ICON),
                    'os_hint' => $entry['os_hint'] ?? [],
                    'applicable' => $applicable,
                    'suggested_action' => $entry['suggested_action'] ?? 'reuse',
                    'used_by' => [],
                ];
            } elseif ('' === ($requirements[$eveImage]['arch'] ?? '') && '' !== $arch = $this->archForNode($node)) {
                // Complete l'architecture si le premier noeud ne la fournissait pas.
                $requirements[$eveImage]['arch'] = $arch;
            }

            $requirements[$eveImage]['used_by'][] = (int) $node['eve_id'];
        }

        return array_values($requirements);
    }

    /**
     * Drivers RemoteLabz (`config/templates/`) proposes comme device de
     * remplacement pour un noeud sans image (EVE-NG `vpcs`/PC).
     *
     * @return array<int, array{template: string, name: string, type: string, hypervisor: int, description: string}>
     */
    public function deviceCandidates(): array
    {
        $this->loadDrivers();

        $candidates = [];
        foreach ($this->drivers as $driver) {
            $candidates[] = [
                'template' => (string) $driver['template'],
                'name' => (string) $driver['name'],
                'type' => (string) $driver['type'],
                'hypervisor' => (int) $driver['hypervisor'],
                'description' => (string) $driver['description'],
            ];
        }

        usort($candidates, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $candidates;
    }

    /**
     * Noeuds EVE-NG sans image (`vpcs`/PC) requerant le choix d'un device de
     * remplacement, avec le driver retenu par defaut.
     *
     * @param array<int, array{template:string}> $deviceChoices Choix deja exprimes, indexes par `eve_id`
     *
     * @return array<int, array{eve_id: int, name: string, selected: ?string}>
     */
    public function deviceChoices(array $parsed, array $deviceChoices = []): array
    {
        $this->loadCatalogue();
        $this->loadDrivers();
        $this->loadHypervisors();

        $choices = [];
        foreach ($parsed['nodes'] as $node) {
            if (!$this->isVpcsNode($node)) {
                continue;
            }

            $eveId = (int) $node['eve_id'];
            $entry = $this->catalogueEntry($node['template'], $node['type']);

            $selected = $deviceChoices[$eveId]['template'] ?? null;
            if (null === $selected || '' === $selected) {
                $selected = $this->pickDriver(
                    (string) ($entry['device_type'] ?? 'switch'),
                    $this->hypervisorId($this->hypervisorForNode($node, $entry)),
                    $entry['rlz_template'] ?? null
                );
            }

            $choices[] = [
                'eve_id' => $eveId,
                'name' => '' !== $node['name'] ? $node['name'] : sprintf('device-%d', $eveId),
                'selected' => $selected,
            ];
        }

        return $choices;
    }

    /**
     * Convertit la topologie en JSON "Lab RemoteLabz".
     *
     * @param array<string, array{status:string, os_id:?int, os_name:?string, os_image:?string}> $images
     *        Resolution des images, indexee par identifiant EVE-NG.
     * @param array<int, array{template:string}> $deviceChoices
     *        Device de remplacement choisi pour un noeud sans image, indexe par
     *        identifiant de noeud EVE-NG (`eve_id`).
     * @param array<string, array{template?: string, flavor?: array}> $imageOverrides
     *        Surcharges par image EVE-NG : device template cree pour une image
     *        sans correspondance (`template`) et flavour retenue (`flavor`).
     *
     * @return array{labJson: array, devices: array, warnings: string[]}
     */
    public function map(array $parsed, array $images = [], array $deviceChoices = [], array $imageOverrides = []): array
    {
        $warnings = [];
        $this->loadCatalogue();
        $this->loadDrivers();
        $this->loadHypervisors();
        $this->flavors = $this->loadFlavors();

        // --- Connections : un identifiant par <network> ---------------------
        $connections = [];
        $connectionId = 1;
        foreach ($parsed['networks'] as $network) {
            $connections[(int) $network['id']] = $connectionId++;
        }

        $devices = [];
        $usedConnections = [];

        // --- Devices des noeuds ---------------------------------------------
        foreach ($parsed['nodes'] as $node) {
            $entry = $this->catalogueEntry($node['template'], $node['type']);
            $deviceChoice = $deviceChoices[(int) $node['eve_id']]['template'] ?? null;
            $imageOverride = $imageOverrides[$node['image']] ?? [];
            $device = $this->mapNode($node, $entry, $images, $connections, $usedConnections, $deviceChoice, $imageOverride);
            $devices[] = $device;
        }

        // --- Devices "Internet" pour les reseaux systeme --------------------
        foreach ($parsed['networks'] as $network) {
            if (!$this->isSystemNetwork($network)) {
                continue;
            }

            $connections[$network['id']] = $connections[$network['id']] ?? $connectionId++;
            $devices[] = $this->mapSystemNetwork($network, $connections[(int) $network['id']]);
        }

        // --- Annotations ----------------------------------------------------
        $textobjects = [];
        foreach ($parsed['textobjects'] as $textobject) {
            // TextObject.data is a LONGTEXT: the full EVE-NG annotation HTML is
            // kept, no matter its size.
            $textobjects[] = [
                'name' => '' !== $textobject['name'] ? $textobject['name'] : 'text',
                'type' => 'text',
                'data' => (string) $textobject['data'],
            ];
        }

        $labName = $this->sanitizeLabName($parsed['lab']['name'] ?? 'EVE-NG lab');

        $labJson = [
            'name' => $labName,
            'shortDescription' => '',
            'description' => '',
            'virtuality' => 1,
            'isTemplate' => false,
            'version' => '1',
            'scripttimeout' => $parsed['lab']['scripttimeout'] ?? 300,
            'hasTimer' => false,
            'devices' => $devices,
            'textobjects' => $textobjects,
        ];

        return [
            'labJson' => $labJson,
            'devices' => $this->summarizeDevices($devices),
            'warnings' => $warnings,
            'links' => count($usedConnections),
            'lab' => [
                'name' => $labName,
                'scripttimeout' => $labJson['scripttimeout'],
                'devices' => count($devices),
                'links' => count($usedConnections),
                'textobjects' => count($textobjects),
            ],
        ];
    }

    private function mapNode(array $node, array $entry, array $images, array $connections, array &$usedConnections, ?string $deviceChoice = null, array $imageOverride = []): array
    {
        // An image-less node (EVE-NG vpcs/PC) can be replaced by an arbitrary
        // RemoteLabz device chosen by the user in the review window. A chosen
        // driver fully defines the device (type, hypervisor, OS, control
        // protocol, icon); otherwise the catalogue mapping is used.
        $driver = (null !== $deviceChoice && '' !== $deviceChoice)
            ? $this->driverByTemplate($deviceChoice)
            : null;

        if (null !== $driver) {
            $hypervisorId = (int) $driver['hypervisor'];
            $deviceType = (string) $driver['type'];
            $template = (string) $driver['template'];
            $icon = $this->resolveIcon('', $driver['icon'] ?? ($entry['icon'] ?? self::FALLBACK_ICON));
            $osJson = $this->operatingSystemJsonFromDriver($driver);
            $controlProtocols = $this->controlProtocolsFromDriver($driver, $deviceType);
            $networkInterfaceTemplate = (string) ($driver['network_interface_template'] ?? 'eth');
        } else {
            $hypervisorId = $this->hypervisorId($this->hypervisorForNode($node, $entry));
            $deviceType = $entry['device_type'] ?? 'vm';
            $osId = $images[$node['image']]['os_id'] ?? null;
            // Une image sans correspondance a recu un device template dedie au
            // commit : il prime sur la selection automatique d'un driver.
            $template = $imageOverride['template']
                ?? $this->pickDriver($deviceType, $hypervisorId, $entry['rlz_template'] ?? null, $osId);
            $icon = $this->resolveIcon($node['icon'], $entry['icon'] ?? self::FALLBACK_ICON);
            $osJson = $this->operatingSystemJson($node, $entry, $images);
            $controlProtocols = $this->controlProtocols((string) $node['console'], $deviceType);
            $networkInterfaceTemplate = 'eth';
        }

        $flavor = $imageOverride['flavor'] ?? $this->resolveFlavor((int) $node['ram']);

        $interfaces = [];
        $index = 0;
        foreach ($node['interfaces'] as $interface) {
            // On ne garde que les interfaces ethernet rattachees a un reseau.
            if ('ethernet' !== strtolower($interface['type']) && '' !== $interface['type']) {
                continue;
            }
            $networkId = (int) $interface['network_id'];
            if (!isset($connections[$networkId])) {
                continue;
            }

            $connection = $connections[$networkId];
            $usedConnections[$connection] = true;

            $interfaces[] = [
                'name' => 'eth' . $index,
                'type' => 'tap',
                'vlan' => 0,
                'connection' => $connection,
                'connectorType' => 'Straight',
                'connectorLabel' => $interface['name'] ?: null,
                'settingsName' => 'eth' . $index . '_settings',
            ];
            ++$index;
        }

        return [
            'eve_id' => (int) $node['eve_id'],
            'name' => '' !== $node['name'] ? $node['name'] : sprintf('device-%d', $node['eve_id']),
            'brand' => '',
            'model' => '',
            'type' => $deviceType,
            'icon' => $icon,
            'virtuality' => 1,
            'hypervisor' => ['id' => $hypervisorId],
            'nbCpu' => max(1, (int) $node['cpu']),
            'operatingSystem' => $osJson,
            'flavor' => $flavor,
            'editorData' => [
                'x' => (int) $node['left'],
                'y' => (int) $node['top'],
            ],
            'networkInterfaceTemplate' => $networkInterfaceTemplate,
            'template' => $template,
            'controlProtocolTypes' => $controlProtocols,
            'networkInterfaces' => $interfaces,
            'config' => (int) ($node['config'] ?? 0),
            'config_data' => (string) ($node['config_data'] ?? ''),
        ];
    }

    private function mapSystemNetwork(array $network, int $connection): array
    {
        $natifHypervisorId = $this->hypervisorId('natif');
        $template = $this->pickDriver('switch', $natifHypervisorId, null);
        $name = '' !== $network['name'] ? $network['name'] : 'Internet';

        return [
            'eve_id' => null,
            'name' => $name,
            'brand' => 'Natif',
            'model' => '',
            'type' => 'switch',
            'icon' => 'Cloud.png',
            'virtuality' => 1,
            'hypervisor' => ['id' => $natifHypervisorId],
            'nbCpu' => 1,
            'operatingSystem' => [
                'name' => 'Natif',
                'image' => 'Natif',
                'hypervisor' => ['id' => $natifHypervisorId],
            ],
            'flavor' => $this->resolveFlavor(256),
            'editorData' => [
                'x' => (int) $network['left'],
                'y' => (int) $network['top'],
            ],
            'networkInterfaceTemplate' => 'eth',
            'template' => $template,
            'controlProtocolTypes' => $this->controlProtocols('', 'switch'),
            'networkInterfaces' => [[
                'name' => 'eth0',
                'type' => 'tap',
                'vlan' => 0,
                'connection' => $connection,
                'connectorType' => 'Straight',
                'connectorLabel' => null,
                'settingsName' => 'eth0_settings',
            ]],
            'config' => 0,
            'config_data' => '',
        ];
    }

    private function operatingSystemJson(array $node, array $entry, array $images): array
    {
        $eveImage = $node['image'];
        $hypervisor = ['id' => $this->hypervisorId($this->hypervisorForNode($node, $entry))];

        if (isset($images[$eveImage]) && null !== ($images[$eveImage]['os_image'] ?? null)) {
            $osJson = [
                'name' => $images[$eveImage]['os_name'] ?? $eveImage,
                'image' => $images[$eveImage]['os_image'],
                'hypervisor' => $hypervisor,
            ];
        } else {
            // Pas de resolution : on laisse LabImporter creer un OS "placeholder"
            // portant l'identifiant EVE-NG (a corriger plus tard dans l'IHM).
            $image = '' !== $eveImage ? $eveImage : ($entry['os'] ?? 'Natif');
            $osJson = [
                'name' => $image,
                'image' => $image,
                'hypervisor' => $hypervisor,
            ];
        }

        // L'architecture (qemu_arch) n'est portee que par l'OS cree : on la
        // transmet pour que l'OperatingSystem cree depuis l'import soit conforme
        // (x86_64, arm64...) au lieu de tomber sur la valeur par defaut.
        $arch = $this->archForNode($node);
        if ('' !== $arch) {
            $osJson['arch'] = ['name' => $arch];
        }

        return $osJson;
    }

    /**
     * OS JSON d'un device construit depuis un driver choisi : l'OS du driver est
     * resolu en base pour que LabImporter reutilise exactement le meme
     * OperatingSystem (au lieu d'en creer un nouveau).
     *
     * @param array<string, mixed> $driver
     *
     * @return array<string, mixed>
     */
    private function operatingSystemJsonFromDriver(array $driver): array
    {
        $hypervisor = ['id' => (int) $driver['hypervisor']];

        $osId = $driver['os_id'] ?? null;
        $os = null !== $osId ? $this->operatingSystemRepository->find((int) $osId) : null;

        if (null !== $os) {
            return [
                'name' => $os->getName(),
                'image' => $os->getImageUrl() ?: $os->getImageFilename(),
                'hypervisor' => $hypervisor,
            ];
        }

        return [
            'name' => (string) $driver['name'],
            'image' => (string) $driver['name'],
            'hypervisor' => $hypervisor,
        ];
    }

    /**
     * Noeud EVE-NG sans image disque (mini-simulateur IP `vpcs`).
     */
    private function isVpcsNode(array $node): bool
    {
        return 'vpcs' === strtolower(trim((string) ($node['template'] ?? '')))
            || 'vpcs' === strtolower(trim((string) ($node['type'] ?? '')));
    }

    private function isImageLessNode(array $node, array $entry): bool
    {
        if ($this->isVpcsNode($node)) {
            return true;
        }

        return '' === $node['image'] && isset($entry['os']);
    }

    private function isSystemNetwork(array $network): bool
    {
        $type = strtolower((string) $network['type']);

        return in_array($type, ['nat0', 'nat1', 'pnet0', 'pnet1', 'cloud', 'system'], true)
            || 'bridge' !== $type;
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogueEntry(string $template, string $nodeType): array
    {
        $this->loadCatalogue();

        $key = strtolower(trim($template));
        if ('' !== $key && isset($this->catalogue[$key])) {
            return $this->catalogue[$key];
        }

        // Repli selon le type de noeud EVE-NG.
        switch (strtolower($nodeType)) {
            case 'vpcs':
                return $this->catalogue['vpcs'] ?? ($this->catalogue['_default'] ?? []);
            case 'docker':
            case 'lxc':
                return $this->catalogue['docker'] ?? ($this->catalogue['_default'] ?? []);
            case 'iol':
                return $this->catalogue['iol'] ?? ($this->catalogue['_default'] ?? []);
            case 'dynamips':
                return $this->catalogue['dynamips'] ?? ($this->catalogue['_default'] ?? []);
        }

        return $this->catalogue['_default'] ?? [
            'device_type' => 'vm',
            'hypervisor' => 'qemu',
            'icon' => self::FALLBACK_ICON,
            'os_hint' => [],
            'suggested_action' => 'reuse',
        ];
    }

    private function loadCatalogue(): void
    {
        if (!empty($this->catalogue)) {
            return;
        }

        $path = $this->projectDirectory . '/config/eve-ng/templates.yaml';
        if (!is_file($path)) {
            $this->catalogue = ['_default' => [
                'device_type' => 'vm',
                'hypervisor' => 'qemu',
                'icon' => self::FALLBACK_ICON,
                'os_hint' => [],
                'suggested_action' => 'reuse',
            ]];

            return;
        }

        $this->catalogue = Yaml::parseFile($path) ?: [];
    }

    private function loadHypervisors(): void
    {
        if (null !== $this->hypervisorIds) {
            return;
        }

        $this->hypervisorIds = [];
        foreach ($this->hypervisorRepository->findAll() as $hypervisor) {
            $this->hypervisorIds[mb_strtolower((string) $hypervisor->getName())] = (int) $hypervisor->getId();
        }
    }

    private function hypervisorId(string $name): int
    {
        $this->loadHypervisors();
        $name = mb_strtolower($name);

        if (isset($this->hypervisorIds[$name])) {
            return $this->hypervisorIds[$name];
        }

        // Replis usuels (ids issus des fixtures).
        return match ($name) {
            'lxc' => 4,
            'natif' => 5,
            'physical' => 6,
            default => 3,
        };
    }

    /**
     * Deduit l'hyperviseur RemoteLabz de l'attribut `type` du <node> EVE-NG :
     *   - qemu / iol / dynamips -> qemu ;
     *   - docker / lxc          -> lxc ;
     *   - vpcs                  -> natif.
     * Un type inconnu retombe sur l'hyperviseur du catalogue (template).
     */
    private function hypervisorForNode(array $node, array $entry): string
    {
        switch (mb_strtolower(trim((string) ($node['type'] ?? '')))) {
            case 'qemu':
            case 'iol':
            case 'dynamips':
                return 'qemu';
            case 'docker':
            case 'lxc':
                return 'lxc';
            case 'vpcs':
                return 'natif';
        }

        return (string) ($entry['hypervisor'] ?? 'qemu');
    }

    /**
     * Normalise l'architecture EVE-NG (`qemu_arch`) vers un nom d'`Arch`
     * RemoteLabz valide (x86, x86_64, arm, arm64), ou "" si inconnue.
     */
    private function archForNode(array $node): string
    {
        $arch = mb_strtolower(trim((string) ($node['arch'] ?? '')));

        return match ($arch) {
            'x86_64', 'amd64', 'x86-64' => 'x86_64',
            'x86', 'i386', 'i486', 'i586', 'i686' => 'x86',
            'arm', 'armv7', 'armv7l', 'armhf' => 'arm',
            'arm64', 'aarch64', 'armv8' => 'arm64',
            default => '',
        };
    }

    /**
     * @return array<int, array{id: ?int, name: ?string, memory: int}>
     */
    private function loadFlavors(): array
    {
        $flavors = [];
        foreach ($this->flavorRepository->findAll() as $flavor) {
            $flavors[] = [
                'id' => $flavor->getId(),
                'name' => $flavor->getName(),
                'memory' => (int) $flavor->getMemory(),
            ];
        }
        usort($flavors, static fn (array $a, array $b): int => $a['memory'] <=> $b['memory']);

        return $flavors;
    }

    /**
     * Flavours RemoteLabz proposees a l'utilisateur pour une image sans
     * correspondance (choix de la RAM du device template).
     *
     * @return array<int, array{id: ?int, name: ?string, memory: int}>
     */
    public function flavorOptions(): array
    {
        if (empty($this->flavors)) {
            $this->flavors = $this->loadFlavors();
        }

        return $this->flavors;
    }

    /**
     * Flavour recommandee pour une RAM EVE-NG donnee (la plus proche >= ram).
     */
    public function recommendedFlavorId(int $ram): ?int
    {
        return $this->resolveFlavor($ram)['id'] ?? null;
    }

    /**
     * Arrondit la RAM EVE-NG a la flavour RemoteLabz superieure ou egale la plus
     * proche (la plus grande si aucune ne suffit).
     */
    private function resolveFlavor(int $ram): array
    {
        if (empty($this->flavors)) {
            $this->flavors = $this->loadFlavors();
        }

        if (empty($this->flavors)) {
            return ['id' => null, 'name' => 'small-1024', 'memory' => 1024, 'image' => null];
        }

        foreach ($this->flavors as $flavor) {
            $memory = (int) ($flavor['memory'] ?? 0);
            if ($memory >= $ram && $ram > 0) {
                return ['id' => $flavor['id'] ?? null, 'name' => $flavor['name'], 'memory' => $memory, 'image' => null];
            }
        }

        if ($ram <= 0) {
            $first = $this->flavors[0];

            return ['id' => $first['id'] ?? null, 'name' => $first['name'], 'memory' => (int) $first['memory'], 'image' => null];
        }

        $last = $this->flavors[count($this->flavors) - 1];

        return ['id' => $last['id'] ?? null, 'name' => $last['name'], 'memory' => (int) $last['memory'], 'image' => null];
    }

    /**
     * @return int[]
     */
    private function controlProtocols(string $console, string $deviceType): array
    {
        if (empty($this->controlProtocolIds)) {
            foreach ($this->controlProtocolTypeRepository->findAll() as $type) {
                $this->controlProtocolIds[mb_strtolower((string) $type->getName())] = (int) $type->getId();
            }
        }

        $wanted = [];
        switch (mb_strtolower($console)) {
            case 'vnc':
            case 'rdp':
                $wanted[] = 'vnc';
                break;
            case 'telnet':
            case 'serial':
                // Une console EVE-NG serie/telnet est exposee cote RemoteLabz
                // comme un acces serie.
                $wanted[] = 'serial';
                break;
            default:
                $wanted[] = 'login';
                break;
        }

        $ids = [];
        foreach ($wanted as $name) {
            if (isset($this->controlProtocolIds[$name])) {
                $ids[] = ['id' => $this->controlProtocolIds[$name]];
            }
        }

        return $ids;
    }

    /**
     * Identifiants des protocoles de controle correspondant a une console
     * EVE-NG (`vnc`/`rdp` -> vnc, `telnet`/`serial` -> serial, sinon login).
     *
     * Expose pour la creation d'un device template a partir d'un <node>.
     *
     * @return int[]
     */
    public function controlProtocolIds(string $console, string $deviceType = 'vm'): array
    {
        return array_map(
            static fn (array $entry): int => (int) $entry['id'],
            $this->controlProtocols($console, $deviceType)
        );
    }

    /**
     * Protocoles de controle declares par un driver (`controlProtocol`), avec
     * repli sur le protocole de console du noeud si le driver n'en declare aucun.
     *
     * @param array<string, mixed> $driver
     *
     * @return array<int, array{id: int}>
     */
    private function controlProtocolsFromDriver(array $driver, string $deviceType): array
    {
        $ids = [];
        foreach ((array) ($driver['control_protocol'] ?? []) as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[] = ['id' => $id];
            }
        }

        return [] !== $ids ? $ids : $this->controlProtocols('', $deviceType);
    }

    /**
     * Retrouve un driver par son identifiant (`Device.template`).
     *
     * @return array<string, mixed>|null
     */
    private function driverByTemplate(string $template): ?array
    {
        $this->loadDrivers();

        foreach ($this->drivers as $driver) {
            if ($driver['template'] === $template) {
                return $driver;
            }
        }

        return null;
    }

    /**
     * Choisit un driver (Device.template) parmi config/templates/.
     *
     * L'hyperviseur (qemu/lxc) prime sur l'image : un driver conteneur ne doit
     * jamais etre affecte a une VM (et inversement), meme si c'est le seul dont
     * l'operatingSystem correspond, car le lancement du device en dependrait.
     *
     *   1. l'indice du catalogue s'il existe ;
     *   2. un driver de meme operatingSystem ET de meme (type, hyperviseur) ;
     *   3. un driver de meme operatingSystem ET de meme hyperviseur ;
     *   4. un driver de meme (type, hyperviseur) ;
     *   5. a defaut seulement, un driver de meme operatingSystem ;
     *   6. sinon aucun driver.
     */
    private function pickDriver(string $deviceType, int $hypervisorId, ?string $rlzHint, ?int $osId = null): ?string
    {
        if (null !== $rlzHint && '' !== $rlzHint) {
            foreach ($this->drivers as $driver) {
                if ($driver['template'] === $rlzHint) {
                    return $driver['template'];
                }
            }
        }

        if (null !== $osId) {
            // 2. Meme OS et compatible (type, hyperviseur).
            foreach ($this->drivers as $driver) {
                if (null !== $driver['os_id'] && (int) $driver['os_id'] === $osId
                    && $driver['type'] === $deviceType && (int) $driver['hypervisor'] === $hypervisorId) {
                    return $driver['template'];
                }
            }

            // 3. Meme OS et meme hyperviseur.
            foreach ($this->drivers as $driver) {
                if (null !== $driver['os_id'] && (int) $driver['os_id'] === $osId
                    && (int) $driver['hypervisor'] === $hypervisorId) {
                    return $driver['template'];
                }
            }
        }

        // 4. Meme (type, hyperviseur) : compatible a defaut d'OS identique.
        foreach ($this->drivers as $driver) {
            if ($driver['type'] === $deviceType && (int) $driver['hypervisor'] === $hypervisorId) {
                return $driver['template'];
            }
        }

        // 5. Dernier recours : meme OS seul.
        if (null !== $osId) {
            foreach ($this->drivers as $driver) {
                if (null !== $driver['os_id'] && (int) $driver['os_id'] === $osId) {
                    return $driver['template'];
                }
            }
        }

        return null;
    }

    private function loadDrivers(): void
    {
        if (!empty($this->drivers)) {
            return;
        }

        $directory = $this->projectDirectory . '/config/templates';
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ('docker.yaml' === $entry || !preg_match('/^.+\.yaml$/', $entry)) {
                continue;
            }

            $data = Yaml::parseFile($directory . '/' . $entry);
            if (!is_array($data)) {
                continue;
            }

            $this->drivers[] = [
                'template' => basename($entry, '.yaml'),
                'name' => $data['name'] ?? basename($entry, '.yaml'),
                'type' => $data['type'] ?? 'vm',
                'hypervisor' => (int) ($data['hypervisor'] ?? 3),
                'os_id' => isset($data['operatingSystem']) ? (int) $data['operatingSystem'] : null,
                'description' => $data['description'] ?? '',
                'config_script' => $data['config_script'] ?? null,
                // Champs utilises lorsqu'un driver est choisi pour remplacer un
                // device sans image (vpcs/PC).
                'icon' => $data['icon'] ?? null,
                'control_protocol' => array_map('intval', (array) ($data['controlProtocol'] ?? [])),
                'network_interface_template' => $data['networkInterfaceTemplate'] ?? 'eth',
            ];
        }
    }

    private function resolveIcon(string $eveIcon, string $defaultIcon): string
    {
        $this->loadAvailableIcons();

        $candidate = null;
        if ('' !== $eveIcon) {
            $key = mb_strtolower($eveIcon);
            $candidate = self::EVE_ICON_MAP[$key] ?? $eveIcon;
        }

        if (null !== $candidate && $this->iconExists($candidate)) {
            return $candidate;
        }

        if ($this->iconExists($defaultIcon)) {
            return $defaultIcon;
        }

        // Recherche insensible a la casse dans les icones disponibles.
        if (null !== $candidate) {
            foreach ($this->availableIcons as $icon => $_) {
                if (mb_strtolower($icon) === mb_strtolower($candidate)) {
                    return $icon;
                }
            }
        }

        return self::FALLBACK_ICON;
    }

    private function loadAvailableIcons(): void
    {
        if (!empty($this->availableIcons)) {
            return;
        }

        if (!is_dir($this->iconsDirectory)) {
            return;
        }

        foreach (scandir($this->iconsDirectory) ?: [] as $icon) {
            if ('.' === $icon || '..' === $icon) {
                continue;
            }
            $this->availableIcons[$icon] = true;
        }
    }

    private function iconExists(string $icon): bool
    {
        $this->loadAvailableIcons();

        return isset($this->availableIcons[$icon]);
    }

    private function summarizeDevices(array $devices): array
    {
        $summary = [];
        foreach ($devices as $device) {
            $summary[] = [
                'eve_id' => $device['eve_id'],
                'name' => $device['name'],
                'eve_template' => null,
                'type' => $device['type'],
                'icon' => $device['icon'],
                'hypervisor' => $device['hypervisor']['id'] ?? null,
                'nbCpu' => $device['nbCpu'],
                'operatingSystem' => $device['operatingSystem'],
                'editorData' => $device['editorData'],
                'networkInterfaces' => array_map(
                    static fn (array $nic): array => ['name' => $nic['name'], 'connection' => $nic['connection']],
                    $device['networkInterfaces']
                ),
            ];
        }

        return $summary;
    }

    private function sanitizeLabName(string $name): string
    {
        $name = trim($name);
        if ('' === $name) {
            $name = 'EVE-NG lab';
        }

        if (mb_strlen($name) > 255) {
            $name = mb_substr($name, 0, 255);
        }

        return $name;
    }
}
