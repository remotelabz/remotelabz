<?php

namespace App\Controller;

use App\Entity\Lab;
use App\Utils\Uuid;
use App\Form\LabType;
use App\Entity\Device;
use GuzzleHttp\Client;
use App\Entity\Instance;
use App\Service\FileUploader;
use App\Repository\LabRepository;
use App\Exception\InstanceException;
use App\Repository\DeviceRepository;
use JMS\Serializer\SerializerInterface;
use App\Exception\NotInstancedException;
use JMS\Serializer\SerializationContext;
use GuzzleHttp\Exception\RequestException;
use App\Exception\AlreadyInstancedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class LabController extends AppController
{
    private $labRepository;
    private $deviceRepository;

    public function __construct(LabRepository $labRepository, DeviceRepository $deviceRepository) {
        $this->labRepository = $labRepository;
        $this->deviceRepository = $deviceRepository;
    }

    /**
     * @Route("/labs", name="labs")
     */
    public function indexAction(Request $request)
    {
        $search = $request->query->get('search', '');
        
        if ($search !== '') {
            $data = $this->labRepository->findByNameLike($search);
        } else {
            $data = $this->labRepository->findAll();
        }

        if ($this->getRequestedFormat($request) === JsonRequest::class) {
            return $this->json($data);
        }
        
        return $this->render('lab/index.html.twig', [
            'labs' => $data,
            'search' => $search
        ]);
    }

    /**
     * @Route("/labs/{id<\d+>}.{_format}",
     *  defaults={"_format": "html"},
     *  requirements={"_format": "html|json"},
     *  name="show_lab",
     *  methods="GET")
     */
    public function showAction(Request $request, $id)
    {
        $data = $this->labRepository->find($id);

        if (null === $data) {
            throw new NotFoundHttpException();
        }

        if ($this->getRequestedFormat($request) === JsonRequest::class) {
            return $this->json($data);
        }
        
        return $this->render('lab/view.html.twig', [
            'lab' => $data,
            'labInstance' => $data->getUserInstance($this->getUser())
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
            return $this->json($data, $status);
        }

        return $this->redirectToRoute('labs');
    }

    /**
     * @Route("/labs/{id<\d+>}/start", name="start_lab", methods="GET")
     */
    public function startAction(Request $request, int $id, SerializerInterface $serializer)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        $lab = $this->labRepository->find($id);
        $lab->setUser($user);

        $labInstance = $lab->getUserInstance($user);

        if ($labInstance != null && $labInstance->isStarted()) {            throw new AlreadyInstancedException($labInstance);
        } elseif ($labInstance == null) {
            $labInstance = new Instance();
            $labInstance
                ->setLab($lab)
                ->setUser($user)
                ->setUuid((string) new Uuid())
            ;
            $lab->addInstance($labInstance);
            $entityManager->persist($lab);
        }

        $deviceInstances = new ArrayCollection();

        foreach ($lab->getDevices() as $device) {
            $deviceInstance = $device->getUserInstance($user);

            if ($deviceInstance == null) {
                $deviceInstance = new Instance();
                $deviceInstance
                    ->setDevice($device)
                    ->setUser($user)
                    ->setUuid((string) new Uuid());
                ;
                $device->addInstance($deviceInstance);
                $deviceInstances->add($deviceInstance);
                $entityManager->persist($deviceInstance);
            } elseif (! $deviceInstance->isStarted()) {
                $deviceInstances->add($deviceInstance);
            }
        }

        $entityManager->flush();

        $context = SerializationContext::create()->setGroups("lab");
        $labXml = $serializer->serialize($lab, 'xml', $context);

        $client = new Client();
        $workerUrl = (string) getenv('WORKER_SERVER');
        $workerPort = (string) getenv('WORKER_PORT');
        $url = "http://{$workerUrl}:{$workerPort}/lab/start";
        $headers = [ 'Content-Type' => 'application/xml' ];
        try {
            $response = $client->post($url, [
                'body' => $labXml,
                'headers' => $headers
            ]);
        } catch (RequestException $exception) {
            dd($exception->getResponse()->getBody()->getContents(), $labXml, $lab->getInstances(), $deviceInstances);
        }

        foreach ($deviceInstances as $deviceInstance) {
            try {
                $this->createDeviceProxyRoute($deviceInstance->getDevice());
            } catch (RequestException $exception) {
                dd($exception);
            }

            $deviceInstance->setStarted(true);
            $entityManager->persist($deviceInstance);
        }

        $labInstance->setStarted(true);
        $entityManager->persist($labInstance);

        $entityManager->flush();
        
        $this->addFlash('success', $lab->getName().' has been started.');

        return $this->redirectToRoute('show_lab', [
            'id' => $id,
            'output' => $response
        ]);

        /* Old VPN code (just in case :) )
            return $this->render('lab/vm_view.html.twig', [
            'host' => 'ws://' . getenv('WORKER_SERVER'),
            'port' => getenv('WORKER_PORT'),
            'path' => 'websockify'
            ]);

            $entityManager = $this->getDoctrine()->getManager();
            $repository = $this->getDoctrine()->getRepository('App:Lab');

            $lab = $repository->find($id);

            $instance = Instance::create()
                ->setActivity($lab)
                ->setProcessName($lab->getLab()->getName() . '_' . 'aaa') // TODO: change 'aaa' to a parameter (UUID ?)
                ->setUser($this->getUser())
                ->setStoragePath($_ENV['INSTANCE_STORAGE_PATH'] . $instance->getId())
            ;

            if ($lab->getAccessType() === Activity::VPN_ACCESS) {
                // VPN access to the laboratory. We need to reserve IP Network for the user and for the devices
                // We use IPTools library to handle IP management
                // See : https://github.com/S1lentium/IPTools
                $network = new Network(getAvailableNetwork($_ENV['LAB_NETWORK'], $_ENV['LAB_SUBNETS_POOL_SIZE']));

                $entityManager->persist($network);
                $instance->setNetwork($network);

                $userNetwork = new Network(getAvailableNetwork($_ENV['USER_NETWORK'], $_ENV['USER_SUBNETS_POOL_SIZE']));

                $entityManager->persist($userNetwork);
                $instance->setUserNetwork($userNetwork);

                // For user network with the VPN
                $fileSystem = new Filesystem();

                $createVpnUserFile = $instance->getStoragePath() . '/' . "create_vpn_user.sh";
                $deleteVpnUserFile = $instance->getStoragePath() . '/' . "delete_vpn_user.sh";

                try {
                    $fileSystem->appendToFile(
                        $createVpnUserFile,
                        "#!/bin/bash\n" .
                        "source " . $_ENV['VPN_SCRIPTS_PATH'] . "easy-rsa/vars"
                    );
                    $fileSystem->appendToFile(
                        $deleteVpnUserFile,
                        "#!/bin/bash\n" .
                        "source " . $_ENV['VPN_SCRIPTS_PATH'] . "easy-rsa/vars"
                    );
                } catch (IOExceptionInterface $exception) {
                    throw new ServiceUnavailableHttpException('Oops, there was a problem. Please try again.');
                }

                foreach ($this->getUser()->getCourses() as $course) {
                    foreach ($course->getUsers() as $user) {
                        try {
                            $fileSystem->appendToFile(
                                $createVpnUserFile,
                                'KEY_CN="' . $user->getLastName() . '_' . $user->getFirstName() . '"' .
                                $_ENV['VPN_SCRIPTS_PATH'] . 'easy-rsa/pkitool ' . $user->getLastName() . "\n" .
                            $_ENV['VPN_SCRIPTS_PATH'] . 'client-config/make_config.sh ' . $user->getLastName() . "\n"
                            );
                            $fileSystem->appendToFile(
                                $deleteVpnUserFile,
                                $_ENV['VPN_SCRIPTS_PATH'] . 'easy-rsa/revoke-full ' . $user->getLastName() . "\n" .
                                '/etc/init.d/openvpn restart' . "\n"
                            );
                        } catch (IOExceptionInterface $exception) {
                            throw new ServiceUnavailableHttpException('Oops, there was a problem. Please try again.');
                        }
                    }
                }
            }

            $entityManager->persist($instance);
            $entityManager->flush();

            // TODO: Replace this function with a object and a serializer
            $labFile = $this->generateXMLLabFile($id, $network, $userNetwork);
        */
    }

    /**
     * @Route("/labs/{id<\d+>}/stop", name="stop_lab", methods="GET")
     */
    public function stopAction(Request $request, int $id, SerializerInterface $serializer)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        $lab = $this->labRepository->find($id);
        $lab->setUser($user);

        $labInstance = $lab->getUserInstance($user);

        if ($labInstance == null) {
            throw new NotInstancedException();
        }
        
        $context = SerializationContext::create()->setGroups("lab");
        $labXml = $serializer->serialize($lab, 'xml', $context);

        $client = new Client();
        $workerUrl = (string) getenv('WORKER_SERVER');
        $workerPort = (string) getenv('WORKER_PORT');
        $url = "http://{$workerUrl}:{$workerPort}/lab/stop";
        $headers = [ 'Content-Type' => 'application/xml' ];
        try {
            $response = $client->post($url, [
                'body' => $labXml,
                'headers' => $headers
            ]);
        } catch (RequestException $exception) {
            dd($exception->getResponse()->getBody()->getContents(), $labXml, $lab->getInstances());
        }

        foreach ($lab->getDevices() as $device) {
            $deviceInstance = $device->getUserInstance($user);
            
            if ($deviceInstance != null) {
                try {
                    $this->deleteDeviceProxyRoute($device);
                } catch (RequestException $exception) {
                    if ($exception->getCode() != 404) {
                        dd($exception);
                    }
                }

                $device->removeInstance($deviceInstance);
                $entityManager->remove($deviceInstance);
                $entityManager->persist($device);
            }
        }
        
        $lab->removeInstance($labInstance);
        $entityManager->remove($labInstance);

        $entityManager->persist($lab);
        $entityManager->flush();

        $this->addFlash('success', 'Lab has been stopped.');

        return $this->redirectToRoute('show_lab', [
            'id' => $id,
            'output' => $response
        ]);
    }

    /**
     * @Route("/labs/{id<\d+>}/device/{deviceId<\d+>}/start", name="start_lab_device", methods="GET")
     */
    public function deviceStartAction(Request $request, int $id, int $deviceId, SerializerInterface $serializer)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        $lab = $this->labRepository->find($id);
        $lab->setUser($user);

        $device = $this->deviceRepository->find($deviceId);

        $labInstance = $lab->getUserInstance($user);

        if ($labInstance != null && $labInstance->isStarted()) {
            throw new AlreadyInstancedException($labInstance);
        } else {
            $labInstance = new Instance();
            $labInstance
                ->setLab($lab)
                ->setUser($user)
                ->setUuid((string) new Uuid())
            ;

            $lab->addInstance($labInstance);
        }

        $instance = $device->getUserInstance($user);

        if ($instance != null && $instance->isStarted()) {
            throw new AlreadyInstancedException($instance);
        } else {
            $instance = new Instance();
            $instance
                ->setDevice($device)
                ->setUser($user)
                ->setUuid((string) new Uuid());
            ;

            $device->addInstance($instance);
        }
        
        $entityManager->persist($lab);
        $entityManager->persist($device);
        $entityManager->flush();
    
        $context = SerializationContext::create()->setGroups("lab");
        $labXml = $serializer->serialize($lab, 'xml', $context);

        $client = new Client();
        $workerUrl = (string) getenv('WORKER_SERVER');
        $workerPort = (string) getenv('WORKER_PORT');
        $url = 'http://' . getenv('WORKER_SERVER') . ':' . getenv('WORKER_PORT') . '/lab/device/' . $device->getUuid() . '/start';
        $headers = [ 'Content-Type' => 'application/xml' ];
        try {
            $response = $client->post($url, [
                'body' => $labXml,
                'headers' => $headers
            ]);
        } catch (RequestException $exception) {
            dd($exception->getResponse()->getBody()->getContents(), $labXml, $lab->getInstances());
        }
            
        $instance->setStarted(true);
        $entityManager->persist($instance);

        $isStarted = $lab->getDevices()->forAll(function ($index, $value) use ($user) {
            $instance = $value->getUserInstance($user);
            return $instance != null ? $instance->isStarted() : false;
        });
        $labInstance->setStarted($isStarted);
        $entityManager->persist($labInstance);
    
        $entityManager->flush();
        

        // Create device proxy route
        try {
            $this->createDeviceProxyRoute($device);
        } catch (RequestException $exception) {
            dd($exception);
        }
        
        $this->addFlash('success', $device->getName() . ' has been started.');

        return $this->redirectToRoute('show_lab', [
            'id' => $id,
            'output' => $response
        ]);
    }

    /**
     * @Route("/labs/{id<\d+>}/device/{deviceId<\d+>}/stop", name="stop_lab_device", methods="GET")
     */
    public function deviceStopAction(Request $request, int $id, int $deviceId, SerializerInterface $serializer)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        $lab = $this->labRepository->find($id);
        $lab->setUser($user);
        
        $device = $this->getDoctrine()->getRepository('App:Device')->find($deviceId);

        $context = SerializationContext::create()->setGroups("lab");
        $labXml = $serializer->serialize($lab, 'xml', $context);

        $labInstance = $lab->getUserInstance($user);
        $deviceInstance = $device->getUserInstance($user);

        if (is_null($labInstance)) {
            throw new NotInstancedException();
        }

        if (is_null($deviceInstance)) {
            throw new NotInstancedException();
        }

        $client = new Client();
        $url = 'http://' . getenv('WORKER_SERVER') . ':' . getenv('WORKER_PORT') . '/lab/device/' . $device->getUuid() . '/stop';
        $headers = [
            'Content-Type' => 'application/xml'
        ];
        try {
            $response = $client->post($url, [
                'body' => $labXml,
                'headers' => $headers
            ]);
        } catch (RequestException $exception) {
            dd($exception->getResponse()->getBody()->getContents());
        }

        // Delete device proxy route
        try {
            $this->deleteDeviceProxyRoute($device);
        } catch (RequestException $exception) {
            dd($exception);
        }

        $device->removeInstance($deviceInstance);
        $entityManager->remove($deviceInstance);
        
        if (! $lab->hasDeviceUserInstance($user)) {
            $lab->removeInstance($labInstance);
            $entityManager->remove($labInstance);
        } else {
            $labInstance->setStarted(false);
        }

        $entityManager->persist($device);
        $entityManager->persist($lab);
        $entityManager->flush();

        $this->addFlash('success', $device->getName() . ' has been started.');

        return $this->redirectToRoute('show_lab', [
            'id' => $id,
            'output' => $response
        ]);
    }

    private function createDeviceProxyRoute(Device $device)
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
                        "target": "ws://' . getenv('WORKER_SERVER') . ':'
                        . ((int)$device->getNetworkInterfaces()->toArray()[0]->getSettings()->getPort() + 1000) . '"
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

        return $this->render('lab/vm_view.html.twig', [
            'lab' => $lab,
            'device' => $device,
            'host' => 'ws://' . getenv('WEBSOCKET_PROXY_SERVER'),
            'port' => 80,
            'path' => 'device/' . $device->getUserInstance($this->getUser())->getUuid()
        ]);
    }
}
