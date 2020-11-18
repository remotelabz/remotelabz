<?php

namespace App\Controller;

use App\Service\Monitor\MessageServiceMonitor;
use App\Service\Monitor\ProxyServiceMonitor;
use App\Service\Monitor\ServiceMonitorInterface;
use App\Service\Monitor\WorkerMessageServiceMonitor;
use App\Service\Monitor\WorkerServiceMonitor;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin")
 */
class ServiceController extends Controller
{
    protected $workerPort;
    protected $workerServer;

    public function __construct(
        string $workerPort,
        string $workerServer
    ) {
        $this->workerPort = $workerPort;
        $this->workerServer = $workerServer;
    }

    /**
     * @return array[]
     */
    public function getRegistredServices(): array
    {
        return [
            MessageServiceMonitor::class => 'local',
            ProxyServiceMonitor::class => 'local',
            WorkerServiceMonitor::class => 'distant',
            WorkerMessageServiceMonitor::class => 'distant',
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
        }

        return $this->render('service/index.html.twig', [
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
                        $this->addFlash('success', 'Service succesfully started.');
                    }
                    if ($type === 'distant') {
                        $service = new $registredService($this->workerPort, $this->workerServer);
                        $service->start();
                        $this->addFlash('success', 'Service succesfully started.');
                    }
                }
            }
        } catch (ProcessFailedException $e) {
            $this->addFlash('danger', 'Service failed to start.');

            return $this->redirectToRoute('services', ['error' => true]);
        } catch (Exception $e) {
            $this->addFlash('danger', 'Service failed to start.');

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

        try {
            foreach ($this->getRegistredServices() as $registredService => $type) {
                $serviceName = call_user_func($registredService.'::getServiceName');

                if ($requestedService === $serviceName) {
                    if ($type === 'local') {
                        $service = new $registredService();
                        $service->stop();
                        $this->addFlash('success', 'Service succesfully stopped.');
                    }
                    if ($type === 'distant') {
                        $service = new $registredService($this->workerPort, $this->workerServer);
                        $service->stop();
                        $this->addFlash('success', 'Service succesfully stopped.');
                    }
                }
            }
        } catch (ProcessFailedException $e) {
            $this->addFlash('danger', 'Service failed to stop.');

            return $this->redirectToRoute('services', ['error' => true]);
        } catch (Exception $e) {
            $this->addFlash('danger', 'Service failed to stop.');

            return $this->redirectToRoute('services', ['error' => true]);
        }

        return $this->redirectToRoute('services');
    }
}
