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
use GuzzleHttp\Client;
use FOS\RestBundle\Controller\Annotations as Rest;
use App\Repository\ConfigWorkerRepository;
use App\Entity\ConfigWorker;


class ServiceController extends Controller
{
    protected $workerPort;
    protected $workerServer;
    private $logger;
    protected $workerManager;

    public function __construct(
        string $workerPort,
        string $workerServer,
        LoggerInterface $logger=null,
        WorkerManager $workerManager,
        ConfigWorkerRepository $configWorkerRepository
    ) {
        $this->workerPort = $workerPort;
        $this->workerServer = $workerServer;
        $this->workerManager = $workerManager;
        $this->configWorkerRepository = $configWorkerRepository;
        $this->logger = $logger;
        if ($logger == null) {
            $this->logger = new Logger();
        }
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
            if ($type === 'local') {
                /** @var ServiceMonitorInterface */
                $service = new $registeredService();
                $serviceStatus[$service::getServiceName()] = $service->isStarted();
                $this->logger->info($type." service ".$service::getServiceName(). " is in state : ".$service->isStarted());

            }
            if ($type === 'distant') {
                $workers = $this->configWorkerRepository->findBy(['available' => true]);
                foreach($workers as $worker) {
                    /** @var ServiceMonitorInterface */
                    $service = new $registeredService($this->workerPort, $worker->getIPv4());
                    $serviceStatus[$service::getServiceName()][$service->getServiceSubName()] = $service->isStarted();
                    $this->logger->info($type." service ".$service::getServiceName(). " is in state : ".$service->isStarted());
                }
                
            }
           

        }

        return $this->render('service/index.html.twig', [
            'serviceName' => $service::getServiceName(),
            'serviceStarted' => $serviceStatus,
        ]);
    }

    /**
     * @Route("/admin/service/start", name="start_service", methods="GET")
     */
    public function startServiceAction(Request $request)
    {
        $requestedService = $request->query->get('service');

        try {
            foreach ($this->getRegistredServices() as $registredService => $type) {
                $serviceName = call_user_func($registredService.'::getServiceName');

                if ($requestedService === $serviceName) {
                    if ($type === 'local') {
                        $service = new $registredService();
                        $service->start();
                        $this->addFlash('success', 'Service successfully started.');
                    }
                    if ($type === 'distant') {
                        $service = new $registredService($this->workerPort, $this->workerServer);
                        $service->start();
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
            $this->logger->error("Error starting service ".$service::getServiceName(). "Exception ".$e); 

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
                        $service = new $registredService($this->workerPort, $this->workerServer);
                        $service->stop();
                        $this->addFlash('success', 'Service successfully stopped.');
                    }
                $this->logger->info($type." service ".$service::getServiceName(). " is in state : ".$service->isStarted()); 
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
    public function RessourceAction(Request $request)
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
        

        return $this->render('service/resources.html.twig', [
            'value' => $usage,
            'nbworkers' => $nbWorkers
        ]);
    }
}
