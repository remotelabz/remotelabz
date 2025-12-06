#!/usr/bin/env php
<?php

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

require dirname(__DIR__).'/vendor/autoload.php';

// Charger les variables d'environnement
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

// Récupérer l'email passé en paramètre
$userEmail = $argv[1] ?? null;

$date = date('d-m-Y_H-i-s');
$filename = 'database_backup_'.$date.'.sql';
$fileSystem = new Filesystem();
$folderName = 'backup_'.$date;
$backupPath = dirname(__DIR__).'/backups/'.$folderName;

try {
    // Créer le dossier de backup
    $fileSystem->mkdir($backupPath);

    // Se connecter à la base de données
    $mysqli = new mysqli(
        $_SERVER['MYSQL_SERVER'], 
        $_SERVER['MYSQL_USER'], 
        $_SERVER['MYSQL_PASSWORD'], 
        $_SERVER['MYSQL_DATABASE']
    );

    if ($mysqli->connect_error) {
        throw new Exception('Erreur de connexion à la base de données: ' . $mysqli->connect_error);
    }

    // Récupérer les tables
    $lines = $mysqli->query('SHOW TABLES');
    $tables = "";
    foreach($lines as $line) {
        if(count(explode("instance", $line["Tables_in_remotelabz"])) == 1){ 
            $tables .= $line["Tables_in_remotelabz"] ." ";
        }
    }

    // Effectuer le dump SQL
    $dumpCommand = sprintf(
        'mysqldump %s --password=%s --user=%s --host=%s --no-tablespaces %s > %s/%s',
        escapeshellarg($_SERVER['MYSQL_DATABASE']),
        escapeshellarg($_SERVER['MYSQL_PASSWORD']),
        escapeshellarg($_SERVER['MYSQL_USER']),
        escapeshellarg($_SERVER['MYSQL_SERVER']),
        $tables,
        escapeshellarg($backupPath),
        escapeshellarg($filename)
    );
    
    exec($dumpCommand, $output, $returnCode);
    
    if ($returnCode !== 0) {
        throw new Exception('Erreur lors du dump de la base de données');
    }

    // Copier les dossiers supplémentaires (banner, pictures, images)
    $projectDir = dirname(__DIR__);
    
    // Banner
    $bannerSrc = $projectDir . '/public/uploads/lab/banner';
    if (file_exists($bannerSrc) && is_dir($bannerSrc)) {
        $fileSystem->mirror($bannerSrc, $backupPath . '/banner');
    }
    
    // Pictures
    $pictureSrc = $projectDir . '/assets/js/components/Editor2/images/pictures';
    if (file_exists($pictureSrc) && is_dir($pictureSrc)) {
        $fileSystem->mirror($pictureSrc, $backupPath . '/pictures');
    }
    
    // Images
    $imageSrc = $_SERVER['IMAGE_DIRECTORY'] ?? null;
    if ($imageSrc && file_exists($imageSrc) && is_dir($imageSrc)) {
        $fileSystem->mirror($imageSrc, $backupPath . '/images');
    }

    // Créer le fichier ZIP
    $zip = new ZipArchive();
    $zipPath = $projectDir . '/backups/' . $folderName . '.zip';
    
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new Exception('Impossible de créer le fichier ZIP');
    }

    $rootPath = realpath($backupPath);
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootPath),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($rootPath) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }

    $zip->close();

    // Nettoyer les fichiers temporaires
    $fileSystem->remove($backupPath);

    // Calculer la taille du fichier
    $fileSize = filesize($zipPath);
    $fileSizeMB = round($fileSize / 1024 / 1024, 2);

    // Envoyer l'email de succès
    if ($userEmail) {
        sendEmail(
            $userEmail, 
            'Backup de la base de données terminé avec succès',
            "Le backup de la base de données a été créé avec succès.\n\n" .
            "Fichier: {$folderName}.zip\n" .
            "Taille: {$fileSizeMB} MB\n" .
            "Date: " . date('d/m/Y H:i:s') . "\n\n" .
            "Vous pouvez le télécharger depuis l'interface d'administration."
        );
    }

    echo $folderName;
    exit(0);

} catch (Exception $e) {
    // En cas d'erreur, nettoyer et envoyer un email d'erreur
    if (file_exists($backupPath)) {
        $fileSystem->remove($backupPath);
    }
    
    if ($userEmail) {
        sendEmail(
            $userEmail,
            'Erreur lors du backup de la base de données',
            "Une erreur est survenue lors de la création du backup:\n\n" .
            $e->getMessage() . "\n\n" .
            "Date: " . date('d/m/Y H:i:s')
        );
    }
    
    error_log('Backup failed: ' . $e->getMessage());
    exit(1);
}

/**
 * Fonction pour envoyer un email
 */
function sendEmail($to, $subject, $body) {
    try {
        // Utiliser le DSN de Mailer configuré dans .env
        $dsn = $_SERVER['MAILER_DSN'] ?? 'smtp://localhost';
        $transport = Transport::fromDsn($dsn);
        $mailer = new Mailer($transport);

        $email = (new Email())
            ->from($_SERVER['MAILER_FROM'] ?? 'noreply@remotelabz.com')
            ->to($to)
            ->subject($subject)
            ->text($body);

        $mailer->send($email);
        
    } catch (Exception $e) {
        error_log('Failed to send email: ' . $e->getMessage());
    }
}