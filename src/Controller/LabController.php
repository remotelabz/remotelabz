<?php

namespace App\Controller;

use App\Entity\Lab;
use App\Form\LabType;

use GuzzleHttp\Client;
use App\Service\FileUploader;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class LabController extends AppController
{
    /**
     * @Route("/admin/labs", name="labs")
     */
    public function indexAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository('App:Lab');

        $search = $request->query->get('search', '');
        
        if ($search !== '') {
            $data = $repository->findByNameLike($search);
        } else {
            $data = $repository->findAll();
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
     * @Route("/admin/labs/{id<\d+>}.{_format}",
     *  defaults={"_format": "html"},
     *  requirements={"_format": "html|json"},
     *  name="show_lab",
     *  methods="GET")
     */
    public function showAction(Request $request, $id)
    {
        $repository = $this->getDoctrine()->getRepository('App:Lab');

        $data = $repository->find($id);

        if (null === $data) {
            throw new NotFoundHttpException();
        }

        if ($this->getRequestedFormat($request) === JsonRequest::class) {
            return $this->json($data);
        }
        
        return $this->render('lab/view.html.twig', [
            'lab' => $data
        ]);
    }

    /**
     * @Route("/admin/labs/new", name="new_lab")
     */
    public function newAction(Request $request, FileUploader $fileUploader)
    {
        $lab = new Lab();
        $labForm = $this->createForm(LabType::class, $lab);
        $labForm->handleRequest($request);
        
        if ($labForm->isSubmitted() && $labForm->isValid()) {
            $lab = $labForm->getData();
            
            $lab->setUser($this->getUser());
            
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
     * @Route("/admin/labs/{id<\d+>}/edit", name="edit_lab", methods={"GET", "POST"})
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
     * @Route("/admin/labs/{id<\d+>}", name="delete_lab", methods="DELETE")
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
     * @Route("/admin/labs/{id<\d+>}/start", name="start_lab", methods="GET")
     */
    public function startAction(Request $request, int $id, SerializerInterface $serializer)
    {
        $repository = $this->getDoctrine()->getRepository('App:Lab');
        $lab = $repository->find($id);
        $context = SerializationContext::create();
        $context->setGroups([
            "Default",
            "user" => [
                "lab"
            ]
        ]);
        
        $labXml = $serializer->serialize($lab, 'xml', $context);

        $client = new Client();
        $method = 'POST';
        $url = 'http://' . getenv('WORKER_SERVER') . ':' . getenv('WORKER_PORT') . '/lab';
        $headers = [
            'Content-Type' => 'application/xml'
        ];
        try {
            $response = $client->request($method, $url, [
                'body' => $labXml,
                'headers' => $headers
            ]);
        } catch (RequestException $exception) {
            dd($exception->getResponse()->getBody()->getContents());
        }

        foreach ($lab->getDevices() as $device) {
            if ($device->getNetworkInterfaces()->count() > 0) {
                $method = 'POST';
                $url = 'http://localhost:' .
                    getenv('WEBSOCKET_PROXY_API_PORT') .
                    '/api/routes/lab/' .
                    $lab->getId() .
                    '/device/' .
                    $device->getId()
                ;
                try {
                    $response = $client->request($method, $url, [
                        'body' => '{
                            "target": "ws://' . getenv('WORKER_SERVER') . ':'
                            . ((int)$device->getNetworkInterfaces()->toArray()[0]->getSettings()->getPort() + 1000) . '"
                        }',
                        'headers' => [
                            'Content-Type' => 'application/json'
                        ]
                    ]);
                } catch (RequestException $exception) {
                    dd($exception);
                }
            }
        }
        
        $this->addFlash('success', 'Lab has been started.');

        $lab->setIsStarted(true);
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($lab);
        $entityManager->flush();

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
     * @Route("/admin/labs/{id<\d+>}/stop", name="stop_lab", methods="GET")
     */
    public function stopAction(Request $request, int $id, SerializerInterface $serializer)
    {
        $repository = $this->getDoctrine()->getRepository('App:Lab');
        $lab = $repository->find($id);
        $context = SerializationContext::create();
        $context->setGroups([
            "Default",
            "user" => [
                "lab"
            ]
        ]);
        
        $labXml = $serializer->serialize($lab, 'xml', $context);

        $client = new Client();
        $method = 'POST';
        $url = 'http://' . getenv('WORKER_SERVER') . ':' . getenv('WORKER_PORT') . '/lab/stop';
        $headers = [
            'Content-Type' => 'application/xml'
        ];
        try {
            $response = $client->request($method, $url, [
                'body' => $labXml,
                'headers' => $headers
            ]);
        } catch (RequestException $exception) {
            dd($exception->getResponse()->getBody()->getContents());
        }

        foreach ($lab->getDevices() as $device) {
            if ($device->getNetworkInterfaces()->count() > 0) {
                $method = 'DELETE';
                $url = 'http://localhost:' .
                    getenv('WEBSOCKET_PROXY_API_PORT') .
                    '/api/routes/lab/' .
                    $lab->getId() .
                    '/device/' .
                    $device->getId();
                try {
                    $response = $client->request($method, $url);
                } catch (RequestException $exception) {
                    dd($exception);
                }
            }
        }
        

        $this->addFlash('success', 'Lab has been stopped.');

        $lab->setIsStarted(false);
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($lab);
        $entityManager->flush();

        return $this->redirectToRoute('show_lab', [
            'id' => $id,
            'output' => $response
        ]);
    }

    /**
     * Return a string representing an available subnetwork in the specified CIDR.
     *
     * @param string $cidr
     * @param integer $maxSize
     * @return string|null CIDR notation of the subnet
     */
    public function getAvailableNetwork(string $cidr, int $maxSize): ?string
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
     * @Route("/admin/labs/{id<\d+>}/device/{deviceId<\d+>}/view", name="view_lab_device")
     */
    public function viewLabDeviceAction(Request $request, int $id, int $deviceId, SerializerInterface $serializer)
    {
        $repository = $this->getDoctrine()->getRepository('App:Lab');
        $lab = $repository->find($id);

        $repository = $this->getDoctrine()->getRepository('App:Device');
        $device = $repository->find($deviceId);

        return $this->render('lab/vm_view.html.twig', [
            'lab' => $lab,
            'device' => $device,
            'host' => 'ws://' . getenv('WEBSOCKET_PROXY_SERVER'),
            'port' => 80,
            'path' => 'lab/' . $id . '/device/' . $deviceId
        ]);
    }
}
