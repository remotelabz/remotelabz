<?php

namespace App\Service;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Psr\Log\LoggerInterface;

class GitVersionService
{
    private $kernel;
    private $logger;
    private $cache;
    private $projectDir;
    private $cacheExpiry;
    private $githubRepository;
    private $gitVersionFile;

    public function __construct(
        KernelInterface $kernel,
        LoggerInterface $logger,
        CacheInterface $cache,
        string $projectDir,
        string $githubRepository,
        int $cacheExpiry = 3600
    )
    {
        $this->kernel = $kernel;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->projectDir = $projectDir;
        $this->githubRepository = $githubRepository;
        $this->cacheExpiry = $cacheExpiry;
        $this->gitVersionFile = $projectDir . '/var/git-version.json';
    }

    /**
     * Récupère la version complète (fichier + commit + branche) avec cache
     */
    public function getFullVersion(): array
    {
        return $this->cache->get('git_version_full', function () {
            return $this->readGitVersionFile();
        });
    }

    /**
     * Lit le fichier JSON généré par systemd
     */
    private function readGitVersionFile(): array
    {
        try {
            if (!file_exists($this->gitVersionFile)) {
                $this->logger->warning('Git version file not found', [
                    'file' => $this->gitVersionFile
                ]);
                return $this->getFallbackVersion();
            }

            $content = file_get_contents($this->gitVersionFile);
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('Failed to parse git version file', [
                    'file' => $this->gitVersionFile,
                    'error' => json_last_error_msg()
                ]);
                return $this->getFallbackVersion();
            }

            // Vérifier que toutes les clés nécessaires sont présentes
            $requiredKeys = ['version_file', 'commit', 'commit_short', 'branch', 'commit_url', 'github_url'];
            foreach ($requiredKeys as $key) {
                if (!isset($data[$key])) {
                    $this->logger->warning('Missing key in git version file', [
                        'key' => $key,
                        'file' => $this->gitVersionFile
                    ]);
                    return $this->getFallbackVersion();
                }
            }

            $this->logger->debug('[GitVersionService:readGitVersionFile] Git version data loaded', $data);

            return $data;

        } catch (\Exception $e) {
            $this->logger->error('Error reading git version file', [
                'exception' => $e->getMessage(),
                'file' => $this->gitVersionFile
            ]);
            return $this->getFallbackVersion();
        }
    }

    /**
     * Retourne une version par défaut en cas d'erreur
     */
    private function getFallbackVersion(): array
    {
        $version = $this->getVersionFromFile();
        
        return [
            'version_file' => $version,
            'commit' => 'unknown',
            'commit_short' => 'unknown',
            'branch' => 'unknown',
            'commit_url' => $this->githubRepository,
            'github_url' => $this->githubRepository,
            'updated_at' => date('c')
        ];
    }

    /**
     * Récupère le contenu du fichier version (fallback)
     */
    private function getVersionFromFile(): string
    {
        try {
            $versionFile = $this->projectDir . '/version';
            if (file_exists($versionFile)) {
                return trim(file_get_contents($versionFile));
            }
            return 'unknown';
        } catch (\Exception $e) {
            $this->logger->error('Error reading version file', ['exception' => $e]);
            return 'error';
        }
    }

    /**
     * Récupère une représentation formatée de la version
     */
    public function getFormattedVersion(): string
    {
        $data = $this->getFullVersion();
        
        if ($data['commit_short'] === 'unknown') {
            return $data['version_file'];
        }

        return sprintf(
            '%s (commit: %s, branch: %s)',
            $data['version_file'],
            $data['commit_short'],
            $data['branch']
        );
    }

    /**
     * Invalide le cache (utile lors d'un déploiement)
     */
    public function invalidateCache(): void
    {
        $this->cache->delete('git_version_full');
        $this->logger->info('Git version cache invalidated');
    }

    /**
     * Force la mise à jour du fichier de version
     * À appeler manuellement après un déploiement
     */
    public function triggerUpdate(): bool
    {
        try {
            // Déclencher manuellement le service systemd
            exec('sudo systemctl start git-version-update.service 2>&1', $output, $returnCode);
            
            if ($returnCode === 0) {
                $this->invalidateCache();
                $this->logger->info('Git version update triggered successfully');
                return true;
            }
            
            $this->logger->error('Failed to trigger git version update', [
                'return_code' => $returnCode,
                'output' => implode("\n", $output)
            ]);
            return false;
            
        } catch (\Exception $e) {
            $this->logger->error('Exception while triggering git version update', [
                'exception' => $e->getMessage()
            ]);
            return false;
        }
    }
}