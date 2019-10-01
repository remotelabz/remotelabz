<?php

namespace App\Controller;

use App\Entity\Lab;
use GuzzleHttp\Psr7;
use App\Form\LabType;
use App\Entity\Device;
use GuzzleHttp\Client;
use App\Entity\LabInstance;
use Psr\Log\LoggerInterface;
use App\Service\FileUploader;
use App\Entity\DeviceInstance;
use Swagger\Annotations as SWG;
use App\Repository\LabRepository;
use App\Repository\DeviceRepository;
use JMS\Serializer\SerializerInterface;
use App\Entity\NetworkInterfaceInstance;
use App\Exception\NotInstancedException;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use App\Repository\LabInstanceRepository;
use GuzzleHttp\Exception\ServerException;
use Nelmio\ApiDocBundle\Annotation\Model;
use GuzzleHttp\Exception\RequestException;
use App\Exception\AlreadyInstancedException;
use App\Repository\DeviceInstanceRepository;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Common\Collections\ArrayCollection;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use App\Repository\NetworkInterfaceInstanceRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\User\UserInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class LabController extends FOSRestController
{
    private $labRepository;
    private $deviceRepository;
    private $serializer;
    protected $labInstanceRepository;
    /**
     * IP or FQDN of worker.
     *
     * @var string
     */
    protected $workerServer;
    /**
     * Port of worker.
     *
     * @var int
     */
    protected $workerPort;
    /**
     * Workers full URL (IP and port).
     *
     * @var string
     */
    protected $workerAddress;

    protected $logger;

    protected $entityManager;

    protected $deviceInstanceRepository;

    protected $networkInterfaceInstanceRepository;

    public function __construct(
        LabRepository $labRepository,
        DeviceRepository $deviceRepository,
        SerializerInterface $serializer,
        LabInstanceRepository $labInstanceRepository,
        LoggerInterface $logger,
        EntityManagerInterface $entityManager,
        DeviceInstanceRepository $deviceInstanceRepository,
        NetworkInterfaceInstanceRepository $networkInterfaceInstanceRepository
    )
    {
        $this->labRepository = $labRepository;
        $this->deviceRepository = $deviceRepository;
        $this->serializer = $serializer;
        $this->labInstanceRepository = $labInstanceRepository;
        $this->workerServer = (string) getenv('WORKER_SERVER');
        $this->workerPort = (int) getenv('WORKER_PORT');
        $this->workerAddress = $this->workerServer . ":" . $this->workerPort;
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->networkInterfaceInstanceRepository = $networkInterfaceInstanceRepository;
    }

    /**
     * @Route("/labs", name="labs")
     * @Rest\Get("/api/labs.{_format}",
     *      defaults={"_format": "json"},
     *      requirements={"_format": "json|xml"}
     * )
     * 
     * @SWG\Parameter(
     *     name="search",
     *     in="query",
     *     type="string",
     *     description="Filter labs by name. All labs containing this value will be shown."
     * )
     * 
     * @SWG\Response(
     *     response=200,
     *     description="Returns all existing labs",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(ref=@Model(type=Lab::class))
     *     )
     * )
     * 
     * @SWG\Tag(name="Labs")
     * @Security(name="Bearer")
     */
    public function indexAction(Request $request, $_format = 'html')
    {
        $search = $request->query->get('search', '');
        
        if ($search !== '') {
            $data = $this->labRepository->findByNameLike($search);
        } else {
            $data = $this->labRepository->findAll();
        }

        $view = $this->view($data, 200)
            ->setTemplate("lab/index.html.twig")
            ->setTemplateData([
                'labs' => $data,
                'search' => $search
            ])
            ->setFormat($_format)
        ;

        return $this->handleView($view);
    }

    /**
     * @Route("/labs/{id<\d+>}",
     *  name="show_lab",
     *  methods="GET")
     * 
     * @Rest\Get("/api/labs/{id<\d+>}.{_format}",
     *      defaults={"_format": "json"},
     *      requirements={"_format": "json|xml"}
     * )
     * 
     * @SWG\Response(
     *     response=200,
     *     description="Returns requested lab",
     *     @SWG\Model(type=Lab::class)
     * )
     * 
     * @SWG\Tag(name="Labs")
     * @Security(name="Bearer")
     */
    public function showAction(Request $request, $id, $_format = 'html', UserInterface $user)
    {
        $lab = $this->labRepository->find($id);

        if (null === $lab) {
            throw new NotFoundHttpException();
        }

        // Remove all instances not belongs to current user (changes are not stored in database)
        $userLabInstance = $this->labInstanceRepository->findByUserAndLab($user, $lab);
        $lab->setInstances($userLabInstance != null ? [ $userLabInstance ] : []);
        $deviceStarted = [];

        /** @var Device $device */
        foreach ($lab->getDevices() as $device) {
            $deviceStarted[$device->getId()] = false;

            if ($userLabInstance && $userLabInstance->getUserDeviceInstance($device)) {
                $deviceStarted[$device->getId()] = true;
            }
        }

        $view = $this->view($lab, 200)
            ->setTemplate("lab/view.html.twig")
            ->setTemplateData([
                'lab' => $lab,
                'labInstance' => $userLabInstance,
                'user' => $user
            ])
            ->setFormat($_format)
        ;

        return $this->handleView($view);
        
        return $this->render('lab/view.html.twig', [
            'lab' => $lab,
            'labInstance' => $lab->getUserInstance($this->getUser()),
            'deviceStarted' => $deviceStarted,
            'user' => $this->getUser()
        ]);
    }

    /**
     * @Route("/labs/new", name="new_lab")
     */
    public function newAction(Request $request, FileUploader $fileUploader)
    {
        $lab = new Lab();
        $labForm = $this->createForm(LabType::class, $lab);
        $labForm->handleRequest($request);
        
        if ($labForm->isSubmitted() && $labForm->isValid()) {
            $lab = $labForm->getData();
            
            $lab->setAuthor($this->getUser());
            
            
            $this->entityManager->persist($lab);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Lab has been created.');

            return $this->redirectToRoute('labs');
        }
        
        return $this->render('lab/new.html.twig', [
            'labForm' => $labForm->createView(),
        ]);
    }

    /**
     * @Route("/labs/{id<\d+>}/edit", name="edit_lab", methods={"GET", "POST"})
     */
    public function editAction(Request $request, $id, FileUploader $fileUploader)
    {
        $repository = $this->getDoctrine()->getRepository('App:Lab');

        $lab = $repository->find($id);

        if (null === $lab) {
            throw new NotFoundHttpException();
        }

        $labForm = $this->createForm(LabType::class, $lab);
        $labForm->handleRequest($request);
        
        if ($labForm->isSubmitted() && $labForm->isValid()) {
            $lab = $labForm->getData();

            
            $this->entityManager->persist($lab);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Lab has been edited.');

            return $this->redirectToRoute('show_lab', [
                'id' => $id
            ]);
        }
        
        return $this->render('lab/new.html.twig', [
            'labForm' => $labForm->createView(),
            'id' => $id,
            'name' => $lab->getName()
        ]);
    }
        
    /**
     * @Route("/labs/{id<\d+>}", name="delete_lab", methods="DELETE")
     */
    public function deleteAction(Request $request, $id)
    {
        $repository = $this->getDoctrine()->getRepository('App:Lab');
            
        $data = null;
        $status = 200;
            
        $lab = $repository->find($id);
            
        if ($lab == null) {
            $status = 404;
        } else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($lab);
            $em->flush();
                
            $data = [
                'message' => 'Lab has been deleted.'
            ];
        }
            
        if ($this->getRequestedFormat($request) === JsonRequest::class) {
            return $this->renderJson($data, $status);
        }

        return $this->redirectToRoute('labs');
    }

    /**
     * @Route("/labs/{id<\d+>}/start", name="start_lab", methods="GET")
     */
    public function startLabAction(int $id, UserInterface $user)
    {
        $lab = $this->labRepository->find($id);
        $hasError = false;

        /** @var Device $device */
        foreach ($lab->getDevices() as $device) {
            try {
                $this->startDevice($lab, $device, $user);
            } catch (AlreadyInstancedException $exception) {
                $this->logger->debug("There is already an instance for device " . $device->getName() . " (" . $device->getUuid() . ") with UUID " . $exception->getInstance()->getUuid());
            } catch (ClientException $exception) {
                $this->logger->error(Psr7\str($exception->getRequest()));
                if ($exception->hasResponse()) {
                    $this->logger->error(Psr7\str($exception->getResponse()));
                }
                $hasError = true;
            } catch (ServerException $exception) {
                $this->logger->error(Psr7\str($exception->getRequest()));
                if ($exception->hasResponse()) {
                    $this->logger->error(Psr7\str($exception->getResponse()));
                }
                $hasError = true;
                $this->stopDevice($lab, $device, $user);
            }
        }

        if ($hasError) {
            $this->addFlash('warning', 'Some devices failed to start. Please verify your parameters or contact an administrator.');
        } else {
            $this->addFlash('success', $lab->getName().' has been started.');
        }

        return $this->redirectToRoute('show_lab', [
            'id' => $id
        ]);
    }

    /**
     * @Route("/labs/{id<\d+>}/stop", name="stop_lab", methods="GET")
     */
    public function stopLabAction(int $id, UserInterface $user)
    {
        $lab = $this->labRepository->find($id);

        foreach ($lab->getDevices() as $device) {
            try {
                $this->stopDevice($lab, $device, $user);
            } catch (NotInstancedException $exception) {
                $this->logger->debug("Device " . $device->getName() . " was not instanced in lab " . $lab->getName());
            } catch (\Exception $exception) {
                $this->logger->error($exception->getMessage());
            }
        }

        $this->addFlash('success', $lab->getName().' has been stopped.');

        return $this->redirectToRoute('show_lab', [
            'id' => $id
        ]);
    }

    /**
     * @Route("/labs/{labId<\d+>}/device/{deviceId<\d+>}/start", name="start_lab_device", methods="GET")
     */
    public function startDeviceAction(int $labId, int $deviceId, UserInterface $user)
    {
        $lab = $this->labRepository->find($labId);
        $device = $this->deviceRepository->find($deviceId);

        try {
            $this->startDevice($lab, $device, $user);

            $this->addFlash('success', $device->getName() . ' has been started.');
        } catch (AlreadyInstancedException $exception) {
            $this->logger->debug("There is already an instance for device " . $device->getName() . " (" . $device->getUuid() . ") with UUID " . $exception->getInstance()->getUuid());
            $this->addFlash('warning', $device->getName() . ' is already instanced.');
        } catch (ClientException $exception) {
            $this->addFlash('danger', "Worker can't be reached. Please contact your administrator.");
            $this->logger->error(Psr7\str($exception->getRequest()));
            if ($exception->hasResponse()) {
                $this->logger->error(Psr7\str($exception->getResponse()));
            }
        } catch (ServerException $exception) {
            $this->addFlash('danger', "Device " . $device->getName() . " failed to start. Please verify your parameters or contact an administrator.");
            $this->logger->error(Psr7\str($exception->getRequest()));
            if ($exception->hasResponse()) {
                $this->logger->error(Psr7\str($exception->getResponse()));
            }
            $this->stopDevice($lab, $device, $user);
        } finally {
            return $this->redirectToRoute('show_lab', [
                'id' => $labId,
            ]);
        }
    }

    /**
     * @Route("/labs/{labId<\d+>}/device/{deviceId<\d+>}/stop", name="stop_lab_device", methods="GET")
     */
    public function stopDeviceAction(int $labId, int $deviceId, UserInterface $user)
    {
        $lab = $this->labRepository->find($labId);
        $device = $this->deviceRepository->find($deviceId);

        $this->stopDevice($lab, $device, $user);

        $this->addFlash('success', $device->getName() . ' has been stopped.');

        return $this->redirectToRoute('show_lab', [
            'id' => $labId
        ]);
    }

    /**
     * Instanciate and start a device from a lab.
     *
     * @param Lab $lab
     * @param Device $device
     * 
     * @throws AlreadyInstancedException If an instanciable object is already instancied.
     * @throws RequestException If something went wrong with the worker
     * 
     * @return void
     */
    private function startDevice(Lab $lab, Device $device, UserInterface $user)
    {
        $client = new Client();

        $labInstance = $this->labInstanceRepository->findByUserAndLab($user, $lab);

        if ($labInstance && $labInstance->isStarted()) {
            throw new AlreadyInstancedException($labInstance);
        } elseif (!$labInstance) {
            $labInstance = LabInstance::create()
                ->setLab($lab)
                ->setUser($user)
                ->setIsInternetConnected(false)
            ;
            $lab->addInstance($labInstance);
            $this->entityManager->persist($lab);
        }

        $deviceInstance = $labInstance->getDeviceInstance($device);

        if ($deviceInstance != null && $deviceInstance->isStarted()) {
            throw new AlreadyInstancedException($deviceInstance);
        } elseif ($deviceInstance == null) {
            $deviceInstance = DeviceInstance::create()
                ->setDevice($device)
                ->setUser($user)
                ->setLab($lab)
            ;
            $device->addInstance($deviceInstance);
            $labInstance->addDeviceInstance($deviceInstance);
            $this->entityManager->persist($deviceInstance);
        }

        // create nic instances as well
        foreach ($device->getNetworkInterfaces() as $networkInterface) {
            $networkInterfaceInstance = $deviceInstance->getNetworkInterfaceInstance($networkInterface);

            if ($networkInterfaceInstance == null) {
                $networkInterfaceInstance = NetworkInterfaceInstance::create()
                    ->setNetworkInterface($networkInterface)
                    ->setUser($user)
                    ->setLab($lab)
                ;

                // if vnc access is requested, ask for a free port and register it
                if ($networkInterface->getSettings()->getProtocol() == "VNC") {
                    $remotePort = $this->getRemoteAvailablePort();
                    $networkInterfaceInstance->setRemotePort($remotePort);
                    try {
                        $this->createDeviceInstanceProxyRoute($deviceInstance->getUuid(), $remotePort);
                    } catch (ServerException $exception) {
                        $this->logger->error($exception->getResponse()->getBody()->getContents());
                        throw $exception;
                    }
                }

                $networkInterface->addInstance($networkInterfaceInstance);
                $deviceInstance->addNetworkInterfaceInstance($networkInterfaceInstance);
                $this->entityManager->persist($networkInterfaceInstance);
            }
        }

        $this->entityManager->flush();

        $context = SerializationContext::create()->setGroups("start_lab");
        $labXml = $this->serializer->serialize($labInstance, 'json', $context);

        $deviceUuid = $deviceInstance->getUuid();

        $url = "http://" . $this->workerAddress . "/lab/device/{$deviceUuid}/start";
        $headers = [ 'Content-Type' => 'application/json' ];
        try {
            $response = $client->post($url, [
                'body' => $labXml,
                'headers' => $headers
            ]);
        } catch (RequestException $exception) {
            $this->logger->error($exception->getResponse()->getBody()->getContents());
            throw $exception;
            // dd($exception->getResponse()->getBody()->getContents(), $labXml, $lab->getInstances());
        }


        foreach ($device->getNetworkInterfaces() as $networkInterface) {
            $networkInterfaceInstance = $deviceInstance->getNetworkInterfaceInstance($networkInterface);

            $networkInterfaceInstance->setStarted(true);
            $this->entityManager->persist($networkInterfaceInstance);
        }

        $deviceInstance->setStarted(true);
        $this->entityManager->persist($deviceInstance);

        // check if the whole lab is started
        $isStarted = $lab->getDevices()->forAll(function ($index, $value) use ($labInstance) {
            /** @var Device $value */
            return $labInstance->getUserDeviceInstance($value) && $labInstance->getUserDeviceInstance($value)->isStarted();
        });
        $labInstance->setStarted($isStarted);
        $this->entityManager->persist($labInstance);

        $this->entityManager->flush();
    }

    /**
     * Instanciate and stop a device from a lab.
     *
     * @param Lab $lab
     * @param Device $device
     * 
     * @throws NotInstancedException If an instanciable object was not instancied.
     * 
     * @return void
     */
    private function stopDevice(Lab $lab, Device $device, UserInterface $user)
    {
        $client = new Client();

        $labInstance = $this->labInstanceRepository->findByUserAndLab($user, $lab);

        if ($labInstance == null) {
            throw new NotInstancedException($lab);
        }

        $deviceInstance = $labInstance->getDeviceInstance($device);

        if ($deviceInstance == null) {
            throw new NotInstancedException($device);
        }
        
        $context = SerializationContext::create()->setGroups("stop_lab");
        $labXml = $this->serializer->serialize($labInstance, 'json', $context);

        $deviceUuid = $deviceInstance->getUuid();

        $url = "http://" . $this->workerAddress . "/lab/device/" . $deviceUuid . "/stop";
        $headers = [ 'Content-Type' => 'application/json' ];
        
        $response = $client->post($url, [
            'body' => $labXml,
            'headers' => $headers
        ]);

        $this->deleteDeviceInstanceProxyRoute($deviceUuid);

        foreach ($device->getNetworkInterfaces() as $networkInterface) {
            $networkInterfaceInstance = $deviceInstance->getNetworkInterfaceInstance($networkInterface);

            if ($networkInterfaceInstance != null) {
                $networkInterface->removeInstance($networkInterfaceInstance);
                $deviceInstance->removeNetworkInterfaceInstance($networkInterfaceInstance);
                $this->entityManager->remove($networkInterfaceInstance);
                $this->entityManager->persist($networkInterface);
                $this->entityManager->persist($deviceInstance);
            }
        }
        
        // first, remove device instance
        $labInstance->removeDeviceInstance($deviceInstance);
        $device->removeInstance($deviceInstance);
        $this->entityManager->remove($deviceInstance);
        $this->entityManager->persist($labInstance);

        // then, if there is no device instance left for current user, delete lab instance
        if (! $labInstance->hasDeviceInstance()) {
            $this->disconnectLabInstance($labInstance);
            $this->entityManager->remove($labInstance);
            $lab->removeInstance($labInstance);
        } else { // otherwise, just tell the system the lab is not fully started
            $labInstance->setStarted(false);
        }
        
        $this->entityManager->persist($device);
        $this->entityManager->persist($lab);
        $this->entityManager->flush();
    }

    private function getRemoteAvailablePort(): int
    {
        $client = new Client();

        $url = "http://" . $this->workerAddress . "/worker/port/free";
        try {
            $response = $client->get($url);
        } catch (RequestException $exception) {
            throw $exception;
        }

        return (int) $response->getBody()->getContents();
    }

    /**
     * @param string $uuid
     * @param integer $remotePort
     * 
     * @throws RequestException 
     * 
     * @return void
     */
    private function createDeviceInstanceProxyRoute(string $uuid, int $remotePort)
    {
        $client = new Client();
        
        $url = 'http://localhost:' .
            getenv('WEBSOCKET_PROXY_API_PORT') .
            '/api/routes/device/' .
            $uuid
        ;
        try {
            $client->post($url, [
                'body' => '{
                    "target": "ws://' . $this->workerServer . ':' . ($remotePort + 1000) . '"
                }',
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);
        } catch (RequestException $exception) {
        } catch (ServerException $exception) {
            throw $exception;
        }
    }

    private function deleteDeviceInstanceProxyRoute(string $uuid)
    {
        $client = new Client();
        
        $url = 'http://localhost:' .
            getenv('WEBSOCKET_PROXY_API_PORT') .
            '/api/routes/device/' .
            $uuid
        ;
        try {
            $client->delete($url);
        } catch (RequestException $exception) {
        } catch (ServerException $exception) {
            throw $exception;
        }
    }

    /**
     * Return a string representing an available subnetwork in the specified CIDR.
     *
     * @param string $cidr
     * @param integer $maxSize
     * @return string|null CIDR notation of the subnet
     */
    private function getAvailableNetwork(string $cidr, int $maxSize): ?string
    {
        $networkRepository = $this->getDoctrine()->getRepository('App:Network');

        $network = IPTools\Network::parse($cidr);

        // Get all possible subnetworks from specified config
        $subnets = $network->moveTo(32 - log((float) $maxSize, 2));

        // If $subnets is empty, it means that user's config has a problem
        if (is_empty($subnets)) {
            throw new BadRequestHttpException('Your network configuration is wrong, please check the dotenv file.');
        }
        
        // Exclude all reserved subnetworks from the list
        foreach ($networkRepository->findAll() as $reservedNetwork) {
            $subnets->exclude(IPTools\Network::parse($reservedNetwork->CIDR));
        }

        // If subnets list is empty now, it means that every subnet is already allocated
        if (is_empty($subnets)) {
            // TODO: Create an new exception class
            throw new BadRequestHttpException(
                'No available subnetwork.' .
                'Please delete some networks or check your config and try again.'
            );
        }

        return (string)$subnets[0];
    }

    /**
     * @Route("/labs/{id<\d+>}/json", name="test_lab_json")
     */
    public function testLabSerializer(int $id, SerializerInterface $serializer, UserInterface $user)
    {
        $lab = $this->labRepository->find($id);
        $labInstance = $this->labInstanceRepository->findByUserAndLab($user, $lab);
        
        $context = SerializationContext::create();
        $context->setGroups(
            "stop_lab"
        );
        
        return new Response($serializer->serialize($labInstance, 'json', $context), 200, [
            "Content-Type" => "application/json"
        ]);
    }

    /**
     * @Route("/labs/{id<\d+>}/device/{deviceId<\d+>}/json", name="test_lab_device_json")
     */
    public function testLabDeviceSerializer(int $id, int $deviceId, SerializerInterface $serializer, UserInterface $user)
    {
        $device = $this->deviceRepository->find($deviceId);
        $deviceInstance = $this->deviceInstanceRepository->findByUserAndDevice($user, $device);

        $context = SerializationContext::create();
        $context->setGroups(
            'start_lab'
        );
        
        return new Response($serializer->serialize($deviceInstance, 'xml', $context), 200, [
            'Content-Type' => 'application/xml'
        ]);
    }

    /**
     * @Route("/labs/{id<\d+>}/device/{deviceId<\d+>}/view", name="view_lab_device")
     */
    public function viewLabDeviceAction(Request $request, int $id, int $deviceId, SerializerInterface $serializer, UserInterface $user)
    {
        $lab = $this->labRepository->find($id);
        $device = $this->deviceRepository->find($deviceId);
        $labInstance = $this->labInstanceRepository->findByUserAndLab($user, $lab);
        $deviceInstance = $labInstance->getUserDeviceInstance($device);

        if ($request->get('size') == "fullscreen") {
            $fullscreen = true;
        } else {
            $fullscreen = false;
        }
        $protocol = stripos($_SERVER['SERVER_PROTOCOL'],'https') === 0 ? 'wss://' : 'ws://';

        return $this->render(($fullscreen ? 'lab/vm_view_fullscreen.html.twig' : 'lab/vm_view.html.twig'), [
            'lab' => $lab,
            'device' => $device,
            'host' => $protocol."".($request->get('host') ?: getenv('WEBSOCKET_PROXY_SERVER')),
            'port' => $request->get('port') ?: getenv('WEBSOCKET_PROXY_PORT'),
            'path' => $request->get('path') ?: 'device/' . $deviceInstance->getUuid()
        ]);
    }

    /**
     * @Route("/labs/{id<\d+>}/connect", name="connect_internet")
     */
    public function connectLabAction(int $id, UserInterface $user)
    {
        $lab = $this->labRepository->find($id);
        $labInstance = $this->labInstanceRepository->findByUserAndLab($user, $lab);

        try {
            $this->connectLabInstance($labInstance);
        } catch (ProcessFailedException $e) {
            $this->addFlash('danger', "Lab " . $lab->getName() . " failed to connect to internet. Please verify your parameters or contact an administrator.");
        }
        
        $labInstance->setIsInternetConnected(true);
        $this->entityManager->persist($labInstance);
        $this->entityManager->flush();
        $this->addFlash('success', 'The lab '.$lab->getName().' is connected.');

        return $this->redirectToRoute('show_lab', [
            'id' => $id
        ]);
    }

    private function connectLabInstance(LabInstance $labInstance)
    {
        $client = new Client();

        if ($labInstance == null) {
            throw new NotInstancedException($labInstance->getLab());
        }

        $context = SerializationContext::create()->setGroups("start_lab");
        $serialized = $this->serializer->serialize($labInstance, 'json', $context);

        $url = "http://" . $this->workerAddress . "/lab/connect/internet";
        $headers = [ 'Content-Type' => 'application/json' ];
        try {
            $response = $client->post($url, [
                'body' => $serialized,
                'headers' => $headers
            ]);
        } catch (RequestException $exception) {
            dd($exception->getResponse()->getBody()->getContents(), $serialized, $labInstance->getInstances());
        }
    }

    /**
     * @Route("/labs/{id<\d+>}/disconnect", name="disconnect_internet")
     */
    public function disconnectLabAction(int $id, UserInterface $user)
    {
        $lab = $this->labRepository->find($id);
        $labInstance = $this->labInstanceRepository->findByUserAndLab($user, $lab);

        try {
            $this->disconnectLabInstance($labInstance);
        } catch (ProcessFailedException $e) {
            $this->addFlash('danger', "Lab " . $lab->getName() . " failed to disconnect to internet. Please verify your parameters or contact an administrator.");
        }
        
        $labInstance->setIsInternetConnected(false);
        $this->entityManager->persist($labInstance);
        $this->entityManager->flush();
        $this->addFlash('success', 'The lab '.$lab->getName().' is disconnected.');

        return $this->redirectToRoute('show_lab', [
            'id' => $id
        ]);
    }

    private function disconnectLabInstance(LabInstance $labInstance)
    {
        $client = new Client();

        if ($labInstance == null) {
            throw new NotInstancedException($labInstance->getLab());
        }

        $context = SerializationContext::create()->setGroups("stop_lab");
        $serialized = $this->serializer->serialize($labInstance, 'json', $context);

        $url = "http://" . $this->workerAddress . "/lab/disconnect/internet";
        $headers = [ 'Content-Type' => 'application/json' ];
        
        $response = $client->post($url, [
            'body' => $serialized,
            'headers' => $headers
        ]);
    }
}
