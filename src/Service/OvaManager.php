<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class OvaManager
{
    private Filesystem $filesystem;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->filesystem = new Filesystem();
        $this->logger = $logger;
    }

    /**
     * Trouve tous les fichiers VMDK dans un répertoire
     */
    public function findVmdkFiles(string $directory): array
    {
        if (!$this->filesystem->exists($directory)) {
            throw new \RuntimeException("Le répertoire n'existe pas : $directory");
        }

        $finder = new Finder();
        $finder->files()->in($directory)->name('*.vmdk')->sortByName();

        $files = [];
        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }

        $this->logger->info(sprintf('Trouvé %d fichier(s) VMDK dans %s', count($files), $directory));
        
        return $files;
    }

    /**
     * Convertit un fichier VMDK en QCOW2 avec qemu-img
     */
    public function convertVmdkToQcow2(string $vmdkPath, string $qcow2file): string
    {
        if (!$this->filesystem->exists($vmdkPath)) {
            throw new \RuntimeException("Le fichier VMDK n'existe pas : $vmdkPath");
        }

        $qcow2file=pathinfo($vmdkPath)["dirname"]."/".$qcow2file.".qcow2";
        $this->logger->info("Conversion de ".$vmdkPath." vers ".$qcow2file);
     

        $process = new Process([
            'qemu-img',
            'convert',
            '-f', 'vmdk',
            '-O', 'qcow2',
            $vmdkPath,
            $qcow2file
        ]);

        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $this->logger->info("Conversion réussie : ".$qcow2file);
        return $qcow2file;
    }

    /**
     * Fusionne plusieurs fichiers VMDK en un seul (si nécessaire) puis convertit en QCOW2
     */
    public function processVmdkFiles(string $extractedDir): string
    {
        // 1. Trouver tous les fichiers VMDK
        $vmdkFiles = $this->findVmdkFiles($extractedDir);
        $this->logger->info("ExtractedDir: $extractedDir");

        if (empty($vmdkFiles)) {
            throw new \RuntimeException("No VMDK file found in ".$extractedDir);
        }

        // 2. Déterminer le fichier VMDK principal
        // Si plusieurs fichiers, on cherche celui qui n'a pas de suffixe numérique (le descriptor)
        $mainVmdk = $this->findMainVmdk($vmdkFiles);
        
        $this->logger->info("Fichier VMDK principal : ".basename($mainVmdk));

        // 3. Convertir en QCOW2
        $source=basename($mainVmdk,'.vmdk');
        $newfile = preg_replace('/([^-]+)-disk\d+/', '$1', $source);
        $this->logger->debug("[OvaManager:processVmdkFiles]::source:".$source." newfile:".$newfile);
        return $this->convertVmdkToQcow2($mainVmdk, $newfile);

        
    }

    /**
     * Trouve le fichier VMDK principal (descriptor) parmi plusieurs fichiers
     */
    private function findMainVmdk(array $vmdkFiles): string
    {
        if (count($vmdkFiles) === 1) {
            return $vmdkFiles[0];
        }

        // Chercher le fichier descriptor (celui sans suffixe -s001, -s002, etc.)
        foreach ($vmdkFiles as $file) {
            $basename = basename($file);
            // Le fichier principal ne contient généralement pas de pattern -s[0-9]+
            if (!preg_match('/-s\d+\.vmdk$/i', $basename)) {
                return $file;
            }
        }

        // Si aucun descriptor n'est trouvé, prendre le premier
        $this->logger->warning('No VMDK descriptor found, first file is used');
        return $vmdkFiles[0];
    }

    /**
     * Déplace un fichier vers une destination
     */
    public function moveFile(string $sourcePath, string $destinationPath): void
    {
        $destinationDir = dirname($destinationPath);
        
        if (!$this->filesystem->exists($destinationDir)) {
            $this->filesystem->mkdir($destinationDir, 0755);
        }

        $this->filesystem->rename($sourcePath, $destinationPath, true);
        $this->logger->info("Fichier déplacé de $sourcePath vers $destinationPath");
    }

    /**
     * Copie un fichier vers une destination
     */
    public function copyFile(string $sourcePath, string $destinationPath): void
    {
        $destinationDir = dirname($destinationPath);
        
        if (!$this->filesystem->exists($destinationDir)) {
            $this->filesystem->mkdir($destinationDir, 0755);
        }

        $this->filesystem->copy($sourcePath, $destinationPath, true);
        $this->logger->info("Fichier copié de $sourcePath vers $destinationPath");
    }
}