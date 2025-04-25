<?php

namespace App\Controller;

use DateTime;
use App\Entity\Device;
use App\Entity\User;
use App\Entity\NetworkInterface;
use App\Entity\NetworkSettings;
use App\Entity\EditorData;
use App\Entity\ControlProtocolType;
use App\Entity\OperatingSystem;
use App\Entity\InvitationCode;
use App\Form\DeviceType;
use App\Form\EditorDataType;
use App\Form\ControlProtocolTypeType;
use App\Repository\DeviceRepository;
use App\Repository\DeviceInstanceRepository;
use App\Repository\LabRepository;
use App\Repository\LabInstanceRepository;
use App\Repository\EditorDataRepository;
use App\Repository\ControlProtocolTypeRepository;
use App\Repository\FlavorRepository;
use App\Repository\OperatingSystemRepository;
use App\Repository\HypervisorRepository;
use App\Repository\NetworkInterfaceRepository;
use App\Security\ACL\LabVoter;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;
use function Symfony\Component\String\u;
use JMS\Serializer\SerializationContext;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\Security;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class DeviceController extends Controller
{
    private DeviceRepository $deviceRepository;
    private DeviceInstanceRepository $deviceInstanceRepository;
    private LabRepository $labRepository;
    private LabInstanceRepository $labInstanceRepository;
    private ControlProtocolTypeRepository $controlProtocolTypeRepository;
    private HypervisorRepository $hypervisorRepository;
    private FlavorRepository $flavorRepository;
    private OperatingSystemRepository $operatingSystemRepository;
    private NetworkInterfaceRepository $networkInterfaceRepository;

    /** @var LoggerInterface $logger */
    private $logger;

    public function __construct(
        LoggerInterface $logger,
        LabRepository $labRepository,
        LabInstanceRepository $labInstanceRepository,
        DeviceRepository $deviceRepository,
        DeviceInstanceRepository $deviceInstanceRepository,
        SerializerInterface $serializerInterface,
        ControlProtocolTypeRepository $controlProtocolTypeRepository,
        HypervisorRepository $hypervisorRepository,
        OperatingSystemRepository $operatingSystemRepository,
        FlavorRepository $flavorRepository,
        NetworkInterfaceRepository $networkInterfaceRepository,
        ManagerRegistry $managerRegistry,
        EntityManagerInterface $entityManager)
    {
        $this->deviceRepository = $deviceRepository;
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->labRepository = $labRepository;
        $this->labInstanceRepository = $labInstanceRepository;
        $this->logger = $logger;
        $this->serializer = $serializerInterface;
        $this->controlProtocolTypeRepository = $controlProtocolTypeRepository;
        $this->flavorRepository = $flavorRepository;
        $this->operatingSystemRepository = $operatingSystemRepository;
        $this->hypervisorRepository = $hypervisorRepository;
        $this->networkInterfaceRepository = $networkInterfaceRepository;
        $this->managerRegistry = $managerRegistry;
        $this->entityManager = $entityManager;
    }

    
	#[Get('/api/devices', name: 'api_devices')]
	#[Security("is_granted('ROLE_TEACHER_EDITOR')", message: "Access denied.")]
    #[Route(path: '/admin/devices', name: 'devices')]
    public function indexAction(Request $request)
    {
        $search = $request->query->get('search', '');
        $type = $request->query->get('type');
        $template = $request->query->get('template', true);
        $deviceArray = [];

        $criteria = Criteria::create()
            ->where(Criteria::expr()->contains('name', $search))
            ->andWhere(Criteria::expr()->eq('isTemplate', $template))
            ->orderBy([
                'name' => Criteria::ASC
            ]);

        $allDevices = $this->deviceRepository->matching($criteria);
        $devices = $allDevices->filter(function ($device) {
            return  count($device->getLabs()) == 0;
        });
        $count = $devices->count();
        $vmCount = $devices->filter(function ($device) {
            return $device->getType() === 'vm';
        })->count();
        $containerCount = $devices->filter(function ($device) {
            return $device->getType() === 'container';
        })->count();
        $physicalCount = $devices->filter(function ($device) {
            return $device->getType() === 'physical';
        })->count();
    
        if ($type) {
            switch ($type) {
                case 'vm':
                    $devices = $devices->filter(function ($device) {
                        return $device->getType() === 'vm';
                    });
                break;
                case 'container':
                    $devices = $devices->filter(function ($device) {
                        return $device->getType() === 'container';
                    });
                break;
                case 'physical':
                    $devices = $devices->filter(function ($device) {
                        return $device->getType() === 'physical';
                    });
                break;
            }
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($devices->getValues(), 200, [], ['api_get_device']);
        }

        return $this->render('device/index.html.twig', [
            'devices' => $devices,
            'count' => [
                'total' => $count,
                'vms' => $vmCount,
                'containers' => $containerCount,
                'physical' => $physicalCount
            ],
            'search' => $search
        ]);
    }

    
	#[Post('/api/labs/{id<\d+>}/nodes', name: 'api_get_devices')]
    #[Route(path: '/devices', name: 'get_devices')]
    public function indexActionTest(Request $request, int $id)
    {
        $lab = $this->labRepository->findById($id);
        $this->denyAccessUnlessGranted(LabVoter::SEE_DEVICE, $lab);

        $nodeData = json_decode($request->getContent(), true);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');

        if($nodeData['edition'] == 0 && $nodeData['labInstance'] != null) {
            $labInstance = $this->labInstanceRepository->find($nodeData['labInstance']);
            $devices = $this->deviceRepository->findByLabInstance($labInstance);
        }
        if($nodeData['edition'] == 0 && $nodeData['labInstance'] == null) {
            $response->setContent(json_encode([
                'code'=> 400,
                'status'=>'fail',
                'message' => 'Lab Instance is null']));
                return $response;
        }
        if($nodeData['edition'] == 1) {
            $devices = $this->deviceRepository->findByLab($lab);
        }
        $data = [];
        foreach($devices as $device){

            if($nodeData['edition'] == 0) {
                $deviceInstance = $this->deviceInstanceRepository->findByDeviceAndLabInstance($device, $labInstance);
                if ($device->getType() != "switch") {
                    if($deviceInstance->getState() == 'started') {
                        $status = 2;
                    }
                    else if($deviceInstance->getState() == 'stopped' || $deviceInstance->getState() == 'error') {
                        $status = 0;
                    }
                    else if ($deviceInstance->getState() == 'starting'){
                        $status = 3;
                    }
                    else if ($deviceInstance->getState() == 'stopping'){
                        $status = 1;
                    }
                }
                else {
                    $status = 2;
                }
                
                $uuid = $deviceInstance->getUuid();
            }
            else {
                $status = 0;
            }

            $controlProtocolTypes = [];
            $vnc = false;
            $login = false;
            $serial = false;
            foreach($device->getControlProtocolTypes() as $controlProtocolType) {
                //$controlProtocolTypes[$controlProtocolType->getId()] = $controlProtocolType->getName();
                array_push($controlProtocolTypes, $controlProtocolType->getName());
                if ($controlProtocolType->getName() == 'vnc') {
                    $vnc = true;
                }
                if ($vnc == false && $controlProtocolType->getName() == 'login') {
                    $login = true;
                }
                if ($login == false && $vnc == false && $controlProtocolType->getName() == 'serial') {
                    $serial == true;
                }
            }

            $user = $this->getUser();
            if (in_array('login', $controlProtocolTypes) && ($user instanceof User && ($user->isAdministrator() || (($user->hasRole("ROLE_TEACHER") || $user->hasRole("ROLE_TEACHER_EDITOR")) && $user == $lab->getAuthor())))) {
                array_push($controlProtocolTypes, "admin");
            }
            
            //choose the control protocol to open the console
            if ($vnc == true) {
                $finalControlProtocolType = 'vnc';
            }
            else if ($login == true) {
                $finalControlProtocolType = 'login';
            }
            else if ($serial == true) {
                $finalControlProtocolType = 'serial';
            }
            else {
                $finalControlProtocolType = null;
            }

            $data[$device->getId()] = [
                "id"=> $device->getId(),
                "name"=> $device->getName(),
                "type"=> $device->getType(),
                //"console"=> $device->getConsole(),
                "delay"=> $device->getDelay(),
                "left"=> $device->getEditorData()->getY(),
                "top"=> $device->getEditorData()->getX(),
                "icon"=> $device->getIcon(),
                //"image"=> $device->getImage(),
                "ram"=> $device->getFlavor()->getMemory(),
                //"url"=> $device->getUrl(),
                "template"=> $device->getTemplate(),
                "status"=> $status,
                "ethernet"=> $device->getEthernet(),
                "console" => $controlProtocolTypes,
                "networkInterfaceTemplate"=> $device->getNetworkInterfaceTemplate()
            ];

            if (isset($uuid)) {
                $data[$device->getId()]["uuid"] = $uuid;
            }
        }

        
        $response->setContent(json_encode([
            'code'=> 200,
            'status'=>'success',
            'message' => 'Successfully listed nodes (60026).',
            'data' => $data]));
        return $response;
    }

    
	#[Get('/api/devices/{id<\d+>}', name: 'api_get_device')]
    #[Route(path: '/admin/devices/{id<\d+>}', name: 'show_device', methods: 'GET')]
    #[Route(path: '/devices/{id<\d+>}', name: 'show_device_public', methods: 'GET')]
    public function showAction(Request $request, int $id)
    {
        $device = $this->deviceRepository->find($id);
        if (!$device) {
            throw new NotFoundHttpException("Device " . $id . " does not exist.");
        }

        $canViewDevice = false;
        $user = $this->getUser();
        if ($user instanceof InvitationCode) {
            foreach ($user->getLab()->getDevices() as $userDevice) {
                if ($userDevice == $device) {
                    $canViewDevice = true;
                }
            }
        }
        else {
            if ($user->getHighestRole() == "ROLE_TEACHER" || $user->getHighestRole() == "ROLE_TEACHER_EDITOR") {
                $labInstances=$this->labInstanceRepository->findByUserMembersAndGroups($user);
            }
            else {
                $labInstances=$this->labInstanceRepository->findByUserAndGroups($user);
            }
            
            $defaultgroupinstances=$this->labInstanceRepository->findByDefaultGroup();
                foreach($defaultgroupinstances as $defaultgroupinstance)
                    array_push($labInstances,$defaultgroupinstance);
            

            /*
            foreach($labInstances as $labInstance) {
                $this->logger->debug("".$labInstance->getGroup()->getName());
            }*/

            foreach($labInstances as $labInstance) {
                foreach($labInstance->getDeviceInstances() as $deviceInstance) {
                    //var_dump($deviceInstance->getDevice()->getId());
                    if ($deviceInstance->getDevice() == $device) {
                        $canViewDevice = true;
                        break;
                    }
                }
            }

            if ($user->isAdministrator() || $user->isEditor()) {
                $canViewDevice = true;
            }
        }

        if ($canViewDevice == false) {
          throw new AccessDeniedHttpException("Access denied.");
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($device, 200, [], ['api_get_device']);
        }
        
        return $this->render('device/view.html.twig', ['device' => $device]);
    }

    
	#[Post('/api/labs/{labId<\d+>}/nodes/{id<\d+>}', name: 'api_get_node')]
    public function showActionTest(Request $request, int $id, int $labId)
    {
        $lab = $this->labRepository->find($labId);
        $this->denyAccessUnlessGranted(LabVoter::SEE_DEVICE, $lab);

        $device = $this->deviceRepository->find($id);
        $nodeData = json_decode($request->getContent(), true);
        if (!$device) {
            throw new NotFoundHttpException("Device " . $id . " does not exist.");
        }

        if($nodeData['edition'] == 0 && $nodeData['labInstance'] != null) {
            $labInstance = $this->labInstanceRepository->find($nodeData['labInstance']);
            $deviceInstance = $this->deviceInstanceRepository->findByDeviceAndLabInstance($device, $labInstance);
            if ($device->getType() != "switch") {
                if($deviceInstance->getState() == 'started') {
                    $status = 2;
                }
                else if ($deviceInstance->getState() == 'stopped'|| $deviceInstance->getState() == 'error'){
                    $status = 0;
                }
                else if ($deviceInstance->getState() == 'starting'){
                    $status = 3;
                }
                else if ($deviceInstance->getState() == 'stopping'){
                    $status = 1;
                }
            }
            else {
                $status = 2;
            }
            
            $uuid = $deviceInstance->getUuid();
        }
        if($nodeData['edition'] == 0 && $nodeData['labInstance'] == null) {
            $response->setContent(json_encode([
                'code'=> 400,
                'status'=>'fail',
                'message' => 'Lab Instance is null']));
                return $response;
        }
        if($nodeData['edition'] == 1) {
            $status = 0;
        }
        $controlProtocolTypes = [];
        $controlProtocolTypesName = [];
        $vnc = false;
        $login = false;
        $serial = false;
        foreach($device->getControlProtocolTypes() as $controlProtocolType) {
            //$controlProtocolTypes[$controlProtocolType->getId()] = $controlProtocolType->getName();
            array_push($controlProtocolTypes, $controlProtocolType->getId());
            array_push($controlProtocolTypesName, $controlProtocolType->getName());
            if ($controlProtocolType->getName() == 'vnc') {
                $vnc = true;
            }
            if ($vnc == false && $controlProtocolType->getName() == 'login') {
                $login = true;
            }
            if ($login == false && $vnc == false && $controlProtocolType->getName() == 'serial') {
                $serial == true;
            }
        }
        $user = $this->getUser();
        if (in_array('login', $controlProtocolTypesName) && ($user instanceof User && ($user->isAdministrator() || (($user->hasRole("ROLE_TEACHER") || $user->hasRole("ROLE_TEACHER_EDITOR")) && $user == $lab->getAuthor())))) {
            array_push($controlProtocolTypesName, "admin");
        }
        //choose the control protocol to open the console
        if ($vnc == true) {
            $finalControlProtocolType = 'vnc';
        }
        else if ($login == true) {
            $finalControlProtocolType = 'login';
        }
        else if ($serial == true) {
            $finalControlProtocolType = 'serial';
        }
        else {
            $finalControlProtocolType = null;
        }

        $data = [
            "name"=> $device->getName(),
            "type"=> $device->getType(),
            //"console"=> $device->getConsole(),
            "delay"=> $device->getDelay(),
            "left"=> $device->getEditorData()->getY(),
            "top"=> $device->getEditorData()->getX(),
            "icon"=> $device->getIcon(),
            //"image"=> $device->getImage(),
            //"url"=> $device->getUrl(),
            "config"=>$device->getConfig(),
            "status"=> $status,
            "ethernet"=>$device->getEthernet(), 
            "cpu"=>$device->getNbCpu(),
            "core"=>$device->getNbCore(),
            "socket"=>$device->getNbSocket(),
            "thread"=>$device->getNbThread(),
            "flavor"=>$device->getFlavor()->getId(),
            "template"=>$device->getTemplate(),
            "brand"=>$device->getBrand(),
            "model"=>$device->getModel(),
            "controlProtocol" => $controlProtocolTypes,
            //"hypervisor" => $device->getHypervisor()->getId(),
            "operatingSystem" => $device->getOperatingSystem()->getId(),
            "console" => $controlProtocolTypesName,
            "networkInterfaceTemplate"=>$device->getNetworkInterfaceTemplate()
        ];

        if (isset($uuid)) {
            $data['uuid'] = $uuid;
        }

        $response = new Response();
        $response->setContent(json_encode([
            'code'=> 200,
            'status'=>'success',
            'message' => 'Successfully listed node (60025).',
            'data' => $data]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    
	#[Post('/api/devices', name: 'api_new_device')]
	#[Security("is_granted('ROLE_TEACHER_EDITOR')", message: "Access denied.")]
    #[Route(path: '/admin/devices/new', name: 'new_device')]
    #[Route(path: '/admin/devices/physical/new', name: 'new_physical_device')]
    public function newAction(Request $request)
    {
        $device = new Device();

        if(($params = $request->query->all()) && (isset($params['os'])) && (isset($params['model']))) {
            $hypervisor = $this->hypervisorRepository->findByName('lxc');
            $type = "container"; 
            $device->setType($type);
            $device->setHypervisor($hypervisor);
            $osName = $params['os']."-".$params['model'];
            if ($operatingSystem = $this->operatingSystemRepository->findByName($osName)) {
                $device->setName($osName);
                $device->setModel($params['model']);
                $device->setBrand($params['os']);
                $device->setOperatingSystem($operatingSystem);
            }
            
        }

        if ("new_physical_device" === $request->get('_route')) {
            $virtuality = false;
        }
        else {
            $virtuality = true;
        }

        $deviceForm = $this->createForm(DeviceType::class, $device, ["virtuality" => $virtuality]);
        
        $deviceForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $device = json_decode($request->getContent(), true);
            $deviceForm->submit($device);
        }

        if ($deviceForm->isSubmitted() && $deviceForm->isValid()) {
            /** @var Device $device */
           $device = $deviceForm->getData();
            foreach ($device->getControlProtocolTypes() as $proto) {
                $proto->addDevice($device);
                $this->logger->debug($proto->getName());
            }
            //$this->addNetworkInterface($device);
            $this->setDeviceHypervisorToOS($device);
            $device->setIcon('Server_Linux.png');
            $device->setAuthor($this->getUser());
            $device->setVirtuality($virtuality);
            $entityManager = $this->entityManager;
            $entityManager->persist($device);
            $entityManager->flush();
            if ($device->getIsTemplate() == true) {
                $controlProtocolTypes= [];
                foreach($device->getControlProtocolTypes() as $controlProtocolType) {
                    array_push($controlProtocolTypes, $controlProtocolType->getId());
                }
                if ($controlProtocolTypes == []) {
                    $controlProtocolTypes = '';
                }
                $deviceData = [
                    "name" => $device->getName(),
                    //"type" => $device->getType(),
                    "icon" => $device->getIcon(),
                    "operatingSystem" => $device->getOperatingSystem()->getId(),
                    "flavor" => $device->getFlavor()->getId(),
                    "controlProtocol" => $controlProtocolTypes,
                    //"hypervisor" => $device->getHypervisor()->getId(),
                    "brand" => $device->getBrand(),
                    "model" => $device->getModel(),
                    "description" => $device->getName(),
                    "networkInterfaceTemplate"=>$device->getNetworkInterfaceTemplate(),
                    "cpu" => $device->getNbCpu(),
                    "core" => $device->getNbCore(),
                    "socket" => $device->getNbSocket(),
                    "thread" => $device->getNbSocket(),
                    "context" => "remotelabz",
                    "config_script" => "embedded",
                    "ethernet" => 1,
                    "virtuality" => $virtuality
                ];

                $yamlContent = Yaml::dump($deviceData,2);
                $fileName = u($device->getName())->camel();
                file_put_contents($this->getParameter('kernel.project_dir')."/config/templates/".$device->getId()."-". $fileName . ".yaml", $yamlContent);
            }
            
            if ('json' === $request->getRequestFormat()) {
                return $this->json($device, 201, [], ['api_get_device']);
            }

            $this->addFlash('success', 'Device has been created.');

            return $this->redirectToRoute('devices');
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($deviceForm, 200, [], ['api_get_device']);
        }

        return $this->render('device/new.html.twig', [
            'form' => $deviceForm->createView(),
            'data' => $device,
            'virtuality' => $virtuality
        ]);
    }

    
	#[Post('/api/devices/lxc_params', name: 'api_new_lxc_device_params')]
	#[Post('/api/devices/lxc', name: 'api_new_lxc_device')]
	#[Security("is_granted('ROLE_TEACHER_EDITOR')", message: "Access denied.")]
    #[Route(path: '/admin/devices/new_lxc', name: 'new_lxc_device')]
    public function newLxcAction(Request $request, UrlGeneratorInterface $router)
    {
        $file=file_get_contents("https://images.linuxcontainers.org/images");
        $dom = new \DOMDocument();
        $dom->loadHtml($file);
        $links = $dom->getElementsByTagName('a');
        $os = [];
        foreach($links as $link){
            if($link->nodeValue !== "../") {
                array_push($os, ucfirst(substr($link->nodeValue, 0, -1)));
            }
        }

        $os_json = json_encode($os);

        if ('json' === $request->getRequestFormat()) {
            if ($request->get("_route") == "api_new_lxc_device_params") {
                $data = json_decode($request->getContent(), true);
                if (!isset($data['version']) && isset($data['os'])) {
                    $fileVersion = file_get_contents("https://images.linuxcontainers.org/images/". $data['os']);
                    $dom = new \DOMDocument();
                    $dom->loadHtml($fileVersion);
                    $links = $dom->getElementsByTagName('a');
                    $versions = [];
                    foreach($links as $link){
                        if($link->nodeValue !== "../") {
                            array_push($versions, substr($link->nodeValue, 0, -1));
                        }
                    }
                    return $this->json($versions, 200, [], []);
                }
                if (!isset($data['date']) && isset($data['version'])) {
                    $fileVersion = file_get_contents("https://images.linuxcontainers.org/images/". $data['os'].$data['version']."amd64/default/");
                    $dom = new \DOMDocument();
                    $dom->loadHtml($fileVersion);
                    $links = $dom->getElementsByTagName('a');
                    $updates = [];
                    foreach($links as $link){
                        if($link->nodeValue !== "../") {
                            array_push($updates, $link->nodeValue);
                        }
                    }
                    $update = end($updates);
                    return $this->json($update, 200, [], []);
                }
            }
            //return $this->json($deviceForm, 200, [], ['api_get_device']);
        }

        if ($request->get("_route") == "api_new_lxc_device") {
            $data = json_decode($request->getContent(), true);
            $hypervisor = $this->hypervisorRepository->findByName('lxc');
            $entityManager = $this->entityManager;
            $osName = ucfirst($data['os'])."-".$data['version'];
            if(!$operatingSystem = $this->operatingSystemRepository->findByName($osName)) {
                $newOs = new OperatingSystem();
                $newOs->setName($osName);
                $newOs->setHypervisor($hypervisor);
                $newOs->setImageFilename($osName);
                $entityManager->persist($newOs);
                $entityManager->flush();
            }
            $values = ['os'=> ucfirst($data['os']), 'model'=> $data['version']];
            return $this->json($values, 200, [], []);
        }

        return $this->render('device/newLxc.html.twig', [
            'os' => $os,
            'props' => $os_json,
        ]);
    }

    
	#[Post('/api/labs/{id<\d+>}/node', name: 'api_new_devices')]
    public function newActionTest(Request $request, int $id, HyperVisorRepository $hypervisorRepository, ControlProtocolTypeRepository $controlProtocolTypeRepository, OperatingSystemRepository $operatingSystemRepository )
    {
        $data = json_decode($request->getContent(), true);
        if ($data["virtuality"] == 0) {
            preg_match_all('!\d+!', $data["template"], $templateNumber);
            $sameDevice = $this->deviceRepository->findByTemplateBeginning($templateNumber[0][0]."-");
            if ($sameDevice != null) {
                $response = new Response();
                $response->setContent(json_encode([
                    'code' => 400,
                    'status'=> 'fail',
                    'message' => 'A device using this template already exists.'
                ]));
                $response->headers->set('Content-Type', 'application/json');
                return $response;
            }
        } 
        
        $lab = $this->labRepository->findById($id);
        $this->denyAccessUnlessGranted(LabVoter::EDIT_DEVICE, $lab);

        $no_array = true;
        $ids = [];
        if($data["virtuality"] == 1 && $data['count'] > 1) {
            $no_array = false;
            for($i = 1; $i <= $data['count']; $i++) {
                if ($i > 1)
                {
                    $data['left'] =  $data['left'] + ( ( $i -1 ) % 5 )   * 60   ;
                    $data['top'] =  $data['top'] + ( intval( ( $i -1 ) / 5 )  * 80 ) ;
                }
                $ids[] = $this->addDevice($lab, $data);
            };
        }
        else {
            $id = $this->addDevice($lab, $data);
        }

        

        $response = new Response();
        $response->setContent(json_encode([
            'code' => 200,
            'status'=> 'success',
            'message' => 'Lab has been saved (60023).',
            'data' => [
                'id'=> $no_array? $ids : $id,
            ]]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    public function addDevice($lab, $data)
    {
        $device = new Device();
        $editorData = new EditorData();
        
        //$hypervisor = $this->hypervisorRepository->findById($data['hypervisor']);
        //$controlProtocolType = $this->controlProtocolTypeRepository->findById($data['controlProtocol']);
        foreach($data['controlProtocol'] as $controlProtocolTypeId) {
            $controlProtocolType = $this->controlProtocolTypeRepository->findById($controlProtocolTypeId);
            $device->addControlProtocolType($controlProtocolType[0]);
        }
        $flavor = $this->flavorRepository->findById($data['flavor']);
        $operatingSystem = $this->operatingSystemRepository->findById($data['operatingSystem']);
        if($data['core'] === '') {
            $device->setNbCore(null);
        }
        else {
            $device->setNbCore($data['core']);
        }

        if($data['socket'] === '') {
            $device->setNbSocket(null);
        }
        else {
            $device->setNbSocket($data['socket']);
        }
        if($data['thread'] === '') {
            $device->setNbThread(null);
        }
        else {
            $device->setNbThread($data['thread']);
        }
        //$device->setCount($data['count']);
        if($data['name'] != '') {
            $device->setName($data['name']);
        }
        else {
            $device->setName('Unamed device');
        }

        if($lab->getIsTemplate() == true) {
            $device->setIsTemplate(true);
        }
        else {
            $device->setIsTemplate(false);
        }
        //$device->setType($data['type']);
        $device->setNetworkInterfaceTemplate($data['networkInterfaceTemplate']);
        $device->setIcon($data['icon']);
        $device->setAuthor($this->getUser());
        $device->setBrand($data['brand']);
        $device->setFlavor($flavor[0]);
        $device->setOperatingSystem($operatingSystem[0]);
        $this->setDeviceHypervisorToOS($device);
        /*if($controlProtocolType != null) {
            $device->addControlProtocolType($controlProtocolType[0]);
        }*/
        if($data['delay'] != '') {
            $device->setDelay($data['delay']);
        }
        else {
            $device->setDelay(0);
        }
        $device->setPostFix($data['postfix']);
        $device->setLaunchOrder(0);
        $device->setVirtuality($data['virtuality']);
        if($data['cpu'] != '') {
            $device->setNbCpu($data['cpu']);
        }
        else{
            $device->setNbCpu(1);
        }
        $device->setCreatedAt(new \DateTime());
        $device->setTemplate($data['template']);
        $device->setModel($data['model']);

        if($data['top'] != '') {
            $editorData->setX($data['top']);
        }
        else {
            $editorData->setX(0);
        }
       
        if($data['left'] != '') {
            $editorData->setY($data['left']);
        }
        else {
            $editorData->setY(0);
        }
        $device->setEditorData($editorData);

        //Check validity of cpu number with other parameters
        $total=1;
        if ($device->getNbCore()!=0)
            $total=$total*$device->getNbCore();
        if ($device->getNbSocket()!=0)
            $total=$total*$device->getNbSocket();
        if ($device->getNbThread()!=0)
             $total=$total*$device->getNbThread();
        //$this->logger->debug("Total CPU :".$total);
        
        if ($device->getNbCpu() < $total ) {
            $device->setNbCpu($total);
        }
        
        //preg_match_all('!\d+!', $device->getTemplate(), $templateNumber);
        
        if ($device->getTemplate() != null) {
            
            preg_match('/^(\d+)(.*)$/', $device->getTemplate(), $templateNumber);
                        
            $this->logger->debug("Device template: ".$device->getTemplate());
            if ($templateNumber != null) {
            $this->logger->debug("template number: ".$templateNumber[1]);
            
                    $template = $this->deviceRepository->find($templateNumber[2]);
                    $device->setIp($template->getIp());
                    $device->setPort($template->getPort());
            }
        } else $this->logger->debug("Device has no template");
        
        
        $entityManager = $this->entityManager;
        $entityManager->persist($device);
        $lab->addDevice($device);
        $entityManager->flush();
        $editorData->setDevice($device);
        $entityManager->flush();

        $this->logger->info("Device named '" . $device->getName() . "' created");

        return $device->getId();
    }
    
	#[Put('/api/devices/{id<\d+>}', name: 'api_edit_device')]
	#[Security("is_granted('ROLE_TEACHER_EDITOR')", message: "Access denied.")]
    #[Route(path: '/admin/devices/{id<\d+>}/edit', name: 'edit_device')]
    public function updateAction(Request $request, int $id)
    {
        if (!$device = $this->deviceRepository->find($id)) {
            throw new NotFoundHttpException("Device " . $id . " does not exist.");
        }

        $isTemplate = $device->getIsTemplate();
        $oldName = $device->getName();
        $virtuality = $device->getVirtuality();
        $this->logger->info("Device ".$device->getName()." modification asked by user ".$this->getUser()->getFirstname()." ".$this->getUser()->getName());
        $deviceForm = $this->createForm(DeviceType::class, $device, [
            'nb_network_interface' => count($device->getNetworkInterfaces()),
            'virtuality' => $virtuality]
        );
        $deviceForm->handleRequest($request);

        //$this->logger->debug("Nb network interface:".$request->query->get('nb_network_interface'));

        foreach ($device->getControlProtocolTypes() as $proto) {
            $proto->removeDevice($device);
            //$this->logger->debug("Before submit: ".$device->getName()." has control protocol ".$proto->getName());
        }
        $entityManager = $this->entityManager;
        $entityManager->persist($device);
        $entityManager->flush();

        if ($request->getContentType() === 'json') {
            $device_json = json_decode($request->getContent(), true);
            
            $device_json['networkInterfaces']=count($device->getNetworkInterfaces());
            $controlProtocolType_json=$device_json['controlProtocolTypes'];
            $device_json['controlProtocolTypes']=array();

            if ( !empty($controlProtocolType_json) ) {
                foreach ($controlProtocolType_json as $controlProtoType){
                    $this->logger->debug("ControlProtoType : ",$controlProtoType);
                    //array_push($device_json['controlProtocolTypes'],$this->controlProtocolTypeRepository->find($controlProtoType['id']));
                    array_push($device_json['controlProtocolTypes'],(string)$controlProtoType['id']);
                    $this->logger->debug("controlProtocolTypes  : ",$device_json['controlProtocolTypes']);
                }
            }
            
                
            /*$device_json=["id" => 52,
            "name"=>"Linux Alpine","brand"=>"Test","model"=>"","operatingSystem"=>5,
            "hypervisor"=>3,"flavor"=>2,"nbCpu"=>"1","networkInterfaces"=>1,
            "controlProtocolTypes" => ["1" ]];*/

            //$this->logger->debug("before submit json :",$device_json);

            $deviceForm->submit($device_json, false);
        }

        if ($deviceForm->isSubmitted() && $deviceForm->isValid()) {
            /** @var Device $device */

            foreach ($device->getControlProtocolTypes() as $proto) {
                $proto->addDevice($device);
                //$this->logger->debug("Add for ".$device->getName()." control protocol ".$proto->getName());
                //$this->logger->debug($device->getName()." has control protocol ".$proto->getName());
            }
            
            $device->setLastUpdated(new DateTime());
            //Check validity of cpu number with other parameters
            $total=1;
            if ($device->getNbCore()!=0)
                $total=$total*$device->getNbCore();
            if ($device->getNbSocket()!=0)
                $total=$total*$device->getNbSocket();
            if ($device->getNbThread()!=0)
                 $total=$total*$device->getNbThread();
            //$this->logger->debug("Total CPU :".$total);
            
            if ($device->getNbCpu() < $total ) {
                $device->setNbCpu($total);
            }

            $device->setIcon("Server_Linux.png");
            $this->setDeviceHypervisorToOS($device);

            $controlProtocolTypes= [];
            foreach($device->getControlProtocolTypes() as $controlProtocolType) {
                array_push($controlProtocolTypes, $controlProtocolType->getId());
            }
            if ($controlProtocolTypes == []) {
                $controlProtocolTypes = '';
            }
            
            $deviceData = [
                "name" => $device->getName(),
                //"type" => $device->getType(),
                "icon" => $device->getIcon(),
                "operatingSystem" => $device->getOperatingSystem()->getId(),
                "flavor" => $device->getFlavor()->getId(),
                "controlProtocol" => $controlProtocolTypes,
                //"hypervisor" => $device->getHypervisor()->getId(),
                "brand" => $device->getBrand(),
                "model" => $device->getModel(),
                "description" => $device->getName(),
                "networkInterfaceTemplate"=>$device->getNetworkInterfaceTemplate(),
                "cpu" => $device->getNbCpu(),
                "core" => $device->getNbCore(),
                "socket" => $device->getNbSocket(),
                "thread" => $device->getNbSocket(),
                "context" => "remotelabz",
                "config_script" => "embedded",
                "ethernet" => 1,
                "virtuality"=> $virtuality
            ];

            $yamlContent = Yaml::dump($deviceData);
            $fileName = u($device->getName())->camel();
            $oldFileName = u($oldName)->camel();
            if ($isTemplate == true && $device->getIsTemplate() == true) {
                if ($oldName == $device->getName()) {
                    file_put_contents($this->getParameter('kernel.project_dir')."/config/templates/".$device->getId()."-". $fileName . ".yaml", $yamlContent);
                }
                else {
                    if (is_file($this->getParameter('kernel.project_dir')."/config/templates/".$device->getId()."-". $oldFileName . ".yaml")) {
                        unlink($this->getParameter('kernel.project_dir')."/config/templates/".$device->getId()."-". $oldFileName . ".yaml");
                    }
                    file_put_contents($this->getParameter('kernel.project_dir')."/config/templates/".$device->getId()."-". $fileName . ".yaml", $yamlContent);
                }
            }
            else if($isTemplate == true && $device->getIsTemplate() == false) {
                if (is_file($this->getParameter('kernel.project_dir')."/config/templates/".$device->getId()."-". $oldName . ".yaml")) {
                    unlink($this->getParameter('kernel.project_dir')."/config/templates/".$device->getId()."-". $oldName . ".yaml");
                }
            }
            else if($isTemplate == false && $device->getIsTemplate() == true) {
                file_put_contents($this->getParameter('kernel.project_dir')."/config/templates/".$device->getId()."-". $fileName . ".yaml", $yamlContent);
            }

            $entityManager = $this->entityManager;
            $entityManager->persist($device);
            $entityManager->flush();

            if ('json' === $request->getRequestFormat()) {
                return $this->json($device, 200, [], ['api_get_device']);
            }
            $this->logger->info("Device ".$device->getName()." modification submitted");

            $this->addFlash('success', 'Device has been updated.');

            return $this->redirectToRoute('show_device', ['id' => $id]);
        } elseif ($deviceForm->isSubmitted() && !$deviceForm->isValid()) {
            $this->logger->error("Device ".$device->getName()."modification submitted but form not valid");
                foreach ($deviceForm as $fieldName => $formField) {
                    if ($formField->getErrors() != "")
                        $this->logger->debug($fieldName." Error : ".$formField->getErrors());
                }
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($device, 200, [], ['api_get_device']);
        }

        return $this->render('device/new.html.twig', [
            'form' => $deviceForm->createView(),
            'data' => $device,
            'virtuality' => $virtuality
        ]);
    }

    
	#[Put('/api/labs/{labId<\d+>}/node/{id<\d+>}', name: 'api_edit_node')]
    public function updateActionTest(Request $request, int $id, int $labId)
    {
        if (!$lab = $this->labRepository->find($labId)){
            throw new NotFoundHttpException("Lab ".$labId." does not exist.");
        }
        $this->denyAccessUnlessGranted(LabVoter::EDIT_DEVICE, $lab);

        if (!$device = $this->deviceRepository->findById($id)[0]){
            throw new NotFoundHttpException("Device ".$id. " does not exist.");
        }
        if (!$lab->getDevices()->contains($device)) {
            throw new BadRequestHttpException("Lab ".$lab->getId()." does not contain device ". $device->getId().".");
        }

        $data = json_decode($request->getContent(), true);   

        if(isset($data['count'])) {
            $device->setCount($data['count']);
        }
        if(isset($data['name'])) {
            if($data['name'] != '') {
                $oldDeviceName = $device->getName();
                $device->setName($data['name']);

            }
        }
        if(isset($data['postfix'])) {
            $device->setPostFix($data['postfix']);
        }

        if(isset($data['config'])) {
            $device->setConfig($data['config']);
        }

        if(isset($data['networkInterfaceTemplate'])) {
            $oldTemplate = $device->getNetworkInterfaceTemplate();
            $device->setNetworkInterfaceTemplate($data['networkInterfaceTemplate']);
            foreach($device->getNetworkInterfaces() as $networkInterface) {
                preg_match_all('!\d+!', $networkInterface->getName(), $numbers);
                $netId = (int)$numbers[0][count($numbers[0]) -1];

                $networkInterface->setName($device->getNetworkInterfaceTemplate().$netId);
            }
            
        }
        if(isset($data['controlProtocol'])) {
            foreach ($device->getControlProtocolTypes() as $proto) {
                $proto->removeDevice($device);
                //$this->logger->debug("Before submit: ".$device->getName()." has control protocol ".$proto->getName());
            }
            $entityManager = $this->entityManager;
            $entityManager->persist($device);
            $entityManager->flush();

            if(sizeof($data['controlProtocol']) > 0) {
                foreach($data['controlProtocol'] as $controlProtocolTypeId) {
                    $controlProtocolType = $this->controlProtocolTypeRepository->findById($controlProtocolTypeId);
                    $device->addControlProtocolType($controlProtocolType[0]);
                }
            }
        }
            
        if(isset($data['core'])) {
            if($data['core'] === '') {
                $device->setNbCore(null);
            }
            else {
                
                $device->setNbCore((int)$data['core']);
            }
        }
        if(isset($data['cpu'])) {
            if($data['cpu'] === '' || $data['cpu'] === 0) {
                $device->setNbCpu(1);
            }
            else {
                
                $device->setNbCpu((int)$data['cpu']);
            }
        }
        if(isset($data['socket'])) {
            if($data['socket'] === '') {
                $device->setNbSocket(null);
            }
            else {
                $device->setNbSocket((int)$data['socket']);
            }
        }
        if(isset($data['thread'])) {
            if($data['thread'] === '') {
                $device->setNbThread(null);
            }
            else {
                $device->setNbThread((int)$data['thread']);
            }
        }
            
        if(isset($data['icon'])) {
            $device->setIcon($data['icon']);
        }
        if(isset($data['brand'])) {
            $device->setBrand($data['brand']);
        }
        
        if(isset($data['flavor'])) {
            $flavor = $this->flavorRepository->findById($data['flavor']);
            $device->setFlavor($flavor[0]);
        }
        if(isset($data['operatingSystem'])) {
            $operatingSystem = $this->operatingSystemRepository->findById($data['operatingSystem']);
            $device->setOperatingSystem($operatingSystem[0]);
            $this->setDeviceHypervisorToOS($device);
        }
        if(isset($data['delay'])) {
            if($data['delay'] != '') {
                $device->setDelay($data['delay']);
            }
            else {
                $device->setDelay(0);
            }
        }

        if(isset($data['template'])) {
            $device->setTemplate($data['template']);
        }
        if(isset($data['model'])) {
            $device->setModel($data['model']);
        }
        
    
        if(isset($data['top']) && isset($data['left'])) {
            if( $data['top'] !== null || $data['left'] !== null) {
                $editorData = $device->getEditorData();
                if($data['top'] != '') {
                    $editorData->setX($data['top']);
                }
                if($data['left'] != '') {
                    $editorData->setY($data['left']);
                }
                $device->setEditorData($editorData);
            }
        }

        //Check validity of cpu number with other parameters
        $total=1;
        if ($device->getNbCore()!=0)
            $total=$total*$device->getNbCore();
        if ($device->getNbSocket()!=0)
            $total=$total*$device->getNbSocket();
        if ($device->getNbThread()!=0)
             $total=$total*$device->getNbThread();
        //$this->logger->debug("Total CPU :".$total);
        
        if ($device->getNbCpu() < $total ) {
            $device->setNbCpu($total);
        }

        preg_match('!(\d+)(.*)!', $device->getTemplate(), $templateNumber);
        if (is_array($templateNumber) && isset($templateNumber[0]) && !empty($templateNumber[0])) {
            $template = $this->deviceRepository->find($templateNumber[0][0]);
            $device->setIp($template->getIp());
            $device->setPort($template->getPort());
        }

        $entityManager = $this->entityManager;
        $entityManager->persist($device);
        $entityManager->flush();

        $this->logger->info("Device named" . $device->getName() . " modified");

        $response = new Response();
        $response->setContent(json_encode([
            'code' => 201,
            'status'=> 'success',
            'message' => 'Lab has been saved (60023).']));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /*    /*public function updateEditorDataAction(Request $request, int $id, EditorDataRepository $editorDataRepository)
    {*/
        /** @var EditorDataRepository $editorDataRepository */
        /*$editorDataRepository = $this->managerRegistry->getRepository(EditorData::class);
        $deviceEditorData = $editorDataRepository->findByDeviceId($id);
        //$device = $this->deviceRepository->find($id);

        // if (! ($deviceEditorData instanceof EditorData)) {
        //     throw new NotFoundHttpException("Device " . $id . " does not exist.");
        // }

        if ($request->getContentType() === 'json') {
            $editorData = json_decode($request->getContent(), true);

            if (!$editorData) {
                throw new BadRequestHttpException("Incorrect JSON.");
            }
        }

        //$deviceEditorData = $device->getEditorData();
        if (array_key_exists('x', $editorData)) {
            $deviceEditorData->setX($editorData['x']);
        }
        if (array_key_exists('y', $editorData)) {
            $deviceEditorData->setY($editorData['y']);
        }

        $lab = $deviceEditorData->getDevice()->getLabs()[0];
        $lab->setLastUpdated(new \DateTime());

        $entityManager = $this->entityManager;
        $entityManager->persist($deviceEditorData);
        $entityManager->persist($lab);
        $entityManager->flush();

        return new JsonResponse();
    }*/

    
	#[Put('/api/labs/{id<\d+>}/editordata', name: 'api_edit_node_editor_data')]
    public function updateEditorDataActionTest(Request $request, int $id, EditorDataRepository $editorDataRepository)
    {
        $lab = $this->labRepository->findById($id);
        $this->denyAccessUnlessGranted(LabVoter::EDIT_DEVICE, $lab);
 
        $editorDataList = json_decode($request->getContent(), true);

        if (!$editorDataList) {
            throw new BadRequestHttpException("Incorrect JSON.");
        }

        /** @var EditorDataRepository $editorDataRepository */
        $editorDataRepository = $this->managerRegistry->getRepository(EditorData::class);

        $entityManager = $this->entityManager;

        foreach ($editorDataList as $editorData) {
            $deviceEditorData = $editorDataRepository->findByDeviceId($editorData['id']);

            //$deviceEditorData = $device->getEditorData();
            if (array_key_exists('top', $editorData)) {
                $deviceEditorData->setX($editorData['top']);
            }
            if (array_key_exists('left', $editorData)) {
                $deviceEditorData->setY($editorData['left']);
            }

            $entityManager->persist($deviceEditorData);
            
        }

        $lab->setLastUpdated(new \DateTime());       
        
        $entityManager->persist($lab);
        $entityManager->flush();

        $response = new Response();
        $response->setContent(json_encode([
            'code' => 200,
            'status'=> 'success',
            'message' => 'Lab has been saved (60023).']));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    
	#[Delete('/api/devices/{id<\d+>}', name: 'api_delete_device')]
	#[Security("is_granted('ROLE_TEACHER_EDITOR')", message: "Access denied.")]
    #[Route(path: '/admin/devices/{id<\d+>}/delete', name: 'delete_device', methods: 'GET')]
    public function deleteAction(Request $request, int $id)
    {
        $user = $this->get('security.token_storage')->getToken()->getUser();
        $username=$user->getUserIdentifier();
        $device = $this->deviceRepository->find($id);

        $this->delete_device($device);
        $this->logger->info("Device ".$device->getName()." deleted by user ".$username);

        if ('json' === $request->getRequestFormat()) {
            return $this->json();
        }

        return $this->redirectToRoute('devices');
    }

    public function delete_device(Device $device, int $labId = null) {

        if (!$device = $this->deviceRepository->find($device->getId())) {
            throw new NotFoundHttpException();
        }
        if($device->getIsTemplate() == true && count($device->getLabs()) == 0) {
            $fileName = u($device->getName())->camel();
            if (is_file($this->getParameter('kernel.project_dir')."/config/templates/".$device->getId()."-". $fileName . ".yaml")) {
                unlink($this->getParameter('kernel.project_dir')."/config/templates/".$device->getId()."-". $fileName . ".yaml");
            }
        }

        $entityManager = $this->entityManager;

        foreach ($device->getNetworkInterfaces() as $networkInterface) {
            if ($labId != null) {
                foreach($this->networkInterfaceRepository->findByLabAndConnection($labId, $networkInterface->getConnection()) as $otherInterface) {
                    $entityManager->remove($otherInterface);
                }
            }
            $entityManager->remove($networkInterface);
        }

        if ($device->getHypervisor()->getName() === "lxc") {
            $this->logger->info("Delete the device ".$device->getId());
        }
        $entityManager->flush();
        try {
            $entityManager->remove($device);
            $entityManager->flush();        
        $this->addFlash('success', $device->getName() . ' has been deleted.');

        }
        catch (ForeignKeyConstraintViolationException $e) {
            $this->logger->error("ForeignKeyConstraintViolationException".$e->getMessage());
            $this->addFlash('danger', 'This device is still used in some lab. Please delete them first.');

        }
    }

    
	#[Delete('/api/labs/{labId<\d+>}/nodes/{id<\d+>}', name: 'api_delete_device_test')]
    public function deleteActionTest(Request $request, int $id, int $labId)
    {
        if(!$lab = $this->labRepository->find($labId)) {
            throw new NotFoundHttpException("Lab ". $labId. "does not exist.");
        }
        $this->denyAccessUnlessGranted(LabVoter::EDIT_DEVICE, $lab);

        $user = $this->getUser();
        $username = $user ? $user->getUserIdentifier() : 'anonymous';

        if(!$device = $this->deviceRepository->find($id)) {
            throw new NotFoundHttpException("Device ". $id. "does not exist.");
        }
        if(!$lab->getDevices()->contains($device)) {
            throw new BadRequestHttpException("Lab ". $lab->getId()." does not contain device ".$device->getId().".");
        }

        $this->delete_device($device, $labId);
        $this->logger->info("Device ".$device->getName()." deleted by user ".$username);

        $response = new Response();
        $response->setContent(json_encode([
            'code' => 200,
            'status'=> 'success',
            'message' => 'Lab has been saved (60023).']));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    private function addNetworkInterface(Device $device) {
        $i=count($device->getNetworkInterfaces());
        $networkInterface = new NetworkInterface();
        $networkInterface->setName($device->getNetworkInterfaceTemplate().($i+1));
        $networkInterface->setIsTemplate(true);
        $networkSettings = new NetworkSettings();
        $networkSettings->setName($networkInterface->getName()."_set".($i+1));
        $networkInterface->setSettings($networkSettings);
        $device->addNetworkInterface($networkInterface);
    }

    private function removeNetworkInterface(Device $device) {
        $entityManager = $this->entityManager;
        $networkInterface = $device->getNetworkInterfaces()->last();
        $entityManager->remove($networkInterface);
        //$networkInterface->removeNetworkSetting($networkInterface->getSetting());
        $device->removeNetworkInterface($networkInterface);
        $entityManager->persist($device);
        $entityManager->flush();
    }

    /*    /*public function getNetworkInterface(Request $request, int $id)
    {
        $device = $this->deviceRepository->find($id);

        if (!$device) {
            throw new NotFoundHttpException("Device " . $id . " does not exist.");
        }

        if ('json' === $request->getRequestFormat()) {
            $i=count($device->getNetworkInterfaces());
            
        $response=new JsonResponse();
        $response->setData($i);
        return $response;
        }
        
        return new JsonResponse();
        
    }*/

     
	#[Get('/api/labs/{labId<\d+>}/nodes/{deviceId<\d+>}/interfaces', name: 'api_get_device_interfaces')]
    public function getNetworkInterfaces(Request $request, int $labId, int $deviceId)
    {
        if (!$lab = $this->labRepository->find($labId)) {
            throw new NotFoundHttpException("Lab ".$labId. " does not exist.");
        }
        $this->denyAccessUnlessGranted(LabVoter::SEE_DEVICE, $lab);

        if (!$device = $this->deviceRepository->find($deviceId)){
            throw new NotFoundHttpException("Device ".$id. " does not exist.");
        }

        if (!$lab->getDevices()->contains($device)){
            throw new BadRequestHttpException("Lab ".$lab->getId(). "does not contain device ".$device->getId().".");
        }
        $networkInterfaces = $device->getNetworkInterfaces();

        //the device has at least one interface
        if ($networkInterfaces[0] != null){
            $data = [];
            $ethernet = [];
            $i = [];

            //get all network Interfaces of the device
            foreach($networkInterfaces as $networkInterface){
                    if ($device->getNetworkInterfaceTemplate() == "") {
                        preg_match_all('!\d+!', $networkInterface->getName(), $numbers);
                        $netId = $numbers[0][count($numbers[0]) -1];
                        $ethernet[(int)$netId]= [
                            "name"=> $networkInterface->getName(),
                            "network_id"=> $networkInterface->getVlan(),
                        ];
                        array_push($i, (int)$netId);
                    }
                    else {
                        $ethernet[(int)explode($device->getNetworkInterfaceTemplate(), $networkInterface->getName())[1]]= [
                            "name"=> $networkInterface->getName(),
                            "network_id"=> $networkInterface->getVlan(),
                        ];
                        array_push($i, (int)explode($device->getNetworkInterfaceTemplate(), $networkInterface->getName())[1]);
                    }
                    
                
            }

            //sort the array to get the next interface number
            if(sizeof((array)$i) > 1) {
                sort($i);
            }

            //add the missing interfaces
            for ($j = 0; $j < $i[count($i)-1]; $j++) {
                if (!isset($ethernet[$j])) {
                    $ethernet[$j] = [
                        "name"=> "new network interface",
                        "network_id"=> -1,
                    ];
                    break;
                }
            }

            //add an availbable interface
            $newInterfaceExists = false;
            foreach($ethernet as $interface) {
                if ($interface["name"] == "new network interface") {
                    $newInterfaceExists = true;
                    break;
                }
            }
            if ($newInterfaceExists == false) {
                //$interface = ($i[sizeof((array)$i)-1] +1);
                array_push($ethernet, [
                    "name"=> "new network interface",
                    "network_id"=> -1,
                ]);
            }

            $data = [
                "id"=>$networkInterface->getDevice()->getId(),
                "sort"=> $networkInterface->getDevice()->getType(),
                "ethernet"=>$ethernet
            ];
        }
        //the device does not have any interface
        else {
            $data = [
                "id"=>$device->getId(),
                "sort"=> $device->getType(),
                "ethernet"=>[
                    0 => [
                        "name"=> "new network interface",
                        "network_id"=> -1,
                    ],
                ]
            ];
        }
        $response = new Response();
        $response->setContent(json_encode([
            'code'=> 200,
            'status'=>'success',
            'message' => 'Successfully listed network interfaces (60030).',
            'data' => $data]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;

    }

    // Set the hypervisor of a device to the same that de OS choosen
    private function setDeviceHypervisorToOS(Device $device){
        $device->setHypervisor($device->getOperatingSystem()->getHypervisor());
            switch($device->getOperatingSystem()->getHypervisor()->getName()) {
                case 'lxc':
                    $device->setType('container');
                break;
                case 'qemu':
                    $device->setType('vm');
                break;
                case 'natif':
                    $device->setType('switch');
                break;
                case 'physical':
                    $device->setType('physical');
                break;
            }
    }

    public function getConsoleUrl($html5,$username,$device) {

                /*if ($device->getType() == 'docker') {
                                return 'docker://'.$_SERVER['SERVER_NAME'].':4243/'.$this -> lab_id.'-'.$this -> tenant.'-'.$this -> id.'?'.$this -> name;
                        }*/
                //if ( $html5 != 1 ) {
                    switch ( $device->getConsole() ) {
                        default:
                        case 'telnet' :
                            //html5AddSession( $html5_db, $username.'_'.$this -> name , "telnet" , $this -> port, $this -> tenant);
                            return 'telnet://'.$_SERVER['SERVER_ADDR'].':'.$device->getPort();
                            break;;
                        case 'vnc' :
                            //html5AddSession( $html5_db, $username.'_'.$this -> name , "vnc" , $this -> port, $this -> tenant);
                            return 'vnc://'.$_SERVER['SERVER_ADDR'].':'.$device->getPort();
                            break;;
                        case 'rdp' :
                            //html5AddSession( $html5_db, $username.'_'.$this -> name , "rdp" , $this -> port, $this -> tenant);
                            //return 'rdp://full%20address=s:'.$_SERVER['SERVER_NAME'].':'.$this -> port;
                            return '/rdp/?target='.$_SERVER['SERVER_ADDR'].'&port='.$device->getPort();
                            break;;
                    }
                /*} else {
                    if ( !isset($this->console) || $this->console == '' ) {
                        $console='telnet' ;
                    } else {
                        $console=$this->console ;
                    }
                    //$html5_db = html5_checkDatabase();
                    html5AddSession( $html5_db, $this -> name.'_'.$this ->id.'_'.$username , $console , $this -> port, $this -> tenant);
                    $html5_db = null ;
                    addHtml5Perm($this->port,$this->tenant);
                    $token=getHtml5Token($this->tenant);
                    $b64id=base64_encode( $this->port."\0".'c'."\0".'mysql' );
                    //return 'http://'.$_SERVER['SERVER_NAME'].':8080/guacamole/#/client/'.$b64id ;
                    return '/html5/#/client/'.$b64id.'?token='.$token ;
                }*/
            }
}
