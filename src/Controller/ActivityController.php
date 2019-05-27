<?php

namespace App\Controller;

use IPTools;
use App\Entity\Network;

use App\Entity\Activity;
use App\Entity\Instance;
use App\Form\ActivityType;
use App\Service\FileUploader;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class ActivityController extends AppController
{
    /**
     * @Route("/activities", name="activities")
     */
    public function indexAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository('App:Activity');

        $search = $request->query->get('search', '');
        
        if ($search !== '') {
            $data = $repository->findByNameLike($search);
        } else {
            $data = $repository->findAll();
        }

        if ($this->getRequestedFormat($request) === JsonRequest::class) {
            return $this->json($data);
        }
        
        return $this->render('activity/index.html.twig', [
            'activities' => $data,
            'search' => $search
        ]);
    }

    /**
     * @Route("/activities/{id<\d+>}.{_format}",
     *  defaults={"_format": "html"},
     *  requirements={"_format": "html|json"},
     *  name="show_activity",
     *  methods="GET")
     */
    public function showAction(Request $request, $id)
    {
        $repository = $this->getDoctrine()->getRepository('App:Activity');

        $data = $repository->find($id);

        if (null === $data) {
            throw new NotFoundHttpException();
        }

        if ($this->getRequestedFormat($request) === JsonRequest::class) {
            return $this->json($data);
        }
        
        return $this->render('activity/view.html.twig', [
            'activity' => $data
        ]);
    }

    /**
     * @Route("/activities/new", name="new_activity")
     */
    public function newAction(Request $request, FileUploader $fileUploader)
    {
        $activity = new Activity();
        $activityForm = $this->createForm(ActivityType::class, $activity);
        $activityForm->handleRequest($request);
        
        if ($activityForm->isSubmitted() && $activityForm->isValid()) {
            $activity = $activityForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($activity);
            $entityManager->flush();
            
            $this->addFlash('success', 'Activity has been created.');

            return $this->redirectToRoute('activities');
        }
        
        return $this->render('activity/new.html.twig', [
            'activityForm' => $activityForm->createView(),
        ]);
    }

    /**
     * @Route("/activities/{id<\d+>}/edit", name="edit_activity", methods={"GET", "POST"})
     */
    public function editAction(Request $request, $id, FileUploader $fileUploader)
    {
        $repository = $this->getDoctrine()->getRepository('App:Activity');

        $activity = $repository->find($id);

        if (null === $activity) {
            throw new NotFoundHttpException();
        }

        $activityForm = $this->createForm(ActivityType::class, $activity);
        $activityForm->handleRequest($request);
        
        if ($activityForm->isSubmitted() && $activityForm->isValid()) {
            $activity = $activityForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($activity);
            $entityManager->flush();
            
            $this->addFlash('success', 'Activity has been edited.');

            return $this->redirectToRoute('show_activity', [
                'id' => $id
            ]);
        }
        
        return $this->render('activity/new.html.twig', [
            'activityForm' => $activityForm->createView(),
            'id' => $id,
            'name' => $activity->getName()
        ]);
    }

    /**
     * @Route("/activities/{id<\d+>}/start", name="start_activity", methods="GET")
     */
    public function startAction(Request $request, int $id)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $repository = $this->getDoctrine()->getRepository('App:Activity');

        $activity = $repository->find($id);

        $instance = Instance::create()
            ->setActivity($activity)
            ->setProcessName($activity->getLab()->getName() . '_' . 'aaa') // TODO: change 'aaa' to a parameter (UUID ?)
            ->setUser($this->getUser())
            ->setStoragePath($_ENV['INSTANCE_STORAGE_PATH'] . $instance->getId())
        ;

        if ($activity->getAccessType() === Activity::VPN_ACCESS) {
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
    }

    /**
     * @Route("/activities/{id<\d+>}", name="delete_activity", methods="DELETE")
     */
    public function deleteAction(Request $request, $id)
    {
        $repository = $this->getDoctrine()->getRepository('App:Activity');
            
        $data = null;
        $status = 200;
            
        $activity = $repository->find($id);
            
        if ($activity == null) {
            $status = 404;
        } else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($activity);
            $em->flush();
                
            $data = [
                'message' => 'Activity has been deleted.'
            ];
        }
            
        if ($this->getRequestedFormat($request) === JsonRequest::class) {
            return $this->json($data, $status);
        }

        return $this->redirectToRoute('activities');
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

    public function generateXMLLabFile($activityId, $network, $userNetwork)
    {
        $fileSystem = new Filesystem();
        $repository = $this->getDoctrine()->getRepository('App:Activity');
        $activity = $repository->find($activityId);
        $lab = $activity->getLab();
        
        $rootNode = new \SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' standalone='yes'?><lab></lab>");
        $userNode = $rootNode->addChild('user');
        $index = 1;
        $indexControl = 1;
        $labName = $lab->getName() . "_" . "aaa";
        
        $rootNode->addChild('name', $labName);
        $rootNode->addChild('id', $activityId);
        $rootNode->addChild('tp_supervised', $activity->getSupervised());
        $rootNode->addChild('tp_shared', $activity->getShared());
        $rootNode->addChild('tp_access', $activity->getAccessType());
        
        $userNode->addAttribute('login', $this->getUser()->getEmail());
        $nodes = $rootNode->addChild('nodes');
        $init = $rootNode->addChild('init');

        if ($activity->getAccessType() === Activity::VPN_ACCESS) {
            $init->addChild('network_lab', $network->cidr);
            $init->addChild('network_user', $userNetwork->cidr);
        }
        
        foreach ($lab->getDevices() as $device) {
            $deviceNode = $nodes->addChild('device');

            if ($activity->getAccessType() === Activity::VPN_ACCESS) {
                $vpnNode = $deviceNode->addChild('vpn');
                $vpnNode->addChild('ipv4', '127.0.0.1/24');
                $vpnNode->addChild('ipv6', '');
            }
            
            $deviceNode->addAttribute('type', $device->getType());

            if ($device->getType() === 'switch') {
                $deviceNode->addChild('name', $device->getName().$index);
                $deviceNode->addChild('rawName', $device->getName().$index);
            } else {
                $deviceNode->addChild('name', $device->getName()."_".$activityId);
                $deviceNode->addChild('rawName', $device->getName()."_".$activityId);
            }

            $deviceNode->addAttribute('virtuality', $device->getVirtuality());
            $deviceNode->addAttribute('id', $device->getId());
            $deviceNode->addAttribute('script', $device->getLaunchScript());
            $deviceNode->addAttribute('image', $device->getOperatingSystem()->getPath());
            $deviceNode->addAttribute('order', $device->getLaunchOrder());
            $deviceNode->addAttribute('hypervisor', $device->getOperatingSystem()->getHypervisor()->getName());

            $system = $device->getOperatingSystem();
            $systemNode = $deviceNode->addChild('system', $system->getName());
            $systemNode->addAttribute('memory', $system->getFlavor()->getMemory());
            $systemNode->addAttribute('disk', $system->getFlavor()->getDisk());

            foreach ($device->getNetworkInterfaces() as $networkInterface) {
                if ($device->getControlInterface()) {
                    // Une interface de controle exist
                    // Si l'interface actuelle n'est pas l'int. de controle
                    if ($networkInterface->getId() != $device->getControlInterface()->getId()) {
                        $interfaceNode = $deviceNode->addChild('interface');
                        $interfaceNode->addAttribute('id', $networkInterface->getId());
                        $interfaceNode->addAttribute('name', $networkInterface->getName());
                        if ($networkInterface->getType() == "tap") {
                            $interfaceNode->addAttribute('type', "tap" . $index);
                            $interfaceNode->addAttribute('mac_address', $networkInterface->getMacAddress());
                            $index++;
                        }
                    }
                } else {
                    $interfaceNode = $deviceNode->addChild('interface');
                    $interfaceNode->addAttribute('id', $networkInterface->getId());
                    $interfaceNode->addAttribute('name', $networkInterface->getName());
                    $interfaceNode->addAttribute('type', $networkInterface->getType());
                }
            }

            $controlInterface = $device->getControlInterface();
            if ($controlInterface) {
                $controlInterfaceNode = $deviceNode->addChild('interface_control');
                $controlInterfaceNode->addAttribute('id', $controlInterface->getId());
                $controlInterfaceNode->addAttribute('name', $controlInterface->getName());
                $controlInterfaceNode->addAttribute('type', $controlInterface->getType());

                $controlInterfaceSettings = $controlInterface->getNetworkSettings();
                if ($controlInterfaceSettings) {
                    $controlInterfaceNode->addAttribute('IPv4', $controlInterfaceSettings->getIp());
                    $controlInterfaceNode->addAttribute('mask', $controlInterfaceSettings->getPrefix4());
                    $controlInterfaceNode->addAttribute('IPv6', $controlInterfaceSettings->getIpv6());
                    $controlInterfaceNode->addAttribute('prefix', $controlInterfaceSettings->getPrefix6());
                    $controlInterfaceNode->addAttribute('gateway', $controlInterfaceSettings->getGateway());
                    $protocol = $controlInterfaceSettings->getProtocol();
                    $controlInterfaceNode->addAttribute('protocol', $protocol);
                    
                    switch ($protocol) {
                        case 'websocket':
                            $port = $this->getenv('WEBSOCKET_PORT_START') + $indexControl;
                            break;
                        case 'telnet':
                            $port = $this->getenv('TELNET_PORT_START') + $indexControl;
                            break;
                        case 'vnc':
                            $port = $this->getenv('VNC_PORT_START') + $indexControl;
                            break;
                        case 'ssh':
                            $port = $this->getenv('SSH_PORT_START') + $indexControl;
                            break;
                    }
                    
                    $controlInterfaceNode->addAttribute('port', $port);
                    
                    $indexControl++;
                }
            }

            if ($device->getType() === "switch") {
                $networks = $rootNode->addChild('networks');
                $network = $networks->addChild('network');
                $network->addAttribute('type', 'OVS');
                $network->addAttribute('device_id', $device->getId());

                foreach ($lab->getConnexions() as $connexion) {
                    $interface = $network->addChild('port');
                    $interface->addAttribute('id', $connexion->getId());
                    $interface->addAttribute('interface_id1', $connexion->getInterface1()->getId());
                    $interface->addAttribute('vlan1', $connexion->getVlan1());
                    $interface->addAttribute('interface_id2', $connexion->getInterface2()->getId());
                    $interface->addAttribute('vlan2', $connexion->getVlan2());
                }
            }
        }
        
        $serveur = $init->addChild('serveur');
        $serveur->addChild('IPv4', getenv('HYPERVISOR_IP'));
        $serveur->addChild('IPv6', '');
        $serveur->addChild('index_interface', $index);
        $serveur->addChild('index_interface_control', 1); // TODO: Use a real value

        $dir = '/opt/remotelabz/' . $this->getUser()->getEmail() . '/' . $labName;
        $fileName = $dir . '/Labfile';

        try {
            $fileSystem->appendToFile($fileName, $rootNode->asXML());
            $fileSystem->chmod($dir, 0770, 0000, true);
        } catch (IOExceptionInterface $exception) {
            echo "An error occurred while creating your directory at ".$exception->getPath();
        }
        
        return $fileName;
    }
}
