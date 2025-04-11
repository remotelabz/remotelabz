<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;
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
use Symfony\Component\Security\Http\Attribute\IsGranted;

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

    #[Route(path: '/admin/database', name: 'admin_database')]
    public function indexAction(Request $request, SerializerInterface $serializer)
    {
        $files = scandir($this->getParameter('kernel.project_dir').'/backups/');
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

    
	#[Get('/api/database/backup', name: 'api_database_backup')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    #[Route(path: '/admin/database/backup', name: 'admin_database_backup', methods: 'GET')]
    public function databaseBackup(Request $request)
    {

        $result=exec('php '.$this->getParameter('kernel.project_dir').'/scripts/backupDatabase.php', $output);
        
        if ($result !== false) {

            $fileSystem = new FileSystem();

            //copy banner folder
            if ( file_exists( $this->getParameter('directory.public.upload.lab.banner') ) && is_dir( $this->getParameter('directory.public.upload.lab.banner') ) ) {
                $bannerSrc=$this->getParameter('directory.public.upload.lab.banner');
                $bannerDst=$this->getParameter('kernel.project_dir').'/backups/'.$result.'/banner';
                $fileSystem->mirror($bannerSrc,$bannerDst); 
            } 
            
            //copy picture folder
            $pictureSrc=$this->getParameter('kernel.project_dir').'/assets/js/components/Editor2/images/pictures';
            $pictureDst=$this->getParameter('kernel.project_dir').'/backups/'.$result.'/pictures';
            $fileSystem->mirror($pictureSrc,$pictureDst);

            //copy vm images
            if ( file_exists( $this->getParameter('image_directory') ) && is_dir( $this->getParameter('image_directory') ) ) {
                $imageSrc=$this->getParameter('image_directory');
                $imageDst=$this->getParameter('kernel.project_dir').'/backups/'.$result.'/images';
                $fileSystem->mirror($imageSrc,$imageDst);      
            } 

            $zip = new ZipArchive();
            $rootPath = realpath($this->getParameter('kernel.project_dir').'/backups/'.$result);
            $zip->open($this->getParameter('kernel.project_dir').'/backups/' .$result.'.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);
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
            $fileSystem->remove($this->getParameter('kernel.project_dir').'/backups/database_'.$result.'.sql');
            $fileSystem->remove($this->getParameter('kernel.project_dir').'/backups/'.$result);

            $response = new Response(file_get_contents($this->getParameter('kernel.project_dir').'/backups/' .$result.'.zip'));

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

    #[Route(path: '/admin/database/backup/download/{name}', name: 'admin_database_backup_download', requirements: ['name' => 'backup_[\d]{2}-[\d]{2}-[\d]{4}_[\d]{2}-[\d]{2}-[\d]{2}'])]
    public function downloadBackup(Request $request, string $name)
    {
        $file = $name .".zip";
        $response = new Response(file_get_contents($this->getParameter('kernel.project_dir').'/backups/'.$file));

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $file
        );

        $response->headers->set('Content-Disposition', $disposition);

        return $response;

    }

    #[Route(path: '/admin/database/backup/delete/{name}', name: 'admin_database_backup_delete', requirements: ['name' => 'backup_[\d]{2}-[\d]{2}-[\d]{4}_[\d]{2}-[\d]{2}-[\d]{2}'])]
    public function deleteBackup(Request $request, string $name)
    {
        $file = $name .".zip";
        unlink($this->getParameter('kernel.project_dir').'/backups/'.$file);

        return $this->redirectToRoute('admin_database');

    }

    public function importBackup($file)
    {
        $fileSystem = new FileSystem();
        $zip = new ZipArchive();
        $zip->open($file);
        $zip->extractTo($this->getParameter('kernel.project_dir').'/backups/import/');
        $zip->close();

        $result=exec('php '.$this->getParameter('kernel.project_dir').'/bin/console doctrine:migrations:migrate --no-interaction', $output);

        if ($result !== false) {
            $resultImport=exec('php '.$this->getParameter('kernel.project_dir').'/scripts/importDatabase.php', $output);
            if ($resultImport !== false) {

                $files = scandir($this->getParameter('kernel.project_dir').'/backups/import/');

                foreach ($files as $file) {
                    if (is_dir($file)) {
                        if ($file == "banner") {
                            //copy banner folder
                            $bannerSrc=$this->getParameter('kernel.project_dir').'/backups/import/banner';
                            $bannerDst=$this->getParameter('directory.public.upload.lab.banner');
                            $fileSystem->mirror($bannerSrc,$bannerDst);
                        }
                        else if ($file == "pictures") {
                            //copy picture folder
                            $pictureSrc=$this->getParameter('kernel.project_dir').'/backups/import/pictures';
                            $pictureDst=$this->getParameter('kernel.project_dir').'/assets/js/components/Editor2/images/pictures';
                            $fileSystem->mirror($pictureSrc,$pictureDst);
                        }
                        else if ($file == "images") {
                            //copy vm images
                            $imageSrc=$this->getParameter('kernel.project_dir').'/backups/import/images';
                            $imageDst=$this->getParameter('image_directory');
                            $fileSystem->mirror($imageSrc,$imageDst);
                        }
                    }
                }
                
                $templates = scandir($this->getParameter('kernel.project_dir').'/config/templates/');
                foreach($templates as $template) {                
                    if(is_file($this->getParameter('kernel.project_dir').'/config/templates/'.$template) && preg_match('/^.+\.yaml$/', $template)) {
                        $fileSystem->remove($this->getParameter('kernel.project_dir').'/config/templates/'.$template);
                    } 
                }

            }
            else {
                $fileSystem->remove($this->getParameter('kernel.project_dir').'/backups/import');
                $this->addFlash('danger',"The database import failed. Please try again later");
                $this->logger->error('Migration failed: '.$output);
                return false;
            }
        }
        else {
            $fileSystem->remove($this->getParameter('kernel.project_dir').'/backups/import');
            $this->addFlash('danger',"The database import failed. Please try again later");
            $this->logger->error('Database import failed: '.$output);
            return false;
        }

        $fileSystem->remove($this->getParameter('kernel.project_dir').'/backups/import');
        return true;
    }

}
