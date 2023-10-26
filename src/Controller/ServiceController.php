<?php

namespace App\Controller;

use App\Service\Monitor\ServiceMonitorInterface;

use App\Service\Monitor\MessageServiceMonitor;
use App\Service\Monitor\ProxyServiceMonitor;
use App\Service\Monitor\WorkerMessageServiceMonitor;
use App\Service\Monitor\WorkerServiceMonitor;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use FOS\RestBundle\Controller\Annotations as Rest;


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

        $workers = explode(',', $this->workerServer);
        $nbWorkers = count($workers);
        if ( $nbWorkers > 1) {
            $usage = $this->checkWorkersAction();
        }
        else {
            $client = new Client();
            $url = 'http://'.$this->workerServer.':'.$this->workerPort.'/stats/hardware';
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

    public function checkWorkersAction()
    {
        $client = new Client();
        $workers = explode(',', $this->getParameter('app.worker_server'));
        $usage = [];
        foreach($workers as $worker) {
            $url = 'http://'.$worker.':'.$this->getParameter('app.worker_port').'/stats/hardware';
            try {
                $response = $client->get($url);
                $content = json_decode($response->getBody()->getContents(), true);
                $content['worker'] = $worker;
                array_push($usage, $content);
            } catch (Exception $exception) {
                $this->addFlash('danger', 'Worker is not available');
                $this->logger->error('Usage resources error - Web service or Worker is not available');
                $content['disk'] = $content['cpu'] = $content['memory'] = null;
                $content['worker'] = $worker;
                array_push($usage, $content);
            }
        }

        
       // if ('json' !== $request->getRequestFormat()) {
            return $usage;
        /*}
        $response = new Response();
        $response->setContent(json_encode([
            'code'=> 200,
            'status'=>'success',
            'message' => 'Successfully listed textobjects (60062).',
            'data' => $usage]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;*/
    }

    /**
     * @Rest\Get("/api/free/worker", name="api_get_right_worker")
     */
    public function getFreeWorker(Request $request)
    {
        $usages = $this->checkWorkersAction();
        $disk = 100;
        $worker ="";
        foreach($usages as $usage) {
            if ($usage['disk'] < $disk) {
                $disk = $usage['disk'];
                $worker = $usage['worker'];
            }
        }
        $freeWorker = ['disk'=> $disk, 'worker'=> $worker];
        $response = new Response();
        $response->setContent(json_encode([
            'code'=> 200,
            'status'=>'success',
            'message' => 'Successfully listed textobjects (60062).',
            'data' => $usage]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
}
