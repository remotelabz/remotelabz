<?php

namespace App\Controller;

use App\Service\Monitor\MessageServiceMonitor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin")
 */
class ServiceController extends Controller
{
    private $messageServiceMonitor;

    public function __construct(MessageServiceMonitor $messageServiceMonitor)
    {
        $this->messageServiceMonitor = $messageServiceMonitor;
    }

    /**
     * @Route("/services", name="services")
     */
    public function index()
    {
        return $this->render('service/index.html.twig', [
            'messageServiceStarted' => $this->messageServiceMonitor->isStarted(),
        ]);
    }

    /**
     * @Route("/service/start", name="start_service", methods="GET")
     */
    public function startServiceAction(Request $request)
    {
        $service = $request->query->get('service');

        try {
            switch ($service) {
                case 'remotelabz':
                    $this->messageServiceMonitor->start();
                    $this->addFlash('success', 'Service succesfully started.');
                    break;
                
                default:
                    break;
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
        $service = $request->query->get('service');

        try {
            switch ($service) {
                case 'remotelabz':
                    $this->messageServiceMonitor->stop();
                    $this->addFlash('success', 'Service succesfully stopped.');
                    break;
                
                default:
                    break;
            }
        } catch (ProcessFailedException $e) {
            $this->addFlash('danger', 'Service failed to stop.');
            return $this->redirectToRoute('services', ['error' => true]);
        }

        return $this->redirectToRoute('services');
    }
}
