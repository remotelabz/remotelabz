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
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Remotelabz\Message\Message\InstanceActionMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use App\Repository\OperatingSystemRepository;
use App\Entity\OperatingSystem;
use App\Service\Worker\WorkerManager;
use Doctrine\ORM\EntityManagerInterface;

class ConfigWorkerController extends Controller
{
    private $logger;
    private $workerRepository;
    private $serializer;
    private $labInstanceRepository;
    private $bus;
    private $operatingSystemRepository;
    private $workerManager;

    public function __construct(
        LoggerInterface $logger=null,
        ConfigWorkerRepository $configWorkerRepository,
        LabInstanceRepository $labInstanceRepository,
        SerializerInterface $serializer,
        MessageBusInterface $bus,
        OperatingSystemRepository $operatingSystemRepository,
        WorkerManager $workerManager,
        EntityManagerInterface $entityManager
        
    ) {
        $this->logger = $logger;
        if ($logger == null) {
            $this->logger = new Logger();
        }
        $this->configWorkerRepository = $configWorkerRepository;
        $this->labInstanceRepository = $labInstanceRepository;
        $this->serializer = $serializer;
        $this->bus = $bus;
        $this->operatingSystemRepository = $operatingSystemRepository;
        $this->workerManager = $workerManager;
        $this->entityManager = $entityManager;
    }

    
	#[Get('/api/config/workers', name: 'api_get_workers')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    #[Route(path: '/admin/config', name: 'admin_config')]
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

    
	#[Post('/api/config/worker/new', name: 'api_new_worker')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    public function newAction(Request $request) {
        $data = json_decode($request->getContent(), true);

        $worker = new ConfigWorker();
        $worker->setIPv4($data['IPv4']);
        $worker->setAvailable(true);
        $worker->setQueueName($this->getQueueName());

        $entityManager = $this->entityManager;
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

    
	#[Put('/api/config/worker/{id<\d+>}', name: 'api_update_worker')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    public function updateAction(Request $request, int $id) {
        $workerPort = $this->getParameter('app.worker_port');
        $data = json_decode($request->getContent(), true);

        $worker = $this->configWorkerRepository->find(["id" => $id]);
        
        $workers = $this->configWorkerRepository->findAll();
        
        
        $i=0;
        while ($i<count($workers)-1 && $workers[$i]->getAvailable()==0) {
            $i++;
        }
        $first_available_worker=$workers[$i];
        $this->logger->debug("Worker ". $first_available_worker->getIPv4(). " is the first available worker.");
        
        if (isset($data['IPv4'])) {
            $worker->setIPv4($data['IPv4']);
        }
        else if (isset($data['available'])) {
            if ($this->checkWorkerAvailable($worker->getIPv4(),$workerPort)) {
                $worker->setAvailable($data['available']); 
                $available=($worker->getAvailable()==1)?"Available":"Disable";
                $this->logger->info("Worker ". $worker->getIPv4(). " has been updated (".$available.").");    
                //$this->addFlash('success', 'Worker has been enabled');

                if ($data['available'] == 1) {
                    $operatingSystems=$this->operatingSystemRepository->findAll();
                    $OS_available_worker=$this->getOS_Worker($worker->getIPv4(),$workerPort); //Find OS available on the worker which is enabled
                    //$this->logger->debug("OS on enabled worker ". $worker->getIPv4(). ":".$workerPort.": ".$OS_available_worker);
                    if ($OS_available_worker) {
                        $OS_already_exist_on_worker=json_decode($OS_available_worker,true);

                        //$this->logger->debug("List of OS",$OS_already_exist_on_worker["lxc"]);

                        foreach ($operatingSystems as $operatingSystem) {
                          $this->logger->debug("OS to sync. Test for ".$operatingSystem->getName()." ".$operatingSystem->getHypervisor()->getName());

                            if (in_array($operatingSystem->getImageFilename(),$OS_already_exist_on_worker["lxc"]))
                                $this->logger->debug($operatingSystem->getName()." is in the array");
                            else
                                $this->logger->debug($operatingSystem->getName()." is NOT in the array");

                            if ($operatingSystem->getHypervisor()->getName() === "lxc" && !in_array($operatingSystem->getImageFilename(),$OS_already_exist_on_worker["lxc"])) {
                                $tmp=array();
                                $tmp['Worker_Dest_IP'] = $worker->getIPv4();
                                $tmp['hypervisor'] = $operatingSystem->getHypervisor()->getName();
                                $tmp['os_imagename'] = $operatingSystem->getImageFilename();
                                $deviceJsonToCopy = json_encode($tmp, 0, 4096);

                                $this->logger->debug("OS to sync:",$tmp);
                                $this->bus->dispatch(
                                    new InstanceActionMessage($deviceJsonToCopy, "", InstanceActionMessage::ACTION_COPY2WORKER_DEV), [
                                        new AmqpStamp($first_available_worker->getIPv4(), AMQP_NOPARAM, []),
                                        //new AmqpStamp("192.168.11.132", AMQP_NOPARAM, []),
                                    ]
                                );

                            }
                            if ($operatingSystem->getHypervisor()->getName() === "qemu" && !in_array($operatingSystem->getImageFilename(),$OS_already_exist_on_worker["qemu"])) {
                                $tmp=array();
                                $tmp['Worker_Dest_IP'] = $worker->getIPv4();
                                $tmp['hypervisor'] = $operatingSystem->getHypervisor()->getName();
                                $tmp['os_imagename'] = $operatingSystem->getImageFilename();
                                $deviceJsonToCopy = json_encode($tmp, 0, 4096);
                                if (!is_null($operatingSystem->getImageFilename())) { // the case of qemu image with link.
                                $this->logger->debug("OS to sync from ".$first_available_worker->getIPv4()." -> ".$tmp['Worker_Dest_IP'],$tmp);
                                $this->bus->dispatch(
                                    new InstanceActionMessage($deviceJsonToCopy, "", InstanceActionMessage::ACTION_COPY2WORKER_DEV), [
                                        new AmqpStamp($first_available_worker->getIPv4(), AMQP_NOPARAM, []),
                                        //new AmqpStamp("192.168.11.132", AMQP_NOPARAM, []),
                                        ]
                                    );
                                }
                            }
                        }
                    } else {
                        $this->logger->info("The worker ".$worker->getIPv4()." is not online. Perhaps it's power off");
                        //$this->addFlash("danger", "This worker seems not to be online");
                    }
                }
            }
            else {
                $this->logger->info("Worker ". $worker->getIPv4(). " is offline");
            }
        }

        $entityManager = $this->entityManager;
        $entityManager->flush();

        if (isset($data['IPv4'])) {
            $this->bindQueue($worker->getIPv4(), $worker->getQueueName());
        }

        return $this->json($worker, 200, [], ['api_get_worker_config']);
    }

    
	#[Delete('/api/config/worker/{id<\d+>}', name: 'api_delete_worker')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    public function deleteAction(Request $request, int $id) {
        $data = json_decode($request->getContent(), true);

        $worker = $this->configWorkerRepository->find(["id" => $id]);
        $labInstances = $this->labInstanceRepository->findByWorkerIP($worker->getIPv4());
        if (count($labInstances) != 0) {
            
            $this->logger->error('Worker '.$worker->getIPv4().' is used by an instance');
            throw new BadRequestHttpException('Worker '.$worker->getIPv4().' is used by an instance');
        }
        $queueName = $worker->getQueueName();

        $entityManager = $this->entityManager;
        $entityManager->remove($worker);
        $entityManager->flush(); 
        $this->logger->debug("Worker ". $worker->getIPv4(). " has been deleted.");

        $this->deleteQueue($queueName);

        return $this->json();
    }

    private function createQueue($ipAdress, $queueName) {        
        if ($this->queueExists($queueName)) {
            $this->logger->info("The creation of the queue ". $queueName. " succeed.");
        } else
        {
            // $cmd = ['rabbitmqadmin', "declare", "queue", "name=".$queueName,"type=direct"];
            // $process = new Process($cmd);

            // $process->run();

            // if (!$process->isSuccessful()) {
            //     $this->addFlash("danger", "The queue of worker ".$ipAdress. " has not been created");
            //     $this->logger->error("The creation of the queue ". $queueName. " failed.");
            //     //throw new ProcessFailedException($process);
            // }
            // else {
            //     $this->logger->info("The creation of the queue ". $queueName. " succeed.");
            //     $this->logger->debug($process->getOutput());
            //     $this->bindQueue($ipAdress, $queueName);
            // }


            $cmd = ['rabbitmqadmin', 'declare', 'queue', "name=$queueName", 'durable=true'];
            try {
                $process = new Process($cmd);
                $process->run();
            
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
            
                $this->logger->info("La création de la queue $queueName a réussi.");
                $this->bindQueue($ipAdress, $queueName);
            } catch (ProcessFailedException $exception) {
                $this->logger->error("La création de la queue $queueName a échoué : " . $exception->getMessage());
                throw $exception;
            }
            
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

    private function queueExists($queueName) {
        //$this->logger->debug("Test if ". $queueName. " exist.");

        $command = "rabbitmqadmin list queues name | grep \"$queueName\"";
        $output = shell_exec($command);
    
        // Vérifie si la sortie contient le nom de la queue
        return strpos($output, $queueName) !== false;
    }

    
    private function modifyMessengerConfig() {
        $yaml = Yaml::parse(file_get_contents($this->getParameter('kernel.project_dir').'/config/packages/messenger.yaml'));

        $queues = [];
        $workers = $this->configWorkerRepository->findAll();
        foreach($workers as $worker) {
            $queues[$worker->getQueueName()] = [
                "binding_keys" => [$worker->getIPv4()]
            ];
        }
        $yaml["framework"]["messenger"]["transports"]["worker"]["options"]["queues"] = $queues;

        
        $new_yaml = Yaml::dump($yaml, 8);
        file_put_contents($this->getParameter('kernel.project_dir').'/config/packages/messenger.yaml', $new_yaml);
    }

    //Return a JSON with all OS on this given worker
    private function getOS_Worker(string $workerIP, $workerPort) {
        $client = new Client();
        $url = "http://".$workerIP.":".$workerPort."/os";

        try {
            $response = $client->get($url);
        } catch (Exception $exception) {
            $this->logger->info("Worker ".$workerIP." is not responding:: ".$exception->getMessage());
            return false;
        }
        //$this->logger->debug("OS available on worker ".$workerIP." ".$response->getBody()->getContents());
        return $response->getBody()->getContents();
    }

    private function checkWorkerAvailable(string $workerIP, $workerPort) {
        $client = new Client();
        $url = "http://".$workerIP.":".$workerPort."/os";

        try {
            $response = $client->get($url);
        } catch (Exception $exception) {
            $this->logger->info("Worker ".$workerIP." is not responding: ".$exception->getMessage());
            return false;
        }
        //$this->logger->debug("OS available on worker ".$workerIP." ".$response->getBody()->getContents());
        return true;
    }

}