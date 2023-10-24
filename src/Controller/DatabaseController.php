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
            if ($file !== '.' && $file !== '..'&& $file !== '.gitignore') {
                array_push($backups, $file);
            }
        }

        return $this->render('security/database.html.twig', [
            'backups' => $backups
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

            $response = new Response(file_get_contents('/opt/remotelabz/backups/'.$result));

            $disposition = HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $result
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
    * @Route("/admin/database/backup/download/{name}", name="admin_database_backup_download", requirements={"name"="database_backup_[\d]{2}_[\d]{2}_[\d]{4}_[\d]{2}_[\d]{2}_[\d]{2}"})
    * 
    */
    public function downloadBackup(Request $request, string $name)
    {
        $file = $name .".sql";
        $response = new Response(file_get_contents('/opt/remotelabz/backups/'.$file));

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $file
        );

        $response->headers->set('Content-Disposition', $disposition);

        return $response;

    }

}
