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
use Symfony\Component\ExpressionLanguage\Expression;
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
use App\Service\Files2WorkerManager;

class ConfigWorkerController extends Controller
{
    private $logger;
    private $workerRepository;
    private $serializer;
    private $labInstanceRepository;
    private $bus;
    private $operatingSystemRepository;
    private $workerManager;
    private Files2WorkerManager $Files2WorkerManager;


    public function __construct(
        LoggerInterface $logger=null,
        ConfigWorkerRepository $configWorkerRepository,
        LabInstanceRepository $labInstanceRepository,
        SerializerInterface $serializer,
        MessageBusInterface $bus,
        OperatingSystemRepository $operatingSystemRepository,
        WorkerManager $workerManager,
        EntityManagerInterface $entityManager,
        Files2WorkerManager $Files2WorkerManager,        
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
        $this->Files2WorkerManager = $Files2WorkerManager;

    }

    
	#[Get('/api/config/workers', name: 'api_get_workers')]
	#[IsGranted(new Expression('is_granted("ROLE_ADMINISTRATOR") or is_granted("ROLE_TEACHER_EDITOR")'), message: "Access denied.")]
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
        $error=false;
        $entityManager = $this->entityManager;
        $workerPort = $this->getParameter('app.worker_port');
        $data = json_decode($request->getContent(), true);

        $worker = $this->configWorkerRepository->find(["id" => $id]);
        
        $workers = $this->configWorkerRepository->findAll();
        $i=0;
        while ($i<count($workers) && $workers[$i]->getAvailable()==0) {
            $this->logger->debug("[ConfigWorkerController:updateAction]::Value of i ". $i);
            $i++;
        }
        
        if ($i>=count($workers)) // No activ worker when I activate this worker $id
            $first_available_worker=$worker;
        else // At least one activ worker found
            $first_available_worker=$workers[$i];
            
        $first_available_workerIP=$first_available_worker->getIPv4();
        if (isset($data['IPv4'])) {
            $worker->setIPv4($data['IPv4']);
        }
        else
            if (isset($data['available'])) {
                $workerIP=$worker->getIPv4();
                if ($this->checkWorkerAvailable($workerIP,$workerPort)) {
                    $worker->setAvailable($data['available']);
                    $workerIP=$worker->getIPv4();
                    $entityManager->persist($worker);
                    $available=($worker->getAvailable()==1)?"Available":"Disable";
                    $this->logger->info("State of worker ".$workerIP. " has been updated (".$available.").");    
                    //$this->addFlash('success', 'Worker has been enabled');

                    if ($data['available'] === 1) {                   
                        $this->logger->debug("[ConfigWorkerController:updateAction]::Worker ". $first_available_workerIP. " is the first available worker.");

                        $operatingSystems=$this->operatingSystemRepository->findAll();
                        $OS_first_available_worker=$this->getOS_Worker($first_available_workerIP,$workerPort); //Find OS available on the first worker
                        $OS_available_worker=$this->getOS_Worker($workerIP,$workerPort); //Find OS available on the worker which is enabled
                        
                        $this->logger->debug("OS on first enabled worker ". $first_available_workerIP. ":".$workerPort.": ".$OS_first_available_worker);
                        $this->logger->debug("OS on enabled worker to eventually sync ". $workerIP. ":".$workerPort.": ".$OS_available_worker);

                        if ($OS_first_available_worker && $OS_available_worker) {
                            $OS_already_exist_on_first_worker=json_decode($OS_first_available_worker,true);
                            $OS_already_exist_on_worker=json_decode($OS_available_worker,true);

                            foreach ($operatingSystems as $operatingSystem) {
                                $os_name=$operatingSystem->getName();
                                $os_filename=$operatingSystem->getImageFilename();
                                $os_hypervisor=$operatingSystem->getHypervisor()->getName();
                                $os_url=$operatingSystem->getImageUrl();
                                $this->logger->debug("[ConfigWorkerController:updateAction]::Test to sync ".$os_name." ".$os_filename." which is a ".$os_hypervisor." image");

                                if (strtolower($os_hypervisor) === "lxc") {
                                    if (!in_array($operatingSystem->getImageFilename(),$OS_already_exist_on_worker["lxc"]) && in_array($operatingSystem->getImageFilename(),$OS_already_exist_on_first_worker["lxc"])) {
                                        $this->logger->debug("[ConfigWorkerController:updateAction]::".$os_name." doesn't exist on ".$workerIP);
                                        $tmp=array();
                                        $tmp['Worker_Dest_IP'] = $workerIP;
                                        $tmp['hypervisor'] = strtolower($os_hypervisor);
                                        $tmp['os_imagename'] = $os_filename;
                                        $deviceJsonToCopy = json_encode($tmp, 0, 4096);

                                        $this->logger->debug("[ConfigWorkerController:updateAction]::OS to sync:",$tmp);
                                        $this->logger->info("Send request to copy ".$tmp['os_imagename']." ".$tmp['hypervisor']." image from ".$first_available_workerIP." to ".$tmp['Worker_Dest_IP'],$tmp);
                                        $this->bus->dispatch(
                                            new InstanceActionMessage($deviceJsonToCopy, "", InstanceActionMessage::ACTION_COPY2WORKER_DEV), [
                                                new AmqpStamp($first_available_workerIP, AMQP_NOPARAM, []),
                                            ]
                                        );
                                    } else {
                                        $this->logger->debug("[ConfigWorkerController:updateAction]::".$os_name." already exist on ".$workerIP);
                                    }
                                }
                                else {
                                    if (strtolower($os_hypervisor) === "qemu") {
                                        if ( !is_null($os_filename) && !in_array($os_filename,$OS_already_exist_on_worker["qemu"]) 
                                                && in_array($os_filename,$OS_already_exist_on_first_worker["qemu"])
                                            ) {
                                            // A filename exist for this OS (not an URL)
                                            $tmp=array();
                                            $tmp['Worker_Dest_IP'] = $workerIP;
                                            $tmp['hypervisor'] = strtolower($os_hypervisor);
                                            $tmp['os_imagename'] = $os_filename;

                                            $localFilePath = $this->getParameter('image_directory') . '/' . $os_filename;
                                            if (file_exists($localFilePath)) {
                                                $this->logger->debug("[ConfigWorkerController:updateAction]::Send to all worker a message to copy from front the file ".$localFilePath);
                                                $this->Files2WorkerManager->CopyFileToAllWorkers("image",$os_filename);
                                            }
                                            else {
                                                // Copy from worker
                                                $deviceJsonToCopy = json_encode($tmp, 0, 4096);
                                                $this->logger->debug("[ConfigWorkerController:updateAction]::OS to sync from ".$first_available_workerIP." -> ".$tmp['Worker_Dest_IP'],$tmp);
                                                $this->logger->info("Send request to copy ".$tmp['os_imagename']." ".$tmp['hypervisor']." from ".$first_available_workerIP." to ".$tmp['Worker_Dest_IP'],$tmp);
                                                $this->bus->dispatch(
                                                    new InstanceActionMessage($deviceJsonToCopy, "", InstanceActionMessage::ACTION_COPY2WORKER_DEV), [
                                                    new AmqpStamp($first_available_workerIP, AMQP_NOPARAM, []),
                                                    ]
                                                );
                                            }
                                        }
                                        else {
                                            if (is_null($os_filename) && !is_null($os_url)) {
                                                //It's an URL
                                                $this->logger->debug("[ConfigWorkerController:updateAction]::This OS ".$os_name." is defined by an URL. No sync needed.");
                                            }
                                            else {
                                                $this->logger->error("[ConfigWorkerController:updateAction]::This OS ".$os_name." with file ".$os_filename." is missing on the worker ".$first_available_workerIP);
                                            }
                                        }
                                    }
                                    else {
                                        $this->logger->debug("[ConfigWorkerController:updateAction]::Hypervisor ".strtolower($os_hypervisor)." is included to all workers.");
                                    }
                                }
                        }
                    }
                    else {
                        $this->logger->info("One of the worker ".$workerIP." or ".$first_available_workerIP." is not online. Perhaps it's power off");
                        $this->addFlash("danger", "This worker seems not to be online");
                    }
                }
            }
            else {
                $this->logger->info("Worker ". $workerIP. " is offline");
                $worker->setAvailable(0);
                $error=true;
            }
        }

        $entityManager = $this->entityManager;
        $entityManager->flush();

        if (isset($data['IPv4'])) {
            $this->bindQueue($workerIP, $worker->getQueueName());
        }
        if (!$error)
            return $this->json($worker, 200, [], ['api_get_worker_config']);
        else 
            return $this->json($worker, 400, [], ['api_get_worker_config']);       
    }

    
	#[Delete('/api/config/worker/{id<\d+>}', name: 'api_delete_worker')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    public function deleteAction(Request $request, int $id) {
        $data = json_decode($request->getContent(), true);
        if (!isset($id)) {
            $this->logger->error("No worker ID provided for deletion.");
            throw new BadRequestHttpException('No worker ID provided for deletion.');
        }
        $worker = $this->configWorkerRepository->find(["id" => $id]);
        if (!$worker) {
            $this->logger->error("Worker with ID ".$id." not found.");
            throw new BadRequestHttpException('Worker with ID '.$id.' not found.');
        }
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
