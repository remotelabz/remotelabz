<?php

namespace App\Controller;

use App\Service\Monitor\ServiceMonitorInterface;

use App\Service\Monitor\MessageServiceMonitor;
use App\Service\Monitor\ProxyServiceMonitor;
use App\Service\Monitor\WorkerMessageServiceMonitor;
use App\Service\Monitor\WorkerServiceMonitor;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use App\Repository\LabInstanceRepository;
use App\Repository\DeviceInstanceRepository;
use App\Repository\UserRepository;


/**
 * @Route("/admin")
 */
class ServiceController extends Controller
{
    protected $workerPort;
    protected $workerServer;
    private $logger;

    public function __construct(
        string $workerPort,
        string $workerServer,
        LoggerInterface $logger=null
    ) {
        $this->workerPort = $workerPort;
        $this->workerServer = $workerServer;
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
     * @Route("/services", name="services")
     */
    public function index()
    {
        $serviceStatus = [];

        foreach ($this->getRegistredServices() as $registeredService => $type) {
            if ($type === 'local') {
                /** @var ServiceMonitorInterface */
                $service = new $registeredService();
                $serviceStatus[$service::getServiceName()] = $service->isStarted();

            }
            if ($type === 'distant') {
                /** @var ServiceMonitorInterface */
                $service = new $registeredService($this->workerPort, $this->workerServer);
                $serviceStatus[$service::getServiceName()] = $service->isStarted();
            }
            $this->logger->info($type." service ".$service::getServiceName(). " is in state : ".$service->isStarted());

        }

        return $this->render('service/index.html.twig', [
            'serviceName' => $service::getServiceName(),
            'serviceStarted' => $serviceStatus,
        ]);
    }

    /**
     * @Route("/service/start", name="start_service", methods="GET")
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
     * @Route("/service/stop", name="stop_service", methods="GET")
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
     * @Route("/resources", name="resources", methods="GET")
     */
    public function RessourceAction(Request $request, UserRepository $userRepository, LabInstanceRepository $labInstanceRepository, 
                                    DeviceInstanceRepository $deviceInstanceRepository)
    {
        $client = new Client();
        $url = 'http://'.$this->workerServer.':'.$this->workerPort.'/stats/hardware';
        $users = $userRepository->findAll();
        $authenticatedUsers = count($userRepository->findAllAuthenticatedUsers(new \DateTime()));
        $startedVms = count($deviceInstanceRepository->findStartedByType('vm'));
        $startedContainers = count($deviceInstanceRepository->findStartedByType('container'));
        $labInstances = count($labInstanceRepository->findBy(['state'=> 'created']));
        $labsWithInstance = count($labInstanceRepository->countLabWithInstance());

        $dbData = [
            'users' => $users,
            'authenticatedUsers' => $authenticatedUsers,
            'startedVms' => $startedVms,
            'startedContainers' => $startedContainers,
            'labInstances' => $labInstances,
            'labsWithInstance' =>$labsWithInstance
        ];

        try {
            $response = $client->get($url);
            $usage = json_decode($response->getBody()->getContents(), true);
        } catch (Exception $exception) {
            $this->addFlash('danger', 'Worker is not available');
            $this->logger->error('Usage resources error - Web service or Worker is not available');
            $usage=null;
        }

        return $this->render('service/resources.html.twig', [
            'value' => $usage,
            'dbData' => $dbData
        ]);
    }
}
