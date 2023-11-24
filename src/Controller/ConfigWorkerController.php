<?php

namespace App\Controller;

use App\Repository\ConfigWorkerRepository;
use App\Repository\LabInstanceRepository;
use App\Entity\ConfigWorker;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use FOS\RestBundle\Controller\Annotations as Rest;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;




class ConfigWorkerController extends Controller
{
    private $logger;
    private $workerRepository;
    private $serializer;
    private $labInstanceRepository;

    public function __construct(
        LoggerInterface $logger=null,
        ConfigWorkerRepository $configWorkerRepository,
        LabInstanceRepository $labInstanceRepository,
        SerializerInterface $serializer
    ) {
        $this->logger = $logger;
        if ($logger == null) {
            $this->logger = new Logger();
        }
        $this->configWorkerRepository = $configWorkerRepository;
        $this->labInstanceRepository = $labInstanceRepository;
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
        $worker->setQueueName($this->getQueueName());

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($worker);
        $entityManager->flush();
        $this->logger->debug("worker ". $worker->getIPv4(). " has been created.");

        $this->createQueue($worker->getIPv4(), $worker->getQueueName());

        return $this->json($worker, 200, [], ['api_get_worker_config']);
    }

    private function getQueueName() {
        $workers = $this->configWorkerRepository->findAll();
        $numbers = [];
        foreach ($workers as $worker) {
            $number = preg_replace("/messages_worker/", "", $worker->getQueueName());
            array_push($numbers, $number);
        }
        $exit = false;
        $i = 1;

        while($exit == false) {
            if (in_array($i, $numbers)) {
                $i++;
            }
            else {
                $exit = true;
            }
        }
        $queueName = "messages_worker".$i;
        return $queueName;
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
        $this->logger->debug("worker ". $worker->getIPv4(). " has been updated.");

        if (isset($data['IPv4'])) {
            $this->bindQueue($worker->getIPv4(), $worker->getQueueName());
        }

        return $this->json($worker, 200, [], ['api_get_worker_config']);
    }

    /**
     * @Rest\Delete("/api/config/worker/{id<\d+>}", name="api_delete_worker")
     */
    public function deleteAction(Request $request, int $id) {
        $data = json_decode($request->getContent(), true);

        $worker = $this->configWorkerRepository->find(["id" => $id]);
        $labInstances = $this->labInstanceRepository->findByWorkerIP($worker->getIPv4());
        if (count($labInstances) != 0) {
            
            $this->logger->error('Worker '.$worker->getIPv4().' is used by an instance');
            throw new BadRequestHttpException('Worker '.$worker->getIPv4().' is used by an instance');
        }
        $queueName = $worker->getQueueName();

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($worker);
        $entityManager->flush(); 
        $this->logger->debug("worker ". $worker->getIPv4(). " has been deleted.");

        $this->deleteQueue($queueName);

        return $this->json();
    }

    private function createQueue($ipAdress, $queueName) {
        $cmd = ['rabbitmqadmin', "declare", "queue", "name=".$queueName];
        $process = new Process($cmd);

        $process->run();

        if (!$process->isSuccessful()) {
            $this->addFlash("danger", "The queue of worker ".$ipAdress. " has not been created");
            $this->logger->warning("The creation of the queue ". $queueName. " failed.");
            //throw new ProcessFailedException($process);
        }
        else {
            $this->logger->warning("The creation of the queue ". $queueName. " succeed.");
            $this->logger->debug($process->getOutput());
            $this->bindQueue($ipAdress, $queueName);
        }

        

    }

    private function deleteQueue($queueName) {
        $cmd = ['rabbitmqadmin', "delete", "queue", "name=".$queueName];

        $process = new Process($cmd);

        $process->run();

        if (!$process->isSuccessful()) {
            $this->addFlash("danger", "The queue of worker ".$ipAdress. " has not been deleted");
            $this->logger->warning("The deletion of the queue ". $queueName. " failed.");
            throw new ProcessFailedException($process);
        }
        else {
            $this->logger->debug($process->getOutput());
            $this->modifyMessengerConfig();
        }
    }

    private function bindQueue($ipAdress, $queueName) {

        $cmd = ['rabbitmqadmin', "declare", "binding", "source=worker", "destination_type=queue" , "destination=".$queueName, "routing_key=".$ipAdress];

        $process = new Process($cmd);

        $process->run();

        if (!$process->isSuccessful()) {
            $this->addFlash("danger", "The binding of worker ".$ipAdress. " failed");
            $this->logger->warning("The binding of worker ". $queueName. " failed.");
            throw new ProcessFailedException($process);
        }
        else {
            $this->logger->debug("The binding of worker ". $queueName. " succeed.");
            $this->modifyMessengerConfig();
        }
    }

    private function modifyMessengerConfig() {
        $yaml = Yaml::parse(file_get_contents('/opt/remotelabz/config/packages/messenger.yaml'));

        $queues = [];
        $workers = $this->configWorkerRepository->findAll();
        foreach($workers as $worker) {
            $queues[$worker->getQueueName()] = [
                "binding_keys" => [$worker->getIPv4()]
            ];
        }
        $yaml["framework"]["messenger"]["transports"]["worker"]["options"]["queues"] = $queues;

        
        $new_yaml = Yaml::dump($yaml, 8);
        file_put_contents('/opt/remotelabz/config/packages/messenger.yaml', $new_yaml);
    }

}