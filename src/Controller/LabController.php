<?php

namespace App\Controller;

use Error;
use App\Entity\Lab;
use App\Utils\Uuid;
use App\Form\LabType;
use App\Entity\Device;
use GuzzleHttp\Client;
use App\Entity\Instance;
use App\Entity\LabInstance;
use App\Service\FileUploader;
use App\Entity\DeviceInstance;
use App\Entity\NetworkSettings;
use App\Repository\LabRepository;
use App\Exception\InstanceException;
use App\Repository\DeviceRepository;
use JMS\Serializer\SerializerInterface;
use App\Entity\NetworkInterfaceInstance;
use App\Exception\NotInstancedException;
use JMS\Serializer\SerializationContext;
use GuzzleHttp\Exception\RequestException;
use App\Exception\AlreadyInstancedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Common\Collections\ArrayCollection;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Swagger\Annotations as SWG;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;

class LabController extends FOSRestController
{
    private $labRepository;
    private $deviceRepository;
    private $serializer;

    public function __construct(LabRepository $labRepository, DeviceRepository $deviceRepository, SerializerInterface $serializer) {
        $this->labRepository = $labRepository;
        $this->deviceRepository = $deviceRepository;
        $this->serializer = $serializer;
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
    public function showAction(Request $request, $id, $_format = 'html')
    {
        $data = $this->labRepository->find($id);

        if (null === $data) {
            throw new NotFoundHttpException();
        }

        // Remove all instances not belongs to current user (changes are not stored in database)
        $userLabInstance = $data->getUserInstance($this->getUser());
        $data->setInstances($userLabInstance != null ? [ $userLabInstance ] : []);

        foreach ($data->getDevices() as $device) {
            $userDeviceInstance = $device->getUserInstance($this->getUser());
            $device->setInstances($userDeviceInstance != null ? [ $userDeviceInstance ] : []);
        }

        $view = $this->view($data, 200)
            ->setTemplate("lab/view.html.twig")
            ->setTemplateData([
                'lab' => $data,
                'labInstance' => $data->getUserInstance($this->getUser()),
                'user' => $this->getUser()
            ])
            ->setFormat($_format)
        ;

        return $this->handleView($view);
        
        return $this->render('lab/view.html.twig', [
            'lab' => $data,
            'labInstance' => $data->getUserInstance($this->getUser()),
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
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($lab);
            $entityManager->flush();
            
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

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($lab);
            $entityManager->flush();
            
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
    public function startLabAction(int $id)
    {
        $lab = $this->labRepository->find($id);

        foreach ($lab->getDevices() as $device) {
            try {
                $this->startDevice($lab, $device);
            } catch (AlreadyInstancedException $exception) {
                
            }
        }

        $this->addFlash('success', $lab->getName().' has been started.');

        return $this->redirectToRoute('show_lab', [
            'id' => $id
        ]);
    }

    /**
     * @Route("/labs/{id<\d+>}/stop", name="stop_lab", methods="GET")
     */
    public function stopLabAction(int $id)
    {
        $lab = $this->labRepository->find($id);

        foreach ($lab->getDevices() as $device) {
            try {
                $this->stopDevice($lab, $device);
            } catch (NotInstancedException $exception) {
                
            } catch (Error $error) {
                
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
    public function startDeviceAction(int $labId, int $deviceId)
    {
        $lab = $this->labRepository->find($labId);
        $device = $this->deviceRepository->find($deviceId);

        $this->startDevice($lab, $device);
        
        $this->addFlash('success', $device->getName() . ' has been started.');

        return $this->redirectToRoute('show_lab', [
            'id' => $labId,
        ]);
    }

    /**
     * @Route("/labs/{labId<\d+>}/device/{deviceId<\d+>}/stop", name="stop_lab_device", methods="GET")
     */
    public function stopDeviceAction(int $labId, int $deviceId)
    {
        $lab = $this->labRepository->find($labId);
        $device = $this->deviceRepository->find($deviceId);

        $this->stopDevice($lab, $device);

        $this->addFlash('success', $device->getName() . ' has been stopped.');

        return $this->redirectToRoute('show_lab', [
            'id' => $labId
        ]);
    }

    private function startDevice(Lab $lab, Device $device)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        $lab->setUser($user);
        $client = new Client();
        $workerUrl = (string) getenv('WORKER_SERVER');
        $workerPort = (string) getenv('WORKER_PORT');

        $labInstance = $lab->getUserInstance($user);

        if ($labInstance != null && $labInstance->isStarted()) {
            throw new AlreadyInstancedException($labInstance);
        } elseif ($labInstance == null) {
            $labInstance = new LabInstance();
            $labInstance
                ->setLab($lab)
                ->setUser($user)
            ;
            $lab->addInstance($labInstance);
            $entityManager->persist($lab);
        }

        $deviceInstance = $device->getUserInstance($user);

        if ($deviceInstance != null && $deviceInstance->isStarted()) {
            throw new AlreadyInstancedException($deviceInstance);
        } elseif ($deviceInstance == null) {
            $deviceInstance = new DeviceInstance();
            $deviceInstance
                ->setDevice($device)
                ->setUser($user)
                ->setLab($lab)
            ;
            $device->addInstance($deviceInstance);
            $entityManager->persist($deviceInstance);
        }

        // create nic instances as well
        foreach ($device->getNetworkInterfaces() as $networkInterface) {
            $networkInterfaceInstance = $networkInterface->getUserInstance($user);

            if ($networkInterfaceInstance == null) {
                $networkInterfaceInstance = new NetworkInterfaceInstance();
                $networkInterfaceInstance
                    ->setNetworkInterface($networkInterface)
                    ->setUser($user)
                    ->setLab($lab)
                ;

                // if vnc access is requested, ask for a free port and register it
                if ($networkInterface->getSettings()->getProtocol() == "VNC") {
                    $remotePort = $this->getRemoteAvailablePort();
                    $networkInterfaceInstance->setRemotePort($remotePort);
                    try {
                        $this->createDeviceProxyRoute($deviceInstance->getDevice(), $remotePort);
                    } catch (RequestException $exception) {
                        dd($exception);
                    }
                }

                $networkInterface->addInstance($networkInterfaceInstance);
                $entityManager->persist($networkInterfaceInstance);
            }
        }

        $entityManager->flush();

        $context = SerializationContext::create()->setGroups("lab");
        $labXml = $this->serializer->serialize($lab, 'xml', $context);

        $deviceUuid = $device->getUuid();

        $url = "http://{$workerUrl}:{$workerPort}/lab/device/{$deviceUuid}/start";
        $headers = [ 'Content-Type' => 'application/xml' ];
        try {
            $response = $client->post($url, [
                'body' => $labXml,
                'headers' => $headers
            ]);
        } catch (RequestException $exception) {
            dd($exception->getResponse()->getBody()->getContents(), $labXml, $lab->getInstances());
        }


        foreach ($device->getNetworkInterfaces() as $networkInterface) {
            $networkInterfaceInstance = $networkInterface->getUserInstance($user);

            $networkInterfaceInstance->setStarted(true);
            $entityManager->persist($networkInterfaceInstance);
        }

        $deviceInstance->setStarted(true);
        $entityManager->persist($deviceInstance);

        // check if the whole lab is started
        $isStarted = $lab->getDevices()->forAll(function ($index, $value) use ($user) {
            $instance = $value->getUserInstance($user);
            return $instance != null ? $instance->isStarted() : false;
        });
        $labInstance->setStarted($isStarted);
        $entityManager->persist($labInstance);

        $entityManager->flush();
    }

    private function stopDevice(Lab $lab, Device $device)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        $lab->setUser($user);
        $client = new Client();
        $workerUrl = (string) getenv('WORKER_SERVER');
        $workerPort = (string) getenv('WORKER_PORT');

        $labInstance = $lab->getUserInstance($user);

        if ($labInstance == null) {
            throw new NotInstancedException($lab);
        }

        $deviceInstance = $device->getUserInstance($user);

        if ($deviceInstance == null) {
            throw new NotInstancedException($device);
        }
        
        $context = SerializationContext::create()->setGroups("lab");
        $labXml = $this->serializer->serialize($lab, 'xml', $context);

        $deviceUuid = $device->getUuid();

        $url = "http://{$workerUrl}:{$workerPort}/lab/device/{$deviceUuid}/stop";
        $headers = [ 'Content-Type' => 'application/xml' ];
        try {
            $response = $client->post($url, [
                'body' => $labXml,
                'headers' => $headers
            ]);
        } catch (RequestException $exception) {
            dd($exception->getResponse()->getBody()->getContents(), $labXml, $lab->getInstances());
        }

        // delete the proxy route if device had one
        try {
            $this->deleteDeviceProxyRoute($device);
        } catch (RequestException $exception) {
            // if we have a 404 error, it means the device's route has already been deleted for some reasons
            if ($exception->getCode() != 404) {
                dd($exception);
            }
        }

        foreach ($device->getNetworkInterfaces() as $networkInterface) {
            $networkInterfaceInstance = $networkInterface->getUserInstance($user);

            if ($networkInterfaceInstance != null) {
                $networkInterface->removeInstance($networkInterfaceInstance);
                $entityManager->remove($networkInterfaceInstance);
                $entityManager->persist($networkInterface);
            }
        }
        
        // first, remove device instance
        $device->removeInstance($deviceInstance);
        $entityManager->remove($deviceInstance);

        // then, if there is no device instance left for current user, delete lab instance
        if (! $lab->hasDeviceUserInstance($user)) {
            $entityManager->remove($labInstance);
            $lab->removeInstance($labInstance);
        } else { // otherwise, just tell the system the lab is not fully started
            $labInstance->setStarted(false);
        }
        
        $entityManager->persist($device);
        $entityManager->persist($lab);
        $entityManager->flush();
    }

    private function getRemoteAvailablePort(): int
    {
        $client = new Client();
        $workerUrl = (string) getenv('WORKER_SERVER');
        $workerPort = (string) getenv('WORKER_PORT');

        $url = "http://{$workerUrl}:{$workerPort}/worker/port/free";
        try {
            $response = $client->get($url);
        } catch (RequestException $exception) {
            throw $exception;
        }

        return (int) $response->getBody()->getContents();
    }

    private function createDeviceProxyRoute(Device $device, int $remotePort)
    {
        $client = new Client();
        
        if ($device->getNetworkInterfaces()->count() > 0) {
            $url = 'http://localhost:' .
                getenv('WEBSOCKET_PROXY_API_PORT') .
                '/api/routes/device/' .
                $device->getUserInstance($this->getUser())->getUuid()
            ;
            try {
                $client->post($url, [
                    'body' => '{
                        "target": "ws://' . getenv('WORKER_SERVER') . ':' . ($remotePort + 1000) . '"
                    }',
                    'headers' => [
                        'Content-Type' => 'application/json'
                    ]
                ]);
            } catch (RequestException $exception) {
                throw $exception;
            }
        }
    }

    private function deleteDeviceProxyRoute(Device $device)
    {
        $client = new Client();
        
        if ($device->getNetworkInterfaces()->count() > 0) {
            $url = 'http://localhost:' .
                getenv('WEBSOCKET_PROXY_API_PORT') .
                '/api/routes/device/' .
                $device->getUserInstance($this->getUser())->getUuid()
            ;
            try {
                $client->delete($url);
            } catch (RequestException $exception) {
                throw $exception;
            }
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
     * @Route("/lab/{id<\d+>}/xml", name="test_lab_xml")
     */
    public function testLabXml(int $id, SerializerInterface $serializer)
    {
        $repository = $this->getDoctrine()->getRepository('App:Lab');
        $lab = $repository->find($id);
        $context = SerializationContext::create();
        $context->setGroups(
            "lab"
        );
        
        return new Response($serializer->serialize($lab, 'xml', $context), 200, [
            'Content-Type' => 'application/xml'
        ]);
    }

    /**
     * @Route("/lab/{id<\d+>}/device/{deviceId<\d+>}/xml", name="test_lab_device8xml")
     */
    public function testLabDeviceXml(int $id, int $deviceId, SerializerInterface $serializer)
    {
        $repository = $this->getDoctrine()->getRepository('App:Lab');
        $lab = $repository->find($id);

        foreach ($lab->getDevices() as $device) {
            if ($device->getId() != $deviceId) {
                $lab->removeDevice($device);
            }
        }

        $context = SerializationContext::create();
        $context->setGroups([
            "Default",
            "user" => [
                "lab"
            ]
        ]);
        
        return new Response($serializer->serialize($lab, 'xml', $context), 200, [
            'Content-Type' => 'application/xml'
        ]);
    }

    /**
     * @Route("/labs/{id<\d+>}/device/{deviceId<\d+>}/view", name="view_lab_device")
     */
    public function viewLabDeviceAction(Request $request, int $id, int $deviceId, SerializerInterface $serializer)
    {
        $lab = $this->labRepository->find($id);
        $device = $this->deviceRepository->find($deviceId);

        if ($request->get('size') == "fullscreen") {
            $fullscreen = true;
        } else {
            $fullscreen = false;
        }

        return $this->render(($fullscreen ? 'lab/vm_view_fullscreen.html.twig' : 'lab/vm_view.html.twig'), [
            'lab' => $lab,
            'device' => $device,
            'host' => 'ws://' . getenv('WEBSOCKET_PROXY_SERVER'),
            'port' => getenv('WEBSOCKET_PROXY_PORT'),
            'path' => 'device/' . $device->getUserInstance($this->getUser())->getUuid()
        ]);
    }
}
