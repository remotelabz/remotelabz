<?php

namespace App\Controller;

use App\Service\Monitor\ServiceMonitorInterface;

use App\Service\Monitor\MessageServiceMonitor;
use App\Service\Monitor\ProxyServiceMonitor;
use App\Service\Monitor\WorkerMessageServiceMonitor;
use App\Service\Monitor\WorkerServiceMonitor;
use App\Service\Worker\WorkerManager;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use GuzzleHttp\Client;
use FOS\RestBundle\Controller\Annotations as Rest;
use App\Repository\ConfigWorkerRepository;
use App\Entity\ConfigWorker;
use App\Repository\LabInstanceRepository;
use App\Entity\LabInstance;



class ServiceController extends Controller
{
    protected $workerPort;
    protected $workerServer;
    private $logger;
    protected $workerManager;
    private $labInstanceRepository;


    public function __construct(
        string $workerPort,
        string $workerServer,
        LoggerInterface $logger=null,
        WorkerManager $workerManager,
        ConfigWorkerRepository $configWorkerRepository,
        LabInstanceRepository $labInstanceRepository
    ) {
        $this->workerPort = $workerPort;
        $this->workerServer = $workerServer;
        $this->workerManager = $workerManager;
        $this->configWorkerRepository = $configWorkerRepository;
        $this->LabInstanceRepository = $labInstanceRepository;
        $this->logger = $logger ?: new NullLogger();       
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

    /**
     * @Route("/admin/services", name="services")
     */
    public function index()
    {
        $serviceStatus = [];

        foreach ($this->getRegistredServices() as $registeredService => $type) {
            $this->logger->debug("Type of service: ".$registeredService);
            $this->logger->debug("Name of the service: ".$registeredService::getServiceName());

            if ($type === 'local') {
                /** @var ServiceMonitorInterface */
                $service = new $registeredService();
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
                    elseif (($service_result["power"] === false)) {  // The worker is power off
                        $serviceStatus[$service::getServiceName()][$service->getServiceSubName()] = "error";
                        //The service is not response
                        $worker = $this->configWorkerRepository->findBy(['IPv4' => $worker->getIPv4()]);
                        $worker[0]->setAvailable(0);
                        $entityManager = $this->getDoctrine()->getManager();
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

    /**
     * @Route("/admin/service/start", name="start_service", methods="GET")
     */
    public function startServiceAction(Request $request)
    {
        $requestedService = $request->query->get('service');     
        $publicKeyFile=$this->getParameter('app.ssh.worker.publickey');
        $privateKeyFile=$this->getParameter('app.ssh.worker.privatekey');
        $ssh_user=$this->getParameter('app.ssh.worker.user');
        $ssh_password=$this->getParameter('app.ssh.worker.passwd');
        
        $this->logger->debug("Requested service: ".$requestedService);


        try {
            foreach ($this->getRegistredServices() as $registredService => $type) {
                $serviceName = $registredService::getServiceName();                             

                if ($requestedService === $serviceName) {
                    if ($type === 'local') {
                        $this->logger->info("Start service action requested for ".$registredService);
                        $service = new $registredService();
                        $service->start();
                        //TODO Change the statut : wait a health message !
                        $this->addFlash('success', 'Service successfully started.');
                    }
                    if ($type === 'distant') {
                        $this->logger->info("Start action for worker: ".$request->query->get('ip'));
                        $service = new $registredService($this->workerPort, $request->query->get('ip'),$this->LabInstanceRepository,$this->logger);
                        if ($service->start())
                        //TODO Change the statut : wait a health message !
                            $this->addFlash('success', 'Service successfully started.');
                    }
                }
            }
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

    /**
     * @Route("/admin/service/stop", name="stop_service", methods="GET")
     */
    public function stopServiceAction(Request $request)
    {
        $requestedService = $request->query->get('service');
        //$this->logger->debug($requestedService);

        try {
            foreach ($this->getRegistredServices() as $registredService => $type) {
                $serviceName = call_user_func($registredService.'::getServiceName');
               

                if ($requestedService === $serviceName) {
                    $this->logger->debug("Service name to stop: ".$serviceName);
                    $this->logger->debug("Requested Service to stop: ".$requestedService);
                    if ($type === 'local') {
                        $service = new $registredService();
                        $service->stop();
                        $this->addFlash('success', 'Service successfully stopped.');
                    }
                    if ($type === 'distant') {
                        $this->logger->info("Stop action for worker: ".$request->query->get('ip'));
                        $service = new $registredService($this->workerPort, $request->query->get('ip'),$this->LabInstanceRepository,$this->logger);
                        if ($service->stop()) {
                            $this->addFlash('success', 'Service successfully stopped.');
                            //We assume the worker stay available. It's just the service is down
                        }
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
         /**
     * @Route("/admin/resources", name="resources", methods="GET")
     */
    public function ResourceAction(Request $request)
    {
        $workers = $this->configWorkerRepository->findBy(['available' => true]);
        //$workers = explode(',', $this->workerServer);
        $nbWorkers = count($workers);
        if ( $nbWorkers > 1) {
            $usage = $this->workerManager->checkWorkersAction();
        }
        else {
            $client = new Client();
            $this->logger->debug("worker:".$workers[0]->getIPv4());

            $url = 'http://'.$workers[0]->getIPv4().':'.$this->workerPort.'/stats/hardware';
            try {
                $response = $client->get($url);
                $usage = json_decode($response->getBody()->getContents(), true);
            } catch (Exception $exception) {
                $this->addFlash('danger', 'Worker is not available');
                $this->logger->error('Usage resources error - Web service or Worker is not available');
                $usage=null;
            }
        }
        $this->logger->debug("worker usage:",$usage);

        return $this->render('service/resources.html.twig', [
            'value' => $usage,
            'nbworkers' => $nbWorkers
        ]);
    }
}
