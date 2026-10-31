<?php

namespace App\Service\Lab\Unl;

use App\Entity\OperatingSystem;
use App\Repository\ConfigWorkerRepository;
use App\Repository\OperatingSystemRepository;
use App\Service\EveNg\ImageCatalog;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Compare les images d'un lab EVE-NG aux OperatingSystem RemoteLabz et calcule,
 * pour chacune, un statut :
 *
 *   - matched        : une image RLZ porte exactement le meme identifiant ;
 *   - auto           : une equivalence probable a ete trouvee (glossaire ou
 *                      heuristique de similarite) ;
 *   - missing        : aucune correspondance (resolution manuelle requise) ;
 *   - not_applicable : device sans image (vpcs/natif).
 *
 * Le service interroge aussi le worker (`GET /os`) pour distinguer "l'OS existe
 * en base" de "l'image est reellement presente sur le worker". Si aucun worker
 * n'est joignable, le controle de presence est ignore (avec un avertissement)
 * afin de ne pas bloquer les imports sur un front isole.
 */
class ImageResolver
{
    /** Seuil de similarite (pourcentage) au-dessus duquel une equivalence est proposee. */
    private const AUTO_THRESHOLD = 60.0;

    private const FAMILY_TOKENS = [
        'windows' => ['win', 'windows'],
        'pfsense' => ['pfsense'],
        'cisco' => ['vios', 'iosv', 'ios', 'iol', 'dynamips', 'cisco', 'asa'],
        'linux' => ['linux', 'ubuntu', 'debian', 'alpine', 'rocky', 'centos', 'fedora', 'arch', 'suse', 'kali', 'freebsd', 'redhat', 'rhel'],
    ];

    private LoggerInterface $logger;
    private OperatingSystemRepository $operatingSystemRepository;
    private ImageCatalog $imageCatalog;
    private ConfigWorkerRepository $configWorkerRepository;
    private HttpClientInterface $httpClient;
    private string $workerPort;

    public function __construct(
        LoggerInterface $logger,
        OperatingSystemRepository $operatingSystemRepository,
        ImageCatalog $imageCatalog,
        ConfigWorkerRepository $configWorkerRepository,
        string $workerPort
    ) {
        $this->logger = $logger;
        $this->operatingSystemRepository = $operatingSystemRepository;
        $this->imageCatalog = $imageCatalog;
        $this->configWorkerRepository = $configWorkerRepository;
        $this->workerPort = $workerPort;
        $this->httpClient = HttpClient::create(['timeout' => 5]);
    }

    /**
     * @param array $requirements Liste d'exigences issues de EveNgMapper :
     *        [['eve_image'=>string,'hypervisor'=>?string,'os_hint'=>string[],
     *          'applicable'=>bool,'suggested_action'=>string,'used_by'=>int[]], ...]
     *
     * @return array{images: array, warn_worker_offline: bool}
     */
    public function resolve(array $requirements): array
    {
        $operatingSystems = $this->operatingSystemRepository->findAll();
        $workerImages = $this->fetchWorkerImages();
        $warnWorkerOffline = null === $workerImages;

        $eveImages = array_values(array_filter(array_map(
            static fn (array $requirement): string => $requirement['eve_image'] ?? '',
            $requirements
        )));

        $glossary = $this->imageCatalog->findForImages($eveImages);

        $images = [];
        foreach ($requirements as $requirement) {
            $images[] = $this->resolveOne($requirement, $operatingSystems, $glossary, $workerImages, $warnWorkerOffline);
        }

        return ['images' => $images, 'warn_worker_offline' => $warnWorkerOffline];
    }

    private function resolveOne(
        array $requirement,
        array $operatingSystems,
        array $glossary,
        ?array $workerImages,
        bool $workerOffline
    ): array {
        $eveImage = (string) $requirement['eve_image'];

        $result = [
            'eve_image' => $eveImage,
            'status' => 'missing',
            'used_by' => $requirement['used_by'] ?? [],
            'os_id' => null,
            'os_name' => null,
            'os_image' => null,
            'reason' => null,
            'candidates' => [],
            'suggested_action' => $requirement['suggested_action'] ?? 'reuse',
            'hypervisor' => $requirement['hypervisor'] ?? null,
            'arch' => $requirement['arch'] ?? null,
            // Parametres du <node> repris pour creer un device template lorsque
            // l'image reste sans correspondance (cf. UnlImporter).
            'device_type' => $requirement['device_type'] ?? 'vm',
            'console' => $requirement['console'] ?? '',
            'cpu' => (int) ($requirement['cpu'] ?? 1),
            'ram' => (int) ($requirement['ram'] ?? 0),
            'icon' => $requirement['icon'] ?? null,
        ];

        if (!($requirement['applicable'] ?? true)) {
            $result['status'] = 'not_applicable';
            $result['reason'] = 'device without image (vpcs/native)';

            return $result;
        }

        // 1. Correspondance exacte en base (imageUrl ou imageFilename).
        $exact = $this->findExact($eveImage, $operatingSystems);
        if (null !== $exact) {
            return $this->decorateMatch($result, $exact, 'matched', $operatingSystems, $glossary, $workerImages, $workerOffline);
        }

        // 2. Correspondance memorisee dans le glossaire.
        if (isset($glossary[$eveImage]) && null !== $os = $glossary[$eveImage]->getOperatingSystem()) {
            return $this->decorateMatch($result, $os, 'auto', $operatingSystems, $glossary, $workerImages, $workerOffline, 'shared glossary');
        }

        // 3. Heuristique (famille + similarite).
        $candidates = $this->rankCandidates($eveImage, $operatingSystems, $requirement['os_hint'] ?? []);
        $result['candidates'] = $candidates;

        if (!empty($candidates) && $candidates[0]['score'] >= self::AUTO_THRESHOLD) {
            $os = $this->operatingSystemRepository->find($candidates[0]['id']);
            if (null !== $os) {
                return $this->decorateMatch($result, $os, 'auto', $operatingSystems, $glossary, $workerImages, $workerOffline, 'suggested equivalent');
            }
        }

        $result['status'] = 'missing';
        $result['reason'] = 'no match';

        return $result;
    }

    private function decorateMatch(
        array $result,
        OperatingSystem $os,
        string $status,
        array $operatingSystems,
        array $glossary,
        ?array $workerImages,
        bool $workerOffline,
        ?string $reason = null
    ): array {
        $result['status'] = $status;
        $result['os_id'] = $os->getId();
        $result['os_name'] = $os->getName();
        $result['os_image'] = self::imageOf($os);
        $result['reason'] = $reason;

        // L'OS existe en base : proposer aussi des alternatives classees.
        $result['candidates'] = $this->rankCandidates((string) $result['eve_image'], $operatingSystems, []);

        // Presence reelle du fichier sur le worker.
        if (!$workerOffline && null !== $workerImages) {
            $missingFile = $this->isMissingOnWorkers($os, $workerImages);
            if ($missingFile) {
                $result['status'] = 'missing';
                $result['reason'] = sprintf("the image '%s' is missing from the workers (to (re)distribute)", $os->getImageFilename() ?? $os->getName());
            }
        }

        return $result;
    }

    private static function imageOf(OperatingSystem $os): ?string
    {
        return $os->getImageUrl() ?: $os->getImageFilename();
    }

    /**
     * @param OperatingSystem[] $operatingSystems
     */
    private function findExact(string $eveImage, array $operatingSystems): ?OperatingSystem
    {
        $needle = mb_strtolower($eveImage);

        foreach ($operatingSystems as $os) {
            $url = $os->getImageUrl();
            $filename = $os->getImageFilename();

            if ((null !== $url && mb_strtolower($url) === $needle)
                || (null !== $filename && mb_strtolower($filename) === $needle)
            ) {
                return $os;
            }
        }

        return null;
    }

    /**
     * Classe les OS RemoteLabz par similarite avec l'identifiant EVE-NG.
     *
     * @param OperatingSystem[] $operatingSystems
     *
     * @return array<int, array{id:int, name:?string, image:?string, score:float}>
     */
    private function rankCandidates(string $eveImage, array $operatingSystems, array $osHint): array
    {
        $eveFamily = self::familyOf($eveImage);
        $eveNormalized = self::normalize($eveImage);
        $hinted = array_map(static fn ($hint): string => mb_strtolower((string) $hint), $osHint);

        $candidates = [];
        foreach ($operatingSystems as $os) {
            if (null === self::imageOf($os) && null === $os->getName()) {
                continue;
            }

            $family = self::familyOf(implode(' ', array_filter([
                (string) $os->getName(),
                (string) $os->getImageFilename(),
                (string) $os->getImageUrl(),
            ])));

            if (null !== $eveFamily && null !== $family && $eveFamily !== $family) {
                continue;
            }

            $score = 0.0;
            foreach ([$os->getName(), $os->getImageFilename(), $os->getImageUrl()] as $value) {
                if (null === $value || '' === $value) {
                    continue;
                }
                $score = max($score, self::similarity($eveNormalized, self::normalize($value)));
            }

            // Bonus : le nom de l'OS ou de son image contient un indice du template.
            $haystack = mb_strtolower(implode(' ', array_filter([(string) $os->getName(), (string) $os->getImageFilename()])));
            foreach ($hinted as $hint) {
                if ('' !== $hint && str_contains($haystack, $hint)) {
                    $score = min(100.0, $score + 10.0);
                    break;
                }
            }

            // Bonus de famille explicite.
            if (null !== $eveFamily && $eveFamily === $family) {
                $score = min(100.0, $score + 5.0);
            }

            $candidates[] = [
                'id' => (int) $os->getId(),
                'name' => $os->getName(),
                'image' => self::imageOf($os),
                'score' => round($score, 1),
            ];
        }

        usort($candidates, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($candidates, 0, 5);
    }

    /**
     * @param array{qemu: string[], lxc: string[]} $workerImages
     */
    private function isMissingOnWorkers(OperatingSystem $os, array $workerImages): bool
    {
        $filename = $os->getImageFilename();
        if (null === $filename || '' === $filename) {
            // Image URL : c'est le worker qui la telechargera, rien a verifier.
            return false;
        }

        $hypervisor = $os->getHypervisor() ? mb_strtolower((string) $os->getHypervisor()->getName()) : 'qemu';
        $key = 'lxc' === $hypervisor ? 'lxc' : 'qemu';

        return !in_array($filename, $workerImages[$key] ?? [], true);
    }

    /**
     * Interroge le premier worker disponible (`GET /os`).
     *
     * @return array{qemu: string[], lxc: string[]}|null
     */
    private function fetchWorkerImages(): ?array
    {
        $workers = $this->configWorkerRepository->findBy(['available' => true], ['id' => 'ASC']);
        if (empty($workers)) {
            return null;
        }

        foreach ($workers as $worker) {
            $url = sprintf('http://%s:%s/os', $worker->getIPv4(), $this->workerPort);
            try {
                $response = $this->httpClient->request('GET', $url);
                if (200 !== $response->getStatusCode()) {
                    continue;
                }
                $data = $response->toArray(false);
                if (!is_array($data)) {
                    continue;
                }

                return [
                    'qemu' => array_values(array_map('strval', $data['qemu'] ?? [])),
                    'lxc' => array_values(array_map('strval', $data['lxc'] ?? [])),
                ];
            } catch (Throwable $exception) {
                $this->logger->info('[ImageResolver:fetchWorkerImages]::Worker unreachable', [
                    'worker' => $worker->getIPv4(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return null;
    }

    private static function familyOf(string $value): ?string
    {
        $value = mb_strtolower($value);
        foreach (self::FAMILY_TOKENS as $family => $tokens) {
            foreach ($tokens as $token) {
                if (preg_match('/\b' . preg_quote($token, '/') . '/', $value)) {
                    return $family;
                }
            }
        }

        return null;
    }

    /**
     * Normalise un identifiant d'image : minuscules, suppression des suffixes de
     * version (-21H1v1, -2.6.0, -159-3.M3...) et des separateurs.
     */
    public static function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        // Basename quand il s'agit d'une URL.
        if (str_contains($value, '/')) {
            $value = basename(parse_url($value, PHP_URL_PATH) ?: $value);
        }
        // Retire les extensions de fichier connues.
        $value = preg_replace('/\.(qcow2|img|ova|tar|gz)$/', '', $value);
        // Retire les chiffres isoles et versions (tokens purement numeriques).
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        $tokens = array_filter(explode(' ', $value), static function (string $token): bool {
            return '' !== $token && !preg_match('/^[0-9.]+$/', $token);
        });

        return trim(implode(' ', $tokens));
    }

    private static function similarity(string $a, string $b): float
    {
        if ('' === $a || '' === $b) {
            return 0.0;
        }

        $percent = 0.0;
        similar_text($a, $b, $percent);

        return $percent;
    }
}
