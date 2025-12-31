<?php

namespace App\Controller;

use App\Service\Monitor\ServiceMonitorInterface;

use App\Service\Monitor\MessageServiceMonitor;
use App\Service\Monitor\ProxyServiceMonitor;
use App\Service\Monitor\WorkerMessageServiceMonitor;
use App\Service\Monitor\WorkerServiceMonitor;
use App\Service\Monitor\RouterServiceMonitor;
use App\Service\Monitor\SshConnectionMonitor;
use App\Service\Monitor\CertificateMonitor;
use App\Service\Network\RouteManagerService;
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
    private $sshUser;
    private $sshPasswd;
    private $sshPublicKey;
    private $sshPrivateKey;
    private $sshPort;

    // New monitoring services
    private $sshMonitor;
    private $certMonitor;
    private $routeManager;
    private $sslCaKey;
    private $sslCaCert;
    private $sslTlsKey;
    private $remotelabzProxySslKey;
    private $remotelabzProxySslCert;

    public function __construct(
        string $workerPort,
        string $workerServer,
        string $sshPublicKey,
        string $sshPrivateKey,
        string $sshUser,
        string $sshPasswd,
        string $remotelabzProxyServerAPI,
        string $remotelabzProxyApiPort,
        string $sslCaKey,
        string $sslCaCert,
        string $sslTlsKey,
        string $remotelabzProxySslKey,
        string $remotelabzProxySslCert,
        string $sshPort,
        LoggerInterface $logger=null,
        WorkerManager $workerManager,
        ProxyManager $proxyManager,
        ConfigWorkerRepository $configWorkerRepository,
        LabInstanceRepository $labInstanceRepository,
        DeviceInstanceRepository $deviceInstanceRepository,
        EntityManagerInterface $entityManager,
        RouteManagerService $routeManager
    ) {
        $this->workerPort = $workerPort;
        $this->workerServer = $workerServer;
        $this->sshPublicKey = $sshPublicKey;
        $this->sshPrivateKey = $sshPrivateKey;
        $this->sshUser = $sshUser;
        $this->sshPasswd = $sshPasswd;
        $this->sshPort = $sshPort;
        $this->workerManager = $workerManager;
        $this->configWorkerRepository = $configWorkerRepository;
        $this->LabInstanceRepository = $labInstanceRepository;
        $this->remotelabzProxyServerAPI = $remotelabzProxyServerAPI;
        $this->remotelabzProxyApiPort = $remotelabzProxyApiPort;
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->proxyManager = $proxyManager;
        $this->logger = $logger ?: new NullLogger();       
        $this->entityManager = $entityManager;
        $this->routeManager = $routeManager;

        // SSL/Certificate paths
        $this->sslCaKey = $sslCaKey;
        $this->sslCaCert = $sslCaCert;
        $this->sslTlsKey = $sslTlsKey;
        $this->remotelabzProxySslKey = $remotelabzProxySslKey;
        $this->remotelabzProxySslCert = $remotelabzProxySslCert;

        // Initialize monitoring services
        $this->sshMonitor = new SshConnectionMonitor(
            $sshUser,
            $sshPasswd,
            $sshPublicKey,
            $sshPrivateKey,
            $sshPort,
            $configWorkerRepository,
            $logger
        );
        
        $this->certMonitor = new CertificateMonitor(
            $sslCaKey,
            $sslCaCert,
            $sslTlsKey,
            $remotelabzProxySslKey,
            $remotelabzProxySslCert,
            30, // Warning threshold: 30 days
            $logger
        );
    }

    /**
     * @return array[]
     */
    public function getRegistredServices(): array
    {
        return [
            MessageServiceMonitor::class => 'local',
            ProxyServiceMonitor::class => 'local',
            RouterServiceMonitor::class => 'local',
            WorkerServiceMonitor::class => 'distant',
            SshConnectionMonitor::class => 'check',
            CertificateMonitor::class => 'check'
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

                if ($registeredService::getServiceName() == "router") {
                   $service = new $registeredService(
                                    $this->routeManager,
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
                    $service = new $registeredService(
                        $this->workerPort,
                        $worker->getIPv4(),
                        $this->sshPublicKey,
                        $this->sshPrivateKey,
                        $this->sshUser,
                        $this->sshPasswd,
                        $this->LabInstanceRepository,
                        $this->logger);
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

            // New: Check type services (SSH and Certificate monitoring)
            if ($type === 'check') {
                if ($registeredService::getServiceName() == "ssh-connection-check") {
                    $ssh_result = $this->sshMonitor->isStarted();
                    
                    // Calculate overall status
                    $allSshOk = true;
                    foreach ($ssh_result as $ip => $status) {
                        if (is_array($status) && isset($status['status']) && !$status['status']) {
                            $allSshOk = false;
                            break;
                        }
                    }
                    $ssh_result['overall_status'] = $allSshOk;
                    
                    $serviceStatus['ssh-connection-check'] = $ssh_result;
                    $this->logger->info("SSH Connection check - Overall status: " . ($allSshOk ? 'OK' : 'ISSUES'));
                    $this->logger->debug("[ServiceController:index]::SSH Connection check - Overall status: ", $ssh_result);
                }
                
                if ($registeredService::getServiceName() == "certificate-check") {
                    $cert_result = $this->certMonitor->isStarted();
                    $serviceStatus['certificate-check'] = $cert_result;
                    $this->logger->info("Certificate check - Overall status: " . ($cert_result['overall_status'] ? 'OK' : 'ISSUES'));
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
        $remotelabzProxyServerAPI=$this->getParameter('app.services.proxy.server.api');
        $remotelabzProxyApiPort=$this->getParameter('app.services.proxy.port.api');
        $workerPort=$this->getParameter('app.worker_port');

        $this->logger->debug("[ServiceController:startServiceAction]::Requested service: ".$requestedService);

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
                        } elseif ($registeredService::getServiceName() == "router") {
                               $service = new $registeredService(
                                    $this->routeManager,
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
                        $service = new $registeredService(
                            $this->workerPort,
                            $request->query->get('ip'),
                            $this->sshPublicKey,
                            $this->sshPrivateKey,
                            $this->sshUser,
                            $this->sshPasswd,
                            $this->LabInstanceRepository,
                            $this->logger);
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
                        } elseif ($registeredService::getServiceName() == "router") {
                            $service = new $registeredService(
                                $this->routeManager,
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
                        $service = new $registeredService(
                            $this->workerPort,
                            $request->query->get('ip'),
                            $this->sshPublicKey,
                            $this->sshPrivateKey,
                            $this->sshUser,
                            $this->sshPasswd,
                            $this->LabInstanceRepository,
                            $this->logger);
                        if ($service->stop() === true )
                            $this->addFlash('success', "Service ".$serviceName." successfully stopped");
                        else $this->addFlash('danger', "Service ".$serviceName." doesn't stopped successfully");
                    }
                }
            }
        } catch (ProcessFailedException $e) {
            $this->addFlash('danger', 'Service failed to stop.');
            $this->logger->error("Error stop service ".$service::getServiceName(). " Exception ".$e); 

            return $this->redirectToRoute('services', ['error' => true]);
        } catch (Exception $e) {
            $this->addFlash('danger', 'Service failed to stop.');
            $this->logger->error("Error stop service ".$service::getServiceName(). " Exception ".$e); 

            return $this->redirectToRoute('services', ['error' => true]);
        }

        return $this->redirectToRoute('services');
    }


    #[Route(path: '/admin/resources', name: 'resources', methods: 'GET')]
    public function ResourceAction(Request $request,ManagerRegistry $doctrine)
    {
        $workers = $this->configWorkerRepository->findBy(['available' => true]);
        
            $usage = $this->workerManager->checkWorkersAction($doctrine);
       
        $this->logger->debug("worker usage:",$usage);

        return $this->render('service/resources.html.twig', [
            'value' => $usage
          //  'nbworkers' => $nbWorkers
        ]);
    }

    #[Route(path: '/admin/service/check', name: 'check_service', methods: 'GET')]
    public function checkServiceAction(Request $request)
    {
        $requestedService = $request->query->get('service');
        $this->logger->debug("[ServiceController:checkServiceAction]::Requested service check: ".$requestedService);

        try {
            if ($requestedService === 'ssh-connection-check') {
                $result = $this->sshMonitor->isStarted();
                $this->logger->debug("[ServiceController:checkServiceAction]::ssh-connection-check service check: ",$result);
                $allOk = true;
                $details = [];
                foreach ($result as $ip => $status) {

                    if (is_array($status)) {
                        if ($status['status']) {
                            $details[] = "{$ip}: Connected via {$status['method']}";
                        } else {
                            $details[] = "{$ip}: Failed - {$status['error']}";
                            $allOk = false;
                        }
                        $this->logger->debug("[ServiceController:checkServiceAction]::ssh-connection-check service check: ",$details);
                    }
                }
                
                if ($allOk) {
                    $this->addFlash('success', 'SSH connections OK. ' . implode(' | ', $details));
                } else {
                    $this->addFlash('warning', 'SSH connection issues detected. ' . implode(' | ', $details));
                }
            }
            
            if ($requestedService === 'certificate-check') {
                $result = $this->certMonitor->isStarted();
                
                $issues = [];
                $warnings = [];
                
                foreach ($result['certificates'] as $key => $cert) {
                    if (!$cert['valid']) {
                        $issues[] = "{$cert['name']}: {$cert['error']}";
                    } elseif (isset($cert['warning']) && $cert['warning']) {
                        $warnings[] = "{$cert['name']}: Expires in {$cert['days_remaining']} days";
                    }
                }
                
                if (empty($issues) && empty($warnings)) {
                    $this->addFlash('success', 'All certificates are valid');
                } elseif (empty($issues)) {
                    $this->addFlash('warning', 'Certificate warnings: ' . implode(' | ', $warnings));
                } else {
                    $this->addFlash('danger', 'Certificate issues: ' . implode(' | ', $issues));
                }
            }
            
        } catch (Exception $e) {
            $this->addFlash('danger', 'Check failed: ' . $e->getMessage());
            $this->logger->error("Error checking service: ".$requestedService." Exception: ".$e->getMessage());
        }

        return $this->redirectToRoute('services');
    }

}
