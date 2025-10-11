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
    }

    /**
     * Récupère la version complète (fichier + commit + branche) avec cache
     */
    public function getFullVersion(): array
    {
        return $this->cache->get('git_version_full', function () {
            $commit = $this->getCommitHashShort();
            $result=[
                'version_file' => $this->getVersionFromFile(),
                'commit' => $this->getCommitHash(),
                'branch' => $this->getBranchName(),
                'commit_short' => $commit,
                'commit_url' => $this->getCommitUrl($commit),
                'github_url' => $this->githubRepository,
            ];
            $this->logger->debug('[GitVersionService:getFullVersion] Git version retrieved', [
                'version_file' => $result['version_file'],
                'commit_short' => $result['commit_short'],
                'branch' => $result['branch'],
                'commit_url' => $result['commit_url'],
            ]);
            return $result;
        });
    }

    /**
     * Récupère le contenu du fichier version
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
     * Récupère le hash du commit actuel
     */
    private function getCommitHash(): string
    {
        return $this->executeGitCommand('git rev-parse HEAD');
    }

    /**
     * Récupère le hash court du commit
     */
    private function getCommitHashShort(): string
    {
        return $this->executeGitCommand('git rev-parse --short HEAD');
    }

    /**
     * Récupère le nom de la branche actuelle
     */
    private function getBranchName(): string
    {
        return $this->executeGitCommand('git rev-parse --abbrev-ref HEAD');
    }

    /**
     * Exécute une commande Git
     */
    private function executeGitCommand(string $command): string
    {
        try {
            // Séparer stdout et stderr
            $output = shell_exec('cd ' . escapeshellarg($this->projectDir) . ' && ' . $command . ' 2>/dev/null');
            
            if ($output === null || empty(trim($output))) {
                throw new \Exception('Command returned empty or failed');
            }

            $result = trim($output);
            $this->logger->debug("[GitVersionService:executeGitCommand]::Output of git command ".$output);
            // Vérifier si le résultat contient des messages d'erreur
            if ($this->isErrorMessage($result)) {
                throw new \Exception('Git command returned error message');
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->warning('Git command failed', [
                'command' => $command,
                'exception' => $e->getMessage()
            ]);
            return 'unknown';
        }
    }

    /**
     * Vérifie si le message contient une erreur Git
     */
    private function isErrorMessage(string $message): bool
    {
        $errorPatterns = [
            'fatal:',
            'error:',
            'not a git repository',
            'dubious ownership',
            'detected dubious',
            'safe.directory',
            'permission denied',
        ];

        $lowerMessage = strtolower($message);
        
        foreach ($errorPatterns as $pattern) {
            if (strpos($lowerMessage, strtolower($pattern)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Récupère une représentation formatée de la version
     */
    public function getFormattedVersion(): string
    {
        $data = $this->getFullVersion();
         $result=sprintf(
            '%s (commit: %s, branch: %s)',
            $data['version_file'],
            $data['commit_short'],
            $data['branch']
        );

        return $result;
    }

    /**
     * Génère l'URL du commit sur GitHub
     */
    private function getCommitUrl(string $commitHash): string
    {
        return sprintf('%s/commit/%s', rtrim($this->githubRepository, '/'), $commitHash);
    }

    /**
     * Invalide le cache (utile lors d'un déploiement)
     */
    public function invalidateCache(): void
    {
        $this->cache->delete('git_version_full');
        $this->logger->info('Git version cache invalidated');
    }
}