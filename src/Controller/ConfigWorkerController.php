<?php

namespace App\Controller;

use App\Repository\ConfigWorkerRepository;
use App\Entity\ConfigWorker;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use FOS\RestBundle\Controller\Annotations as Rest;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;


class ConfigWorkerController extends Controller
{
    private $logger;
    private $workerRepository;
    private $serializer;

    public function __construct(
        LoggerInterface $logger=null,
        ConfigWorkerRepository $configWorkerRepository,
        SerializerInterface $serializer
    ) {
        $this->logger = $logger;
        if ($logger == null) {
            $this->logger = new Logger();
        }
        $this->configWorkerRepository = $configWorkerRepository;
        $this->serializer = $serializer;
    }


    /**
     * @Route("/admin/config", name="admin_config")
     * @Rest\Get("/api/config/workers", name="api_get_workers")
     */
    public function indexAction(Request $request)
    {

        $workers = $this->configWorkerRepository->findAll();
        $nbWorkers = count($workers);
        $workerProps = [
            "workers" => $workers,
            "nbWorkers" => $nbWorkers
        ];

        $props=$this->serializer->serialize(
            $workerProps,
            'json',
            SerializationContext::create()->setGroups(['api_get_worker_config'])
        );

        if ('json' === $request->getRequestFormat()) {
            return $this->json($workers, 200, [], ['api_get_worker_config']);
        }

        return $this->render('config.html.twig', [
            'props' => $props,
        ]);
    }

    /**
     * @Rest\Post("/api/config/worker/new", name="api_new_worker")
     */
    public function newAction(Request $request) {
        $data = json_decode($request->getContent(), true);

        $worker = new ConfigWorker();
        $worker->setIPv4($data['IPv4']);
        $worker->setAvailable(true);
        $worker->setQueueName("");

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($worker);
        $entityManager->flush();
        $worker->setQueueName("message_worker".$worker->getId());
        $entityManager->flush();

        return $this->json($worker, 200, [], ['api_get_worker_config']);
    }

    /**
     * @Rest\Put("/api/config/worker/{id<\d+>}", name="api_update_worker")
     */
    public function updateAction(Request $request, int $id) {
        $data = json_decode($request->getContent(), true);

        $worker = $this->configWorkerRepository->find(["id" => $id]);
        if (isset($data['IPv4'])) {
            $worker->setIPv4($data['IPv4']);
        }
        else if (isset($data['available'])) {
            $worker->setAvailable($data['available']);
        }

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->flush();

        return $this->json($worker, 200, [], ['api_get_worker_config']);
    }

    /**
     * @Rest\Delete("/api/config/worker/{id<\d+>}", name="api_delete_worker")
     */
    public function deleteAction(Request $request, int $id) {
        $data = json_decode($request->getContent(), true);

        $worker = $this->configWorkerRepository->find(["id" => $id]);

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($worker);
        $entityManager->flush(); 

        return $this->json();
    }

}
