<?php

namespace App\Controller;


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class DatabaseController extends Controller
{
    /** @var LoggerInterface $logger */
    private $logger;

    public function __construct(
        ValidatorInterface $validator,
        LoggerInterface $logger)
    {
        $this->validator = $validator;
        $this->logger = $logger;
    }

    /**
    * @Route("/admin/database", name="admin_database")
    * 
    */
    public function indexAction(Request $request, SerializerInterface $serializer)
    {
        $files = scandir('/opt/remotelabz/backups/');
        $backups = [];
        foreach ($files as $file) {
            if (preg_match('/^.+\.zip$/', $file)) {
                array_push($backups, $file);
            }
        }

        
        $importBackupForm = $this->createFormBuilder([])
        ->add('file', FileType::class, [
            "help" => "Accepted formats: zip",
            "attr" => [
                "accepted" => ".zip",
            ]
        ])
        ->add('submit', SubmitType::class)
        ->getForm();

        $importBackupForm->handleRequest($request);

        if ($importBackupForm->isSubmitted() && $importBackupForm->isValid()) {
            $file = $importBackupForm->getData()['file'];

            $fileExtension = strtolower($file->getClientOriginalExtension());

            if (in_array($fileExtension, ['zip'])) {

                switch ($fileExtension) {
                    case 'zip':
                        $import = $this->importBackup($file);
                        break;
                }

                if ($import) {
                    $this->addFlash('success', 'Import succeed');
                } else {
                    $this->addFlash(
                        'warning',
                        'Import failed. Please try again.'
                    );
                }

            } else {
                $this->addFlash('danger', "Ce type de fichier n'est pas accepté.");
            }
            return $this->redirectToRoute('admin_database');

        }

        $listBackups= array_reverse($backups);
        return $this->render('security/database.html.twig', [
            'backups' => $listBackups,
            'importBackupForm' => $importBackupForm->createView(),
        ]);
    }

    /**
    * @Route("/admin/database/backup", name="admin_database_backup", methods="GET")
    * @Rest\Get("/api/database/backup", name="api_database_backup")
    * 
    */
    public function databaseBackup(Request $request)
    {

        $result=exec('php /opt/remotelabz/scripts/backupDatabase.php', $output);
        
        if ($result !== false) {

            $fileSystem = new FileSystem();

            //copy banner folder
            if ( file_exists( $this->getParameter('directory.public.upload.lab.banner') ) && is_dir( $this->getParameter('directory.public.upload.lab.banner') ) ) {
                $bannerSrc=$this->getParameter('directory.public.upload.lab.banner');
                $bannerDst='/opt/remotelabz/backups/'.$result.'/banner';
                $fileSystem->mirror($bannerSrc,$bannerDst); 
            } 
            
            //copy picture folder
            $pictureSrc='/opt/remotelabz/assets/js/components/Editor2/images/pictures/';
            $pictureDst='/opt/remotelabz/backups/'.$result.'/pictures';
            $fileSystem->mirror($pictureSrc,$pictureDst);

            //copy vm images
            if ( file_exists( $this->getParameter('image_directory') ) && is_dir( $this->getParameter('image_directory') ) ) {
                $imageSrc=$this->getParameter('image_directory');
                $imageDst='/opt/remotelabz/backups/'.$result.'/images';
                $fileSystem->mirror($imageSrc,$imageDst);      
            } 

            $zip = new ZipArchive();
            $rootPath = realpath('/opt/remotelabz/backups/'.$result);
            $zip->open('/opt/remotelabz/backups/' .$result.'.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($rootPath),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            

            foreach ($files as $name => $file)
            {
                // Skip directories (they would be added automatically)
                if (!$file->isDir())
                {
                    // Get real and relative path for current file
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($rootPath) + 1);

                    // Add current file to archive
                    $zip->addFile($filePath, $relativePath);
                }
            }

            // Zip archive will be created only after closing object
            $zip->close();
            $fileSystem->remove('/opt/remotelabz/backups/database_'.$result.'.sql');
            $fileSystem->remove('/opt/remotelabz/backups/'.$result);

            $response = new Response(file_get_contents('/opt/remotelabz/backups/' .$result.'.zip'));

            $disposition = HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $result.'.zip'
            );

            //$response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Content-Disposition', $disposition);

            return $response;
        }
        $this->addFlash('danger',"The backup failed. Please try again later");
        

        return $this->redirectToRoute('admin_database');
    }

    /**
    * 
    * @Route("/admin/database/backup/download/{name}", name="admin_database_backup_download", requirements={"name"="backup_[\d]{2}-[\d]{2}-[\d]{4}_[\d]{2}-[\d]{2}-[\d]{2}"})
    * 
    */
    public function downloadBackup(Request $request, string $name)
    {
        $file = $name .".zip";
        $response = new Response(file_get_contents('/opt/remotelabz/backups/'.$file));

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $file
        );

        $response->headers->set('Content-Disposition', $disposition);

        return $response;

    }

    /**
    * 
    * @Route("/admin/database/backup/delete/{name}", name="admin_database_backup_delete", requirements={"name"="backup_[\d]{2}-[\d]{2}-[\d]{4}_[\d]{2}-[\d]{2}-[\d]{2}"})
    * 
    */
    public function deleteBackup(Request $request, string $name)
    {
        $file = $name .".zip";
        unlink('/opt/remotelabz/backups/'.$file);

        return $this->redirectToRoute('admin_database');

    }

    public function importBackup($file)
    {
        $fileSystem = new FileSystem();
        $zip = new ZipArchive();
        $zip->open($file);
        $zip->extractTo('/opt/remotelabz/backups/import/');
        $zip->close();

        $result=exec('php /opt/remotelabz/bin/console doctrine:migrations:migrate --no-interaction', $output);

        if ($result !== false) {
            $resultImport=exec('php /opt/remotelabz/scripts/importDatabase.php', $output);
            if ($resultImport !== false) {

                $files = scandir('/opt/remotelabz/backups/import/');

                foreach ($files as $file) {
                    if (is_dir($file)) {
                        if ($file == "banner") {
                            //copy banner folder
                            $bannerSrc='/opt/remotelabz/backups/import/banner';
                            $bannerDst=$this->getParameter('directory.public.upload.lab.banner');
                            $fileSystem->mirror($bannerSrc,$bannerDst);
                        }
                        else if ($file == "pictures") {
                            //copy picture folder
                            $pictureSrc='/opt/remotelabz/backups/import/pictures';
                            $pictureDst='/opt/remotelabz/assets/js/components/Editor2/images/pictures/';
                            $fileSystem->mirror($pictureSrc,$pictureDst);
                        }
                        else if ($file == "images") {
                            //copy vm images
                            $imageSrc='/opt/remotelabz/backups/import/images';
                            $imageDst=$this->getParameter('image_directory');
                            $fileSystem->mirror($imageSrc,$imageDst);
                        }
                    }
                }               

            }
            else {
                $fileSystem->remove('/opt/remotelabz/backups/import');
                $this->addFlash('danger',"The database import failed. Please try again later");
                $this->logger->error('Migration failed: '.$output);
                return false;
            }
        }
        else {
            $fileSystem->remove('/opt/remotelabz/backups/import');
            $this->addFlash('danger',"The database import failed. Please try again later");
            $this->logger->error('Database import failed: '.$output);
            return false;
        }

        $fileSystem->remove('/opt/remotelabz/backups/import');
        return true;
    }

}
