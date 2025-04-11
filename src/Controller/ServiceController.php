<?php

namespace App\Controller;

use App\Service\Monitor\ServiceMonitorInterface;

use App\Service\Monitor\MessageServiceMonitor;
use App\Service\Monitor\ProxyServiceMonitor;
use App\Service\Monitor\WorkerMessageServiceMonitor;
use App\Service\Monitor\WorkerServiceMonitor;
use App\Service\Worker\WorkerManager;
use App\Service\Proxy\ProxyManager;

use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use GuzzleHttp\Client;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;
use App\Repository\ConfigWorkerRepository;
use App\Entity\ConfigWorker;
use App\Repository\LabInstanceRepository;
use App\Entity\LabInstance;
use App\Repository\DeviceInstanceRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;

class ServiceController extends Controller
{
    protected $workerPort;
    protected $workerServer;
    private $logger;
    protected $workerManager;
    private $labInstanceRepository;
    private $remotelabzProxyServerAPI;
    private $remotelabzProxyApiPort;
    protected $deviceInstanceRepository;
    protected $proxyManager;

    public function __construct(
        string $workerPort,
        string $workerServer,
        string $remotelabzProxyServerAPI,
        string $remotelabzProxyApiPort,
        LoggerInterface $logger=null,
        WorkerManager $workerManager,
        ProxyManager $proxyManager,
        ConfigWorkerRepository $configWorkerRepository,
        LabInstanceRepository $labInstanceRepository,
        DeviceInstanceRepository $deviceInstanceRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->workerPort = $workerPort;
        $this->workerServer = $workerServer;
        $this->workerManager = $workerManager;
        $this->configWorkerRepository = $configWorkerRepository;
        $this->LabInstanceRepository = $labInstanceRepository;
        $this->remotelabzProxyServerAPI = $remotelabzProxyServerAPI;
        $this->remotelabzProxyApiPort = $remotelabzProxyApiPort;
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->proxyManager = $proxyManager;
        $this->logger = $logger ?: new NullLogger();       
        $this->entityManager = $entityManager;
    }

    /**
     * @return array[]
     */
    public function getRegistredServices(): array
    {
        return [
            MessageServiceMonitor::class => 'local',
            ProxyServiceMonitor::class => 'local',
            WorkerServiceMonitor::class => 'distant'
        ];
    }

    #[Route(path: '/admin/services', name: 'services')]
    public function index()
    {
        $serviceStatus = [];

        foreach ($this->getRegistredServices() as $registeredService => $type) {
            //$this->logger->debug("Type of service: ".$registeredService);
            //$this->logger->debug("Name of the service: ".$registeredService::getServiceName());

            if ($type === 'local') {
                /** @var ServiceMonitorInterface */
                $service=null;
                if ($registeredService::getServiceName() == "remotelabz") {
                    $service = new $registeredService();
                }
                if ($registeredService::getServiceName() == "remotelabz-proxy") {
                    $service = new $registeredService(
                                    $this->remotelabzProxyServerAPI,
                                    $this->remotelabzProxyApiPort,
                                    $this->deviceInstanceRepository,
                                    $this->proxyManager,
                                    $this->logger
                                );
                }

                $service_result=$service->isStarted();
                $serviceStatus[$service::getServiceName()] = $service_result;
                $this->logger->info("Statut of ".$service::getServiceName()." a ".$type." service is in state : ".$service_result);
                
                           
            }
            if ($type === 'distant') {
                $workers = $this->configWorkerRepository->findBy(['available' => true]);
                foreach($workers as $worker) {
                    /** @var ServiceMonitorInterface */
                    $service = new $registeredService($this->workerPort, $worker->getIPv4(), $this->LabInstanceRepository, $this->logger);
                    $service_result=$service->isStarted();
                    $this->logger->info("Health of worker: ".$worker->getIPv4()." Result: ",$service_result);
                    if ($service_result["power"] === true) {// The worker is power on so, the service (value) can be up or down
                        $serviceStatus[$service::getServiceName()][$service->getServiceSubName()] = $service_result["service"];
                    }
                    elseif (($service_result["power"] === false)) {  //The worker is power off
                        $serviceStatus[$service::getServiceName()][$service->getServiceSubName()] = "error";
                        //The service is not response
                        $worker = $this->configWorkerRepository->findBy(['IPv4' => $worker->getIPv4()]);
                        $worker[0]->setAvailable(0);
                        $entityManager = $this->entityManager;
                        $entityManager->persist($worker[0]);
                        $entityManager->flush();
                    }
                }
            }
        }

        return $this->render('service/index.html.twig', [
            'serviceName' => $service::getServiceName(),
            'serviceStatus' => $serviceStatus,
        ]); 
    }

    #[Route(path: '/admin/service/start', name: 'start_service', methods: 'GET')]
    public function startServiceAction(Request $request)
    {
        $requestedService = $request->query->get('service');     
        $publicKeyFile=$this->getParameter('app.ssh.worker.publickey');
        $privateKeyFile=$this->getParameter('app.ssh.worker.privatekey');
        $ssh_user=$this->getParameter('app.ssh.worker.user');
        $ssh_password=$this->getParameter('app.ssh.worker.passwd');
        $remotelabzProxyServerAPI=$this->getParameter('app.services.proxy.server.api');
        $remotelabzProxyApiPort=$this->getParameter('app.services.proxy.port.api');

        $this->logger->debug("Requested service: ".$requestedService);

        //try {
            foreach ($this->getRegistredServices() as $registeredService => $type) {
                $serviceName = $registeredService::getServiceName();                             

                if ($requestedService === $serviceName) {
                    if ($type === 'local') {
                        $this->logger->info("Start service action requested for ".$registeredService);
                        if ($serviceName == "remotelabz-proxy") {
                            $service = new $registeredService(
                                $this->remotelabzProxyServerAPI,
                                $this->remotelabzProxyApiPort,
                                $this->deviceInstanceRepository,
                                $this->proxyManager,
                                $this->logger
                            );
                        }
                        else {
                            $service = new $registeredService();
                        }
                        if ($service->start())
                            $this->addFlash('success', "Service ".$serviceName." successfully started");
                        else 
                            $this->addFlash('danger', "Service ".$serviceName." doesn't started successfully");
                    }
                    if ($type === 'distant') {
                        $this->logger->info("Start action for worker: ".$request->query->get('ip'));
                        $service = new $registeredService($this->workerPort, $request->query->get('ip'),$this->LabInstanceRepository,$this->logger);
                        if ($service->start() === true)
                            $this->addFlash('success', "Service ".$serviceName." successfully started");
                        else $this->addFlash('danger', "Service ".$serviceName." doesn't start successfully");
                    }
                }
            }
            try{
        } catch (ProcessFailedException $e) {
            $this->addFlash('danger', 'Service failed to start.');
            $this->logger->error("Error starting service ".$service::getServiceName(). "Exception ".$e); 

            return $this->redirectToRoute('services', ['error' => true]);
        } catch (Exception $e) {
            $this->addFlash('danger', 'Service failed to start.');
            //$this->logger->error("Error starting service ".$service::getServiceName(). "Exception ".$e); 

            return $this->redirectToRoute('services', ['error' => true]);
        }

        return $this->redirectToRoute('services');
    }

    #[Route(path: '/admin/service/stop', name: 'stop_service', methods: 'GET')]
    public function stopServiceAction(Request $request)
    {
        $requestedService = $request->query->get('service');
        //$this->logger->debug($requestedService);

        try {
            foreach ($this->getRegistredServices() as $registeredService => $type) {
                $serviceName = $registeredService::getServiceName();                             

                if ($requestedService === $serviceName) {
                    if ($type === 'local') {
                        $this->logger->info("Stop service action requested for ".$registeredService);
                        if ($serviceName == "remotelabz-proxy") {
                            $service = new $registeredService(
                                $this->remotelabzProxyServerAPI,
                                $this->remotelabzProxyApiPort,
                                $this->deviceInstanceRepository,
                                $this->proxyManager,
                                $this->logger
                            );
                        }
                        else {
                            $service = new $registeredService();
                        }
                        if ($service->stop() === true)
                            $this->addFlash('success', "Service ".$serviceName." successfully stopped");
                        else
                            $this->addFlash('danger', "Service ".$serviceName." doesn't stopped successfully");

                    }
                    if ($type === 'distant') {
                        $this->logger->info("Stop action for worker: ".$request->query->get('ip'));
                        $service = new $registeredService($this->workerPort, $request->query->get('ip'),$this->LabInstanceRepository,$this->logger);
                        if ($service->stop() === true )
                            $this->addFlash('success', "Service ".$serviceName." successfully stopped");
                        else $this->addFlash('danger', "Service ".$serviceName." doesn't stopped successfully");
                    }
                }
            }
        } catch (ProcessFailedException $e) {
            $this->addFlash('danger', 'Service failed to stop.');
            $this->logger->error("Error stop service ".$service::getServiceName(). "Exception ".$e); 

            return $this->redirectToRoute('services', ['error' => true]);
        } catch (Exception $e) {
            $this->addFlash('danger', 'Service failed to stop.');
            $this->logger->error("Error stop service ".$service::getServiceName(). "Exception ".$e); 

            return $this->redirectToRoute('services', ['error' => true]);
        }

        return $this->redirectToRoute('services');
    }
         #[Route(path: '/admin/resources', name: 'resources', methods: 'GET')]
    public function ResourceAction(Request $request,ManagerRegistry $doctrine)
    {
        $workers = $this->configWorkerRepository->findBy(['available' => true]);
        //$workers = explode(',', $this->workerServer);
        //$nbWorkers = count($workers);
      //  if ( $nbWorkers > 1) {
            $usage = $this->workerManager->checkWorkersAction($doctrine);
        //}
        /* else {
            $client = new Client();
            $this->logger->debug("worker:".$workers[0]->getIPv4());

            $url = 'http://'.$workers[0]->getIPv4().':'.$this->workerPort.'/stats/hardware';
            $response="";
            try {
                $response = $client->get($url);
                $usage = json_decode($response->getBody()->getContents(), true);
            } catch (Exception $exception) {
                $this->addFlash('danger', 'Worker is not available');
                $this->logger->error("Usage resources error - Web service or Worker ".$worker[0]->getIPv4()." is not available");
                $usage=null;
            }

        }*/
        $this->logger->debug("worker usage:",$usage);

        return $this->render('service/resources.html.twig', [
            'value' => $usage
          //  'nbworkers' => $nbWorkers
        ]);
    }
}
