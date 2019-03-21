<?php

namespace App\Controller;

use App\Entity\Lab;
use App\Form\LabType;

use GuzzleHttp\Client;
use App\Service\FileUploader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;

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
    public function startAction(Request $request, int $id)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $repository = $this->getDoctrine()->getRepository('App:Lab');

        $lab = $repository->find($id);

        $instance = Instance::create()
            ->setActivity($lab)
            ->setProcessName($lab->getLab()->getName() . '_' . 'aaa') // TODO: change 'aaa' to a parameter (UUID ?)
            ->setUser($this->getUser())
            ->setStoragePath($_ENV['INSTANCE_STORAGE_PATH'] . $instance->getId())
        ;

        // if ($lab->getAccessType() === Activity::VPN_ACCESS) {
        //     // VPN access to the laboratory. We need to reserve IP Network for the user and for the devices
        //     // We use IPTools library to handle IP management
        //     // See : https://github.com/S1lentium/IPTools
        //     $network = new Network(getAvailableNetwork($_ENV['LAB_NETWORK'], $_ENV['LAB_SUBNETS_POOL_SIZE']));

        //     $entityManager->persist($network);
        //     $instance->setNetwork($network);

        //     $userNetwork = new Network(getAvailableNetwork($_ENV['USER_NETWORK'], $_ENV['USER_SUBNETS_POOL_SIZE']));

        //     $entityManager->persist($userNetwork);
        //     $instance->setUserNetwork($userNetwork);

        //     // For user network with the VPN
        //     $fileSystem = new Filesystem();

        //     $createVpnUserFile = $instance->getStoragePath() . '/' . "create_vpn_user.sh";
        //     $deleteVpnUserFile = $instance->getStoragePath() . '/' . "delete_vpn_user.sh";

        //     try {
        //         $fileSystem->appendToFile(
        //             $createVpnUserFile,
        //             "#!/bin/bash\n" .
        //             "source " . $_ENV['VPN_SCRIPTS_PATH'] . "easy-rsa/vars"
        //         );
        //         $fileSystem->appendToFile(
        //             $deleteVpnUserFile,
        //             "#!/bin/bash\n" .
        //             "source " . $_ENV['VPN_SCRIPTS_PATH'] . "easy-rsa/vars"
        //         );
        //     } catch (IOExceptionInterface $exception) {
        //         throw new ServiceUnavailableHttpException('Oops, there was a problem. Please try again.');
        //     }

        //     foreach ($this->getUser()->getCourses() as $course) {
        //         foreach ($course->getUsers() as $user) {
        //             try {
        //                 $fileSystem->appendToFile(
        //                     $createVpnUserFile,
        //                     'KEY_CN="' . $user->getLastName() . '_' . $user->getFirstName() . '"' .
        //                     $_ENV['VPN_SCRIPTS_PATH'] . 'easy-rsa/pkitool ' . $user->getLastName() . "\n" .
        //                     $_ENV['VPN_SCRIPTS_PATH'] . 'client-config/make_config.sh ' . $user->getLastName() . "\n"
        //                 );
        //                 $fileSystem->appendToFile(
        //                     $deleteVpnUserFile,
        //                     $_ENV['VPN_SCRIPTS_PATH'] . 'easy-rsa/revoke-full ' . $user->getLastName() . "\n" .
        //                     '/etc/init.d/openvpn restart' . "\n"
        //                 );
        //             } catch (IOExceptionInterface $exception) {
        //                 throw new ServiceUnavailableHttpException('Oops, there was a problem. Please try again.');
        //             }
        //         }
        //     }
        // }

        $entityManager->persist($instance);
        $entityManager->flush();

        // TODO: Replace this function with a object and a serializer
        $labFile = $this->generateXMLLabFile($id, $network, $userNetwork);
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
     * @Route("/lab/xml/{id<\d+>}", name="test_lab_xml")
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

    public function generateXMLLabFile($labId, $network, $userNetwork)
    {
        // $fileSystem = new Filesystem();
        $repository = $this->getDoctrine()->getRepository('App:Lab');
        $lab = $repository->find($labId);
        
        $rootNode = new \SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' standalone='yes'?><lab></lab>");
        $userNode = $rootNode->addChild('user');
        $index = 1;
        $indexControl = 1;
        $labName = $lab->getName();
        
        $rootNode->addChild('name', $labName);
        $rootNode->addChild('id', $labId);
        $rootNode->addChild('tp_supervised', $lab->getSupervised());
        $rootNode->addChild('tp_shared', $lab->getShared());
        $rootNode->addChild('tp_access', $lab->getAccessType());
        
        $userNode->addAttribute('login', $this->getUser()->getEmail());
        $nodes = $rootNode->addChild('nodes');
        $init = $rootNode->addChild('init');

        // if ($lab->getAccessType() === Activity::VPN_ACCESS) {
        //     $init->addChild('network_lab', $network->cidr);
        //     $init->addChild('network_user', $userNetwork->cidr);
        // }
        
        foreach ($lab->getDevices() as $device) {
            $deviceNode = $nodes->addChild('device');

            // if ($lab->getAccessType() === Activity::VPN_ACCESS) {
            //     $vpnNode = $deviceNode->addChild('vpn');
            //     $vpnNode->addChild('ipv4', '127.0.0.1/24');
            //     $vpnNode->addChild('ipv6', '');
            // }
            
            $deviceNode->addAttribute('type', $device->getType());

            if ($device->getType() === 'switch') {
                $deviceNode->addChild('name', $device->getName().$index);
                $deviceNode->addChild('rawName', $device->getName().$index);
            } else {
                $deviceNode->addChild('name', $device->getName()."_".$labId);
                $deviceNode->addChild('rawName', $device->getName()."_".$labId);
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

        return $rootNode->asXML();

        // $dir = '/opt/remotelabz/' . $this->getUser()->getEmail() . '/' . $labName;
        // $fileName = $dir . '/Labfile';

        // try {
        //     $fileSystem->appendToFile($fileName, $rootNode->asXML());
        //     $fileSystem->chmod($dir, 0770, 0000, true);
        // } catch (IOExceptionInterface $exception) {
        //     echo "An error occurred while creating your directory at ".$exception->getPath();
        // }
        
        // return $fileName;
    }

    /**
     * @Route("/websockify/test", name="test_websockify")
     */
    public function testWebsockify(Request $request)
    {
        $client = new Client();
        $response = $client->request('POST', 'http://' . getenv('WORKER_SERVER') . ':' . getenv('WORKER_PORT') . '/lab', [
            'body' => '<lab>
            <name>Lab_name</name>
            <id>1</id>
            <tp_managed>1</tp_managed>
            <tp_type>supervised</tp_type>
            <tp_access>vpn</tp_access>
            <!-- personne qui a lancé le lab -->
            <user>
              <login>root@localhost</login>
            </user>
            <nodes>
              <device type="virtuel" property="X" id="6" script="" image="https://people.debian.org/~aurel32/qemu/amd64/debian_squeeze_amd64_standard.qcow2" order="1" hypervisor="qemu">
                <name>VM_1</name>
                <nom_brute>VM_1</nom_brute>
                <interface id="19" nom_physique="eth0" nom_virtuel="tap12" type="1" mac_address="00:AA:BB:CC:DD:EE" />
                <interface_control id="14" nom_physique="eth0_phy_VM1" nom_virtuel="eth0_ctrl_VM1" ipv4="" Masque="255.255.255.0" IPv6="2001:660:4601:7008::124" Prefix="" DNSv4="8.8.8.8" Gatewayv4="0.0.0.0" protocol="vnc" port="7220"/>
                <!-- if vpn access -->
                <direct_access>
                  <IPv4>1.2.3.4/24</IPv4>
                  <IPv6></IPv6>
                </direct_access>
                <system memory="512" disk="40"></system>
              </device>
              <device type="virtuel" property="X" id="9" script="" image="/usr/local/Virtualize/kvm-image/images/debian-testing20160512.img" relativ_path="/usr/local/Virtualize/kvm-image/images/img-rel" order="2">
                <name>VM_2</name>
                <interface id="20" nom_physique="eth0" nom_virtuel="tap13"/>
                <interface_control id="15" nom_physique="eth0_phy_ctrl_VM2" nom_virtuel="eth0_ctrl_VM2" ipv4="194.57.105.124" Masque="255.255.255.0" IPv6="2001:660:4601:7008::124" Prefix="" DNSv4="8.8.8.8" Gatewayv4="0.0.0.0" protocol="vnc" port="7221"/>
              </device>
              <device type="switch" property="switch" id="10" script="" image="Sans" relativ_path="Sans" order="3">
                <name>OVS1</name>
                <vpn>
                  <ipv4>1.2.3.4/24</ipv4>
                </vpn>
                <interface id="16" nom_physique="port1" nom_virtuel="port1"/>
                <interface id="21" nom_physique="port2" nom_virtuel="port2"/>
                <interface id="24" nom_physique="port3" nom_virtuel="port3"/>
              </device>
            </nodes>
            <networks>
              <network type="OVS" device_id="10">
                <port id="1" interface_id1="19" vlan1="1" interface_id2="16" vlan2="1"/>
                <port id="2" interface_id1="20" vlan1="1" interface_id2="21" vlan2="1"/>
              </network>
            </networks>
            <init>
              <network_lab>1.2.3.0/24</network_lab>
              <network_user>1.2.3.16/26</network_user>
              <serveur>
                <IPv4>194.57.105.124</IPv4>
                <IPv6>0</IPv6>
                <index_interface>12</index_interface>
                <index_interface_control>1</index_interface_control>
              </serveur>
            </init>
          </lab>',
            'headers' => [
                'Content-Type' => 'application/xml'
            ]
        ]);

        return $this->render('lab/vm_view.html.twig', [
          'host' => 'ws://' . getenv('WORKER_SERVER'),
          'port' => getenv('WORKER_PORT'),
          'path' => 'websockify'
        ]);
    }

    /**
     * @Route("/websockify/test/stop", name="test_stop_websockify")
     */
    public function testWebsockifyStop(Request $request)
    {
        $client = new Client();
        $response = $client->request('POST', 'http://' . getenv('WORKER_SERVER') . ':' . getenv('WORKER_PORT') . '/lab/stop', [
            'body' => '<lab>
            <name>Lab_name</name>
            <id>1</id>
            <tp_managed>1</tp_managed>
            <tp_type>supervised</tp_type>
            <tp_access>vpn</tp_access>
            <!-- personne qui a lancé le lab -->
            <user>
              <login>root@localhost</login>
            </user>
            <nodes>
              <device type="virtuel" property="X" id="6" script="" image="https://people.debian.org/~aurel32/qemu/amd64/debian_squeeze_amd64_standard.qcow2" order="1" hypervisor="qemu">
                <name>VM_1</name>
                <nom_brute>VM_1</nom_brute>
                <interface id="19" nom_physique="eth0" nom_virtuel="tap12" type="1" mac_address="00:AA:BB:CC:DD:EE" />
                <interface_control id="14" nom_physique="eth0_phy_VM1" nom_virtuel="eth0_ctrl_VM1" ipv4="" Masque="255.255.255.0" IPv6="2001:660:4601:7008::124" Prefix="" DNSv4="8.8.8.8" Gatewayv4="0.0.0.0" protocol="vnc" port="7220"/>
                <!-- if vpn access -->
                <direct_access>
                  <IPv4>1.2.3.4/24</IPv4>
                  <IPv6></IPv6>
                </direct_access>
                <system memory="512" disk="40"></system>
              </device>
              <device type="virtuel" property="X" id="9" script="" image="/usr/local/Virtualize/kvm-image/images/debian-testing20160512.img" relativ_path="/usr/local/Virtualize/kvm-image/images/img-rel" order="2">
                <name>VM_2</name>
                <interface id="20" nom_physique="eth0" nom_virtuel="tap13"/>
                <interface_control id="15" nom_physique="eth0_phy_ctrl_VM2" nom_virtuel="eth0_ctrl_VM2" ipv4="194.57.105.124" Masque="255.255.255.0" IPv6="2001:660:4601:7008::124" Prefix="" DNSv4="8.8.8.8" Gatewayv4="0.0.0.0" protocol="vnc" port="7221"/>
              </device>
              <device type="switch" property="switch" id="10" script="" image="Sans" relativ_path="Sans" order="3">
                <name>OVS1</name>
                <vpn>
                  <ipv4>1.2.3.4/24</ipv4>
                </vpn>
                <interface id="16" nom_physique="port1" nom_virtuel="port1"/>
                <interface id="21" nom_physique="port2" nom_virtuel="port2"/>
                <interface id="24" nom_physique="port3" nom_virtuel="port3"/>
              </device>
            </nodes>
            <networks>
              <network type="OVS" device_id="10">
                <port id="1" interface_id1="19" vlan1="1" interface_id2="16" vlan2="1"/>
                <port id="2" interface_id1="20" vlan1="1" interface_id2="21" vlan2="1"/>
              </network>
            </networks>
            <init>
              <network_lab>1.2.3.0/24</network_lab>
              <network_user>1.2.3.16/26</network_user>
              <serveur>
                <IPv4>194.57.105.124</IPv4>
                <IPv6>0</IPv6>
                <index_interface>12</index_interface>
                <index_interface_control>1</index_interface_control>
              </serveur>
            </init>
          </lab>',
            'headers' => [
                'Content-Type' => 'application/xml'
            ]
        ]);

        return new Response($response->getBody());
    }
}
