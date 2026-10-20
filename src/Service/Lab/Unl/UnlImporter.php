<?php

namespace App\Service\Lab\Unl;

use App\Entity\EveNgImageMapping;
use App\Entity\OperatingSystem;
use App\Entity\User;
use App\Repository\ArchRepository;
use App\Repository\FlavorDiskRepository;
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
    private Files2WorkerManager $files2WorkerManager;

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
        Files2WorkerManager $files2WorkerManager
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
        $this->files2WorkerManager = $files2WorkerManager;
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

        $imageMap = $this->keyImagesByEveImage($images);
        $mapped = $this->mapper->map($parsed, $imageMap);

        $warnings = array_merge($mapped['warnings'] ?? [], $this->collectWarnings($images));

        if (!empty($resolution['warn_worker_offline'])) {
            $warnings[] = 'Aucun worker joignable : la presence reelle des images sur les workers n\'a pas pu etre verifiee.';
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

        return new LabPreview($source, $lab, $mapped['devices'], $images, $mapped['labJson'], $warnings);
    }

    /**
     * Analyse puis commit en une seule passe (utilise par l'import direct
     * "Phase 1" ou lorsqu'aucune decision n'est fournie).
     *
     * @param array<string, array> $mapping Decisions indexees par identifiant d'image EVE-NG
     */
    public function import(string $path, array $mapping = [], ?User $user = null): int
    {
        $parsed = $this->parser->parseFile($path);
        $requirements = $this->mapper->buildRequirements($parsed);

        $resolution = $this->imageResolver->resolve($requirements);
        $analyzed = $this->keyImagesByEveImage($resolution['images']);

        $applied = $this->applyDecisions($requirements, $analyzed, $mapping, $user);

        $mapped = $this->mapper->map($parsed, $applied);

        $this->logger->info('[UnlImporter:import]::Committing EVE-NG lab', [
            'file' => basename($path),
            'devices' => count($mapped['labJson']['devices']),
        ]);

        return $this->labImporter->import(json_encode($mapped['labJson'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Applique les decisions utilisateur, cree les OS manquants et memorise le
     * glossaire. Retourne la carte { eve_image => { os_id, os_name, os_image } }
     * attendue par EveNgMapper.
     *
     * @param array<int, array>    $requirements
     * @param array<string, array> $analyzed      Resolution automatique indexee par image EVE-NG
     * @param array<string, array> $mapping       Decisions indexees par image EVE-NG
     *
     * @return array<string, array{os_id: ?int, os_name: ?string, os_image: ?string}>
     */
    private function applyDecisions(array $requirements, array $analyzed, array $mapping, ?User $user): array
    {
        $applied = [];

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
        }

        return $applied;
    }

    /**
     * @return array{os_id: ?int, os_name: ?string, os_image: ?string}|null
     */
    private function applyDecision(array $requirement, array $decision, ?User $user): ?array
    {
        $eveImage = (string) $requirement['eve_image'];
        $action = strtolower((string) ($decision['action'] ?? ''));
        $hypervisor = $this->resolveHypervisor($requirement['hypervisor'] ?? null);
        $arch = $this->archRepository->findByName('x86_64');

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
                    (string) ($decision['description'] ?? 'Image EVE-NG importee (fichier televerse).')
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
                    (string) ($decision['description'] ?? 'Image EVE-NG importee (URL distante).')
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
                    (string) ($decision['description'] ?? 'VM vierge creee depuis un import EVE-NG.'),
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
            $warnings[] = sprintf('%d image(s) non resolue(s) : une action est requise dans la fenetre de revue.', $unresolved);
        }

        return $warnings;
    }
}
