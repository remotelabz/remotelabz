<?php

namespace App\Service\Lab\Unl;

use App\Entity\EveNgImageMapping;
use App\Entity\OperatingSystem;
use App\Entity\User;
use App\Repository\ArchRepository;
use App\Repository\FlavorDiskRepository;
use App\Repository\FlavorRepository;
use App\Repository\HypervisorRepository;
use App\Repository\OperatingSystemRepository;
use App\Service\EveNg\ImageCatalog;
use App\Service\Files2WorkerManager;
use App\Service\Lab\LabImporter;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Orchestre l'import d'un lab EVE-NG (`.unl` ou `.zip`) dans RemoteLabz.
 *
 * Deux temps, conformes au document `doc/eve-ng-import-export.md` :
 *
 *   analyze() : dry-run. Parse le fichier, convertit la topologie en JSON
 *               RemoteLabz et calcule pour chaque image un statut de resolution.
 *               Ne persiste rien ; renvoie un LabPreview serialisable.
 *
 *   commit()  : persistance. Applique les decisions prises par l'utilisateur
 *               (reutilisation, upload, URL, VM vierge, ignorer), memorise les
 *               correspondances dans le glossaire, diffuse les nouveaux
 *               fichiers sur les workers puis delegue la creation effective du
 *               Lab a LabImporter::import().
 *
 * Le commit est volontairement recalcule cote serveur a partir du fichier
 * d'origine : le LabPreview ne transporte pas le labJson (potentiellement
 * volumineux et falsifiable cote client).
 */
class UnlImporter
{
    /** Statuts ou l'OS est considere comme resolu (aucune action manuelle). */
    private const RESOLVED_STATUSES = ['matched', 'auto'];

    /** Taille par defaut d'un disque cree pour une VM vierge. */
    private const DEFAULT_DISK_FLAVOR = '3GB';

    private LoggerInterface $logger;
    private UnlParser $parser;
    private ImageResolver $imageResolver;
    private EveNgMapper $mapper;
    private ImageCatalog $imageCatalog;
    private LabImporter $labImporter;
    private OperatingSystemRepository $operatingSystemRepository;
    private HypervisorRepository $hypervisorRepository;
    private ArchRepository $archRepository;
    private FlavorDiskRepository $flavorDiskRepository;
    private FlavorRepository $flavorRepository;
    private Files2WorkerManager $files2WorkerManager;
    private DeviceTemplateFactory $deviceTemplateFactory;

    public function __construct(
        LoggerInterface $logger,
        UnlParser $parser,
        ImageResolver $imageResolver,
        EveNgMapper $mapper,
        ImageCatalog $imageCatalog,
        LabImporter $labImporter,
        OperatingSystemRepository $operatingSystemRepository,
        HypervisorRepository $hypervisorRepository,
        ArchRepository $archRepository,
        FlavorDiskRepository $flavorDiskRepository,
        FlavorRepository $flavorRepository,
        Files2WorkerManager $files2WorkerManager,
        DeviceTemplateFactory $deviceTemplateFactory
    ) {
        $this->logger = $logger;
        $this->parser = $parser;
        $this->imageResolver = $imageResolver;
        $this->mapper = $mapper;
        $this->imageCatalog = $imageCatalog;
        $this->labImporter = $labImporter;
        $this->operatingSystemRepository = $operatingSystemRepository;
        $this->hypervisorRepository = $hypervisorRepository;
        $this->archRepository = $archRepository;
        $this->flavorDiskRepository = $flavorDiskRepository;
        $this->flavorRepository = $flavorRepository;
        $this->files2WorkerManager = $files2WorkerManager;
        $this->deviceTemplateFactory = $deviceTemplateFactory;
    }

    /**
     * Analyse un fichier EVE-NG sans rien persister.
     */
    public function analyze(string $path, ?string $originalName = null): LabPreview
    {
        $parsed = $this->parser->parseFile($path);

        $requirements = $this->mapper->buildRequirements($parsed);
        $resolution = $this->imageResolver->resolve($requirements);
        $images = $resolution['images'];

        // Pour chaque image, indique la flavour conseillee (RAM EVE-NG arrondie a
        // la flavour RemoteLabz superieure). Sert de valeur par defaut dans la
        // fenetre d'import lorsqu'un device template doit etre cree.
        $images = $this->attachFlavorRecommendations($images, $requirements);

        $imageMap = $this->keyImagesByEveImage($images);

        // Noeuds sans image (vpcs/PC) : on expose les drivers candidats et on
        // applique le driver retenu par defaut, de sorte que l'apercu montre les
        // devices tels qu'ils seront importes si l'utilisateur ne change rien.
        $deviceChoices = $this->mapper->deviceChoices($parsed);
        $selectedDevices = [];
        foreach ($deviceChoices as $choice) {
            if (!empty($choice['selected'])) {
                $selectedDevices[(int) $choice['eve_id']] = ['template' => (string) $choice['selected']];
            }
        }

        $mapped = $this->mapper->map($parsed, $imageMap, $selectedDevices);

        $warnings = array_merge($mapped['warnings'] ?? [], $this->collectWarnings($images));

        if (!empty($resolution['warn_worker_offline'])) {
            $warnings[] = 'No worker reachable: the actual presence of the images on the workers could not be verified.';
        }

        $source = [
            'format' => 'eve-ng',
            'file' => $originalName ?? basename($path),
            'entry' => $parsed['lab']['archive_entry'] ?? null,
        ];

        $lab = [
            'name' => $mapped['lab']['name'] ?? ($parsed['lab']['name'] ?? 'EVE-NG lab'),
            'virtuality' => 1,
            'devices' => (int) ($mapped['lab']['devices'] ?? count($parsed['nodes'])),
            'links' => (int) ($mapped['lab']['links'] ?? 0),
            'textobjects' => (int) ($mapped['lab']['textobjects'] ?? count($parsed['textobjects'])),
        ];

        return new LabPreview($source, $lab, $mapped['devices'], $images, $mapped['labJson'], $warnings, $this->mapper->deviceCandidates(), $deviceChoices, $this->mapper->flavorOptions());
    }

    /**
     * Analyse puis commit en une seule passe (utilise par l'import direct
     * "Phase 1" ou lorsqu'aucune decision n'est fournie).
     *
     * @param array<string, array> $mapping       Decisions indexees par identifiant d'image EVE-NG
     * @param array<int, array>    $deviceChoices Device de remplacement choisi
     *                                            ({ eve_id, template }), pour les
     *                                            noeuds sans image (vpcs/PC)
     */
    public function import(string $path, array $mapping = [], array $deviceChoices = [], ?User $user = null): int
    {
        $parsed = $this->parser->parseFile($path);
        $requirements = $this->mapper->buildRequirements($parsed);

        $resolution = $this->imageResolver->resolve($requirements);
        $analyzed = $this->keyImagesByEveImage($resolution['images']);

        $applied = $this->applyDecisions($requirements, $analyzed, $mapping, $user);

        $mapped = $this->mapper->map(
            $parsed,
            $applied['images'],
            $this->normalizeDeviceChoices($deviceChoices),
            $applied['overrides']
        );

        $this->logger->info('[UnlImporter:import]::Committing EVE-NG lab', [
            'file' => basename($path),
            'devices' => count($mapped['labJson']['devices']),
        ]);

        return $this->labImporter->import(json_encode($mapped['labJson'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Applique les decisions utilisateur, cree les OS manquants, cree un device
     * template pour chaque image sans correspondance et memorise le glossaire.
     *
     * @param array<int, array>    $requirements
     * @param array<string, array> $analyzed      Resolution automatique indexee par image EVE-NG
     * @param array<string, array> $mapping       Decisions indexees par image EVE-NG
     *
     * @return array{
     *     images: array<string, array{os_id: ?int, os_name: ?string, os_image: ?string}>,
     *     overrides: array<string, array{template: string, flavor: array}>
     * }
     */
    private function applyDecisions(array $requirements, array $analyzed, array $mapping, ?User $user): array
    {
        $applied = [];
        $overrides = [];

        foreach ($requirements as $requirement) {
            $eveImage = (string) $requirement['eve_image'];
            $decision = $mapping[$eveImage] ?? null;

            $resolution = null;
            if (is_array($decision)) {
                $resolution = $this->applyDecision($requirement, $decision, $user);
            }

            // Aucune decision (ou decision inapplicable) : on retombe sur la
            // resolution automatique si elle existe.
            if (null === $resolution && isset($analyzed[$eveImage]) && in_array($analyzed[$eveImage]['status'], self::RESOLVED_STATUSES, true)) {
                $resolution = [
                    'os_id' => $analyzed[$eveImage]['os_id'] ?? null,
                    'os_name' => $analyzed[$eveImage]['os_name'] ?? null,
                    'os_image' => $analyzed[$eveImage]['os_image'] ?? null,
                ];
            }

            // Dernier recours : placeholder portant l'identifiant EVE-NG.
            if (null === $resolution || null === $resolution['os_image']) {
                $resolution = [
                    'os_id' => null,
                    'os_name' => $eveImage,
                    'os_image' => $eveImage,
                ];
            }

            $applied[$eveImage] = $resolution;

            // L'image n'existait pas (statut "missing") : on materialise en plus
            // un device template RemoteLabz portant les parametres du <node>, et
            // on fait pointer les devices importes dessus.
            if ($this->isUnknownImage($eveImage, $analyzed)) {
                $override = $this->createDeviceTemplateOverride($requirement, $resolution, $decision, $user);
                if (null !== $override) {
                    $overrides[$eveImage] = $override;
                }
            }
        }

        return ['images' => $applied, 'overrides' => $overrides];
    }

    /**
     * Cree un device template pour une image sans correspondance et retourne la
     * surcharge a appliquer aux devices importes (`template` + `flavor`).
     *
     * @param array<string, mixed>      $requirement
     * @param array{os_id: ?int, os_name: ?string, os_image: ?string} $resolution
     * @param array<string, mixed>|null $decision
     *
     * @return array{template: string, flavor: array}|null
     */
    private function createDeviceTemplateOverride(array $requirement, array $resolution, ?array $decision, ?User $user): ?array
    {
        $osId = $resolution['os_id'] ?? null;
        if (null === $osId) {
            // Aucune OS reel (action "skip" ou placeholder) : pas de template.
            return null;
        }

        $os = $this->operatingSystemRepository->find($osId);
        if (null === $os) {
            return null;
        }

        $deviceType = (string) ($requirement['device_type'] ?? 'vm');
        $console = (string) ($requirement['console'] ?? '');
        $ram = (int) ($requirement['ram'] ?? 0);

        $flavor = $this->resolveImportFlavor($decision, $ram);

        $created = $this->deviceTemplateFactory->findOrCreate(
            (string) $os->getName(),
            $deviceType,
            $this->operatingSystemHypervisor($os, $requirement['hypervisor'] ?? null),
            $os,
            $flavor,
            (int) ($requirement['cpu'] ?? 1),
            (string) ($requirement['icon'] ?? 'Server_Linux.png'),
            $this->mapper->controlProtocolIds($console, $deviceType),
            $user
        );

        return [
            'template' => $created['template'],
            'flavor' => [
                'id' => null !== $flavor ? $flavor->getId() : null,
                'name' => null !== $flavor ? $flavor->getName() : null,
                'memory' => null !== $flavor ? (int) $flavor->getMemory() : $ram,
                'image' => null,
            ],
        ];
    }

    /**
     * Flavour retenue pour l'import : celle choisie par l'utilisateur
     * (`flavor_id`), sinon celle recommandee pour la RAM du <node>.
     */
    private function resolveImportFlavor(?array $decision, int $ram): ?\App\Entity\Flavor
    {
        $flavorId = $decision['flavor_id'] ?? null;
        if (null !== $flavorId && '' !== $flavorId && null !== $flavor = $this->flavorRepository->find((int) $flavorId)) {
            return $flavor;
        }

        $recommendedId = $this->mapper->recommendedFlavorId($ram);
        if (null !== $recommendedId && null !== $flavor = $this->flavorRepository->find($recommendedId)) {
            return $flavor;
        }

        // Dernier recours : une flavour existe forcement (colonne NOT NULL), on
        // prend la plus petite disponible plutot que d'echouer a la creation.
        return $this->flavorRepository->findOneBy([], ['memory' => 'ASC']);
    }

    /**
     * Hyperviseur du device template : celui de l'OS cree prime, sinon
     * l'hyperviseur deduit du <node>, sinon qemu.
     */
    private function operatingSystemHypervisor(OperatingSystem $os, ?string $nodeHypervisor): ?\App\Entity\Hypervisor
    {
        if (null !== $os->getHypervisor()) {
            return $os->getHypervisor();
        }

        return $this->resolveHypervisor($nodeHypervisor);
    }

    /**
     * Une image est "inconnue" lorsqu'aucune correspondance n'a ete trouvee lors
     * de l'analyse (statut `missing`), par opposition a une image deja resolue.
     *
     * @param array<string, array> $analyzed
     */
    private function isUnknownImage(string $eveImage, array $analyzed): bool
    {
        if (!isset($analyzed[$eveImage])) {
            return true;
        }

        return 'missing' === ($analyzed[$eveImage]['status'] ?? null);
    }

    /**
     * @return array{os_id: ?int, os_name: ?string, os_image: ?string}|null
     */
    private function applyDecision(array $requirement, array $decision, ?User $user): ?array
    {
        $eveImage = (string) $requirement['eve_image'];
        $action = strtolower((string) ($decision['action'] ?? ''));
        $hypervisor = $this->resolveHypervisor($requirement['hypervisor'] ?? null);
        $arch = $this->resolveArch($requirement['arch'] ?? null);

        switch ($action) {
            case 'reuse':
                $osId = $decision['os_id'] ?? null;
                if (null === $osId || null === $os = $this->operatingSystemRepository->find($osId)) {
                    return null;
                }
                $this->imageCatalog->remember($eveImage, $os, $user);

                return $this->resolutionFromOs($os);

            case 'upload':
                $filename = isset($decision['filename']) ? basename((string) $decision['filename']) : '';
                if ('' === $filename) {
                    return null;
                }
                $os = $this->createOperatingSystem(
                    (string) ($decision['name'] ?? $eveImage),
                    $filename,
                    null,
                    $hypervisor,
                    $arch,
                    (string) ($decision['description'] ?? 'EVE-NG image imported (uploaded file).')
                );
                $this->rememberAndDistribute($eveImage, $os, $filename, $user);

                return $this->resolutionFromOs($os);

            case 'url':
                $url = trim((string) ($decision['url'] ?? ''));
                if ('' === $url) {
                    return null;
                }
                $os = $this->createOperatingSystem(
                    (string) ($decision['name'] ?? $eveImage),
                    null,
                    $url,
                    $hypervisor,
                    $arch,
                    (string) ($decision['description'] ?? 'EVE-NG image imported (remote URL).')
                );
                $this->imageCatalog->remember($eveImage, $os, $user);

                return $this->resolutionFromOs($os);

            case 'blank':
                $flavorDisk = $this->resolveFlavorDisk($decision['flavorDisk'] ?? self::DEFAULT_DISK_FLAVOR);
                if (null === $flavorDisk) {
                    return null;
                }
                $name = (string) ($decision['name'] ?? $eveImage);
                $filename = preg_replace('/[ .]+/', '_', trim($name)) . '_' . preg_replace('/[ .]+/', '_', trim($flavorDisk->getName())) . '.qcow2';
                $os = $this->createOperatingSystem(
                    $name,
                    $filename,
                    null,
                    $hypervisor,
                    $arch,
                    (string) ($decision['description'] ?? 'Blank VM created from an EVE-NG import.'),
                    $flavorDisk
                );
                $this->rememberAndDistribute($eveImage, $os, $filename, $user);

                return $this->resolutionFromOs($os);

            case 'skip':
                // Import volontairement partiel : device sans OS reel.
                return [
                    'os_id' => null,
                    'os_name' => (string) ($requirement['eve_image'] ?? ''),
                    'os_image' => (string) ($requirement['eve_image'] ?? ''),
                ];

            default:
                return null;
        }
    }

    private function createOperatingSystem(
        string $name,
        ?string $imageFilename,
        ?string $imageUrl,
        ?\App\Entity\Hypervisor $hypervisor,
        ?\App\Entity\Arch $arch,
        string $description,
        ?\App\Entity\FlavorDisk $flavorDisk = null
    ): OperatingSystem {
        $os = new OperatingSystem();
        $os->setName($name);

        if (null !== $imageUrl && '' !== $imageUrl) {
            $os->setImageUrl($imageUrl);
        } elseif (null !== $imageFilename && '' !== $imageFilename) {
            $os->setImageFilename($imageFilename);
        }

        $os->setDescription($description);

        if (null !== $hypervisor) {
            $os->setHypervisor($hypervisor);
        }
        if (null !== $arch) {
            $os->setArch($arch);
        }
        if (null !== $flavorDisk) {
            $os->setFlavorDisk($flavorDisk);
        }

        // Champ virtuel : rend l'OS immediatement serialisable pour EveNgMapper.
        $os->setImage($imageUrl ?: $imageFilename);

        $this->operatingSystemRepository->getEntityManager()->persist($os);
        $this->operatingSystemRepository->getEntityManager()->flush();

        $this->logger->info('[UnlImporter:createOperatingSystem]::Created operating system from EVE-NG import', [
            'os_id' => $os->getId(),
            'name' => $name,
            'image_filename' => $imageFilename,
            'image_url' => $imageUrl,
        ]);

        return $os;
    }

    private function rememberAndDistribute(string $eveImage, OperatingSystem $os, string $filename, ?User $user): void
    {
        $this->imageCatalog->remember($eveImage, $os, $user);

        try {
            $this->files2WorkerManager->copyFileToAllWorkers('image', $filename);
        } catch (Throwable $exception) {
            $this->logger->warning('[UnlImporter:rememberAndDistribute]::Unable to distribute image to workers', [
                'filename' => $filename,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{os_id: ?int, os_name: ?string, os_image: ?string}
     */
    private function resolutionFromOs(OperatingSystem $os): array
    {
        return [
            'os_id' => $os->getId(),
            'os_name' => $os->getName(),
            'os_image' => $os->getImageUrl() ?: $os->getImageFilename(),
        ];
    }

    private function resolveHypervisor(?string $name): ?\App\Entity\Hypervisor
    {
        $name = null !== $name && '' !== $name ? $name : 'qemu';

        $hypervisor = $this->hypervisorRepository->findByName($name);
        if (null === $hypervisor) {
            $hypervisor = $this->hypervisorRepository->findByName('qemu');
        }

        return $hypervisor;
    }

    /**
     * Resout l'architecture (`qemu_arch` de l'EVE-NG) vers un `Arch` RemoteLabz.
     * Repli sur x86_64 si l'architecture est absente ou inconnue en base.
     */
    private function resolveArch(?string $name): ?\App\Entity\Arch
    {
        $name = null !== $name && '' !== $name ? $name : 'x86_64';

        $arch = $this->archRepository->findByName($name);
        if (null === $arch) {
            $arch = $this->archRepository->findByName('x86_64');
        }

        return $arch;
    }

    private function resolveFlavorDisk(mixed $value): ?\App\Entity\FlavorDisk
    {
        if ($value instanceof \App\Entity\FlavorDisk) {
            return $value;
        }

        $name = is_string($value) && '' !== $value ? $value : self::DEFAULT_DISK_FLAVOR;

        $flavorDisk = $this->flavorDiskRepository->findByNameInsensitive($name);
        if (null !== $flavorDisk) {
            return $flavorDisk;
        }

        $ordered = $this->flavorDiskRepository->findAllOrderedBySize();
        if (empty($ordered)) {
            return null;
        }

        // Repli sur le plus grand disque disponible.
        return $ordered[count($ordered) - 1];
    }

    /**
     * Reindexe les choix de device ({ eve_id, template }) en une carte
     * { eve_id => { template } } attendue par EveNgMapper::map(). Accepte une
     * liste indexee ou une carte deja indexee par `eve_id`.
     *
     * @param array<int|string, array> $deviceChoices
     *
     * @return array<int, array{template: string}>
     */
    private function normalizeDeviceChoices(array $deviceChoices): array
    {
        $normalized = [];

        foreach ($deviceChoices as $key => $choice) {
            if (!is_array($choice)) {
                continue;
            }

            $eveId = isset($choice['eve_id']) ? (int) $choice['eve_id'] : (is_numeric($key) ? (int) $key : 0);
            $template = trim((string) ($choice['template'] ?? ''));

            if ($eveId > 0 && '' !== $template) {
                $normalized[$eveId] = ['template' => $template];
            }
        }

        return $normalized;
    }

    /**
     * @param array<int, array> $images
     *
     * @return array<string, array>
     */
    private function keyImagesByEveImage(array $images): array
    {
        $map = [];
        foreach ($images as $image) {
            if (isset($image['eve_image'])) {
                $map[(string) $image['eve_image']] = $image;
            }
        }

        return $map;
    }

    /**
     * Ajoute a chaque image la flavour recommandee (`recommended_flavor_id`)
     * calculee a partir de la RAM du <node> EVE-NG.
     *
     * @param array<int, array> $images
     * @param array<int, array> $requirements
     *
     * @return array<int, array>
     */
    private function attachFlavorRecommendations(array $images, array $requirements): array
    {
        $ramByImage = [];
        foreach ($requirements as $requirement) {
            $ramByImage[(string) $requirement['eve_image']] = (int) ($requirement['ram'] ?? 0);
        }

        foreach ($images as $index => $image) {
            $eveImage = (string) ($image['eve_image'] ?? '');
            $images[$index]['recommended_flavor_id'] = $this->mapper->recommendedFlavorId($ramByImage[$eveImage] ?? 0);
        }

        return $images;
    }

    /**
     * @param array<int, array> $images
     *
     * @return string[]
     */
    private function collectWarnings(array $images): array
    {
        $warnings = [];
        $unresolved = 0;

        foreach ($images as $image) {
            if ('missing' === ($image['status'] ?? null)) {
                ++$unresolved;
            }
        }

        if ($unresolved > 0) {
            $warnings[] = sprintf('%d unresolved image(s): an action is required in the review window.', $unresolved);
        }

        return $warnings;
    }
}
