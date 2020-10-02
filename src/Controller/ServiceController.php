<?php

namespace App\Controller;

use App\Service\Monitor\MessageServiceMonitor;
use App\Service\Monitor\ProxyServiceMonitor;
use App\Service\Monitor\ServiceMonitorInterface;
use App\Service\Monitor\WorkerMessageServiceMonitor;
use App\Service\Monitor\WorkerServiceMonitor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin")
 */
class ServiceController extends Controller
{
    /**
     * @return string[]
     */
    public function getRegistredServices(): array
    {
        return [
            MessageServiceMonitor::class,
            ProxyServiceMonitor::class,
            WorkerServiceMonitor::class,
            WorkerMessageServiceMonitor::class,
        ];
    }

    /**
     * @Route("/services", name="services")
     */
    public function index()
    {
        $serviceStatus = [];
        foreach ($this->getRegistredServices() as $registredService) {
            /** @var ServiceMonitorInterface */
            $service = new $registredService();
            $serviceStatus[$service::getServiceName()] = $service->isStarted();
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
            foreach ($this->getRegistredServices() as $registredService) {
                $serviceName = call_user_func($registredService.'::getServiceName');

                if ($requestedService === $serviceName) {
                    $service = new $registredService();
                    $service->start();
                    $this->addFlash('success', 'Service succesfully started.');
                }
            }
        } catch (ProcessFailedException $e) {
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
            foreach ($this->getRegistredServices() as $registredService) {
                $serviceName = call_user_func($registredService.'::getServiceName');

                if ($requestedService === $serviceName) {
                    $service = new $registredService();
                    $service->stop();
                    $this->addFlash('success', 'Service succesfully stopped.');
                }
            }
        } catch (ProcessFailedException $e) {
            $this->addFlash('danger', 'Service failed to stop.');

            return $this->redirectToRoute('services', ['error' => true]);
        }

        return $this->redirectToRoute('services');
    }
}
