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
use App\Entity\Iso;
use App\Form\DeviceType;
use App\Form\EditorDataType;
use App\Form\ControlProtocolTypeType;
use App\Form\IsoType;
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
use App\Repository\IsoRepository;
use App\Repository\FlavorDiskRepository;
use App\Security\ACL\LabVoter;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\Security;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;
use function Symfony\Component\String\u;
use JMS\Serializer\SerializationContext;
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
    private EntityManagerInterface $entityManager;
    private IsoRepository $isoRepository;
    private FlavorDiskRepository $flavorDiskRepository;

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
        EntityManagerInterface $entityManager,
        IsoRepository $isoRepository,
        FlavorDiskRepository $flavorDiskRepository)
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
        $this->isoRepository = $isoRepository;
        $this->flavorDiskRepository = $flavorDiskRepository;
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
    
       $arch=null;
       if (!is_null($device->getOperatingSystem()->getArch())){
            $arch=$device->getOperatingSystem()->getArch()->getName();
        }
        else if (!is_null($device->getIsoArch()))
                $arch=$device->getIsoArch()->getName();
        else $arch="x86_64";
        

        return $this->render('device/view.html.twig', ['device' => $device, 'arch' => $arch]);
    }

    
	#[Post('/api/labs/{labId<\d+>}/nodes/{id<\d+>}', name: 'api_get_node')]
    public function showActionTest(Request $request, int $id, int $labId)
    {
        $lab = $this->labRepository->find($labId);
        $this->denyAccessUnlessGranted(LabVoter::SEE_DEVICE, $lab);

        $device = $this->deviceRepository->find($id);
        $this->logger->debug('[DeviceController:showActionTest]::Device id request '.$id);
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
            "delay"=> $device->getDelay(),
            "left"=> $device->getEditorData()->getY(),
            "top"=> $device->getEditorData()->getX(),
            "icon"=> $device->getIcon(),
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
            "operatingSystem" => $device->getOperatingSystem()->getId(),
            "console" => $controlProtocolTypesName,
            "networkInterfaceTemplate"=>$device->getNetworkInterfaceTemplate(),
            "other_options"=>$device->getOtherOptions(),
            "isos" => $isos,
            "cdrom_bus_type" => $device->getCdromBusType(),
            "bios_type" => $device->getBiosType()
        ];
        if (!is_null($device->getOperatingSystem()->getArch()))
            $data["qemu_arch"]=$device->getOperatingSystem()->getArch()->getId();

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
                $device->setModel($operatingSystem->getRelease());
                $device->setBrand($operatingSystem->getVersion());
                $device->setOperatingSystem($operatingSystem);
            }
        }

        if ("new_physical_device" === $request->get('_route')) {
            $virtuality = false;
        }
        else {
            $virtuality = true;
        }

        $deviceForm = $this->createForm(DeviceType::class, $device, [
            "virtuality" => $virtuality
        ]);
                
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
                $this->logger->debug('[DeviceController:newAction]::'.$proto->getName());
            }
            $device->setAuthor($this->getUser());
            $device->setVirtuality($virtuality);
            $device->setCreatedAt(new DateTime());
            // The type cannot be null but it is update with the function setDeviceHypervisorToOS
            // The update can be work only if the device is created so we add a fake value here
            // to avoid a crash
            // This value will be replaced by the real value after the flush
            $device->setType("vm");

            $entityManager = $this->entityManager;
            $entityManager->persist($device);
            $this->setDeviceHypervisorToOS($device);
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
                
                $isos=[];

                foreach($device->getIsos() as $iso) {
                    $id=$iso->getId();
                    $this->logger->debug("[DeviceController:newAction]::Add iso id ".$id);
                    array_push($isos, $id);
                }
                if ($isos == []) {
                    $isos = '';
                }

                $deviceData = [
                    "name" => $device->getName(),
                    "type" => $device->getType(),
                    "icon" => $device->getIcon(),
                    "operatingSystem" => $device->getOperatingSystem()->getId(),
                    "flavor" => $device->getFlavor()->getId(),
                    "controlProtocol" => $controlProtocolTypes,
                    "hypervisor" => $device->getHypervisor()->getId(),
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
                    "virtuality" => $virtuality,              
                    "other_options"=>$device->getOtherOptions(),
                    "isos" => $isos,
                    "cdrom_bus_type" => $device->getCdromBusType(),
                    "bios_type" => $device->getBiosType()
                ];
                
                if (!is_null($device->getOperatingSystem()->getArch())){
                    $deviceData["qemu_arch"]=$device->getOperatingSystem()->getArch()->getId();
                }
                else {
                    $deviceData["qemu_arch"]=$device->getIsoArch();
                }

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
            'virtuality' => $virtuality,
            'flavor' => $this->getAllFlavors()
        ]);
    }

    private function getAllFlavors() {
        // Récupérer tous les flavors (RAM/CPU)
        $flavors = $this->flavorRepository->findAll();

        // Récupérer tous les Operating Systems qui ont un FlavorDisk associé
        $operatingSystems = $this->operatingSystemRepository->findAll();

        // Préparer les données pour le template
        $flavor_global = [
            'memory' => [],
            'disk' => []
        ];

        // Formater les données des flavors (RAM)
        foreach ($flavors as $flavor) {
            $flavor_global['memory'][] = [
                'id' => $flavor->getId(),
                'name' => $flavor->getName(),
                'memory' => $flavor->getMemory(), // en MB
                'memory_gb' => round($flavor->getMemory() / 1024, 2) // conversion en GB
            ];
        }

        // Trier les flavors par mémoire croissante
        usort($flavor_global['memory'], function($a, $b) {
            return $a['memory'] <=> $b['memory'];
        });

        // Tableau pour éviter les doublons de FlavorDisk
        $addedFlavorDisks = [];

        // Formater les données des Operating Systems qui ont un FlavorDisk
        foreach ($operatingSystems as $os) {
            $flavorDisk = $os->getFlavorDisk();
            
            // Vérifier que l'OS a bien un FlavorDisk et qu'on ne l'a pas déjà ajouté
            if ($flavorDisk !== null && !isset($addedFlavorDisks[$flavorDisk->getId()])) {
                $flavor_global['disk'][] = [
                    'id' => $flavorDisk->getId(),
                    'name' => $flavorDisk->getName(),
                    'disk_gb' => $flavorDisk->getDisk(), // en GB
                    'os_name' => $os->getName(), // Nom de l'OS qui utilise ce FlavorDisk
                    'os_id' => $os->getId()
                ];
                
                // Marquer ce FlavorDisk comme déjà ajouté
                $addedFlavorDisks[$flavorDisk->getId()] = true;
            }
        }

        // Trier les flavor disks par taille croissante
        usort($flavor_global['disk'], function($a, $b) {
            return $a['disk'] <=> $b['disk'];
        });

        return $flavor_global;
    }

    #[Post('/api/labs/{labId<\d+>}/node', name: 'api_new_devices')]
    public function newActionTest(Request $request, int $labId, 
        HyperVisorRepository $hypervisorRepository,
        ControlProtocolTypeRepository $controlProtocolTypeRepository, 
        OperatingSystemRepository $operatingSystemRepository )
    {
        $this->logger->debug("[DeviceController:newActionTest]::add a new device in lab id ".$labId);

        $data = json_decode($request->getContent(), true);
        $this->logger->debug("[DeviceController:newActionTest]::data received for this new device:",$data);

        preg_match('!(\d+)(.*)!', $data["template"], $templateNumber);
        $this->logger->debug("[DeviceController:newActionTest]::templateNumber find in the edit of the device ".$templateNumber[1]);
        $device = $this->deviceRepository->find($templateNumber[1]);
        if (!array_key_exists("virtuality",$data)) {
            $data["virtuality"]=$device->getVirtuality();
        }

        if ($data["virtuality"] === 0) {            
            $sameDevice = $this->deviceRepository->find($templateNumber[1]);
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
        
        $lab = $this->labRepository->findById($labId);
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

                $this->logger->debug("[DeviceController:newActionTest]::virtuality is true and \$data['count']>1");
                $ids[] = $this->addDevice($lab, $data);
            };
        }
        else {
            $this->logger->debug("[DeviceController:newActionTest]::data virtuality is false or count is equal to 0");
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
        $editorData = $device->getEditorData();
        $template=null;
        //$hypervisor = $this->hypervisorRepository->findById($data['hypervisor']);
        //$controlProtocolType = $this->controlProtocolTypeRepository->findById($data['controlProtocol']);
        foreach($data['controlProtocol'] as $controlProtocolTypeId) {
            $controlProtocolType = $this->controlProtocolTypeRepository->findById($controlProtocolTypeId);
            $device->addControlProtocolType($controlProtocolType[0]);
        }

        $this->logger->debug("[DeviceController:addDevice]::data ",$data);
        if (array_key_exists('ISO',$data)) {
            foreach($data['ISO'] as $iso) {
                $iso = $this->isoRepository->findOneById($iso);
                $device->addIso($iso);
           }
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

        /*
        // This is a bug. When we create a lab in sandbox, the device become template only at the export process
        if($lab->getIsTemplate() == true) {
            $device->setIsTemplate(true);
        }
        else {
            $device->setIsTemplate(false);
        }
        */

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
        
        if(isset($data['bios_type'])) {
            $device->setBiosType($data['bios_type']);
        }

        if(isset($data['cdrom_bus_type'])) {
            $device->setCdromBusType($data['cdrom_bus_type']);
        }

        
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
        $entityManager = $this->entityManager;
        if ($device->getTemplate() != null) {
            preg_match('/^(\d+)(.*)$/', $device->getTemplate(), $templateNumber);
                        
            $this->logger->debug("[DeviceController:addDevice]::Device template: ".$device->getTemplate());
            if ($templateNumber != null) {
                $this->logger->debug("[DeviceController:addDevice]::Template number: ".$templateNumber[1]);
                
                $template = $this->deviceRepository->find($templateNumber[1]);

                $device->setIp($template->getIp());
                $device->setPort($template->getPort());

                // To track when this template is used
                $template->addLabUsingTemplate($lab);
                
                $entityManager->persist($template);                
            }
        } else {
            $this->logger->debug("[DeviceController:addDevice]::Device has no template");
        }
        
        $this->setDeviceHypervisorToOS($device);
        $lab->addDevice($device);

        $entityManager->persist($device);
        $entityManager->flush();

        $this->logger->debug("[DeviceController:addDevice]::count lab for the template device ".$template->getName()." is ".$template->getLabsUsingThisTemplate()->count());

        $this->logger->info("Device named '" . $device->getName() . "' created and add to lab ");
        $this->logger->debug("[DeviceController:addDevice]::Device named '" . $device->getName() . "' with id ".$device->getId()." created");

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

        $oldName = $device->getName();
        $virtuality = $device->getVirtuality();
        $this->logger->info("Device ".$device->getName()." modification asked by user ".$this->getUser()->getFirstname()." ".$this->getUser()->getName());
    
        $deviceForm = $this->createForm(DeviceType::class, $device, [
            'nb_network_interface' => count($device->getNetworkInterfaces()),
            'virtuality' => $virtuality
        ]);
        
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
                    $this->logger->debug("[DeviceController:updateAction]::ControlProtocolType : ",$controlProtoType);
                    //array_push($device_json['controlProtocolTypes'],$this->controlProtocolTypeRepository->find($controlProtoType['id']));
                    array_push($device_json['controlProtocolTypes'],(string)$controlProtoType['id']);
                    $this->logger->debug("[DeviceController:updateAction]::controlProtocolTypes  : ",$device_json['controlProtocolTypes']);
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

            $this->setDeviceHypervisorToOS($device);

            $controlProtocolTypes= [];
            foreach($device->getControlProtocolTypes() as $controlProtocolType) {
                array_push($controlProtocolTypes, $controlProtocolType->getId());
            }
            if ($controlProtocolTypes == []) {
                $controlProtocolTypes = '';
            }

            //$this->logger->debug("[DeviceContronller:updateAction]::getIso of the device ".$device->getName()." is ".$device->getIsoArch()->getName());

            $entityManager = $this->entityManager;
            $entityManager->persist($device);
            $entityManager->flush();
            
            $this->update_yaml($device,$oldName);

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
            'virtuality' => $virtuality,
            'flavor' => $this->getAllFlavors()
        ]);
    }

    /**
     * Vérifie la cohérence entre les devices templates en base de données 
     * et les fichiers YAML dans le répertoire config/templates/
     * 
     * @return array Rapport détaillé avec les anomalies détectées
     */
    #[Get('/api/devices/check-templates', name: 'api_check_device_templates')]
    #[Security("is_granted('ROLE_TEACHER_EDITOR')", message: "Access denied.")]
    public function checkTemplatesConsistency(Request $request): JsonResponse
    {
        $templatesDir = $this->getParameter('kernel.project_dir') . "/config/templates/";
        $report = [
            'status' => 'success',
            'total_templates_in_db' => 0,
            'total_yaml_files' => 0,
            'missing_yaml_files' => [],
            'orphaned_yaml_files' => [],
            'valid_templates' => []
        ];

        // Récupérer tous les devices qui sont des templates
        $templateDevices = $this->deviceRepository->findBy(['isTemplate' => true]);
        $report['total_templates_in_db'] = count($templateDevices);

        // Vérifier l'existence des fichiers YAML pour chaque template
        $expectedFiles = [];
        foreach ($templateDevices as $device) {
            $fileName = u($device->getName())->camel();
            $expectedFile = $device->getId() . "-" . $fileName . ".yaml";
            $fullPath = $templatesDir . $expectedFile;
            
            $expectedFiles[] = $expectedFile;
            
            if (!file_exists($fullPath)) {
                $report['missing_yaml_files'][] = [
                    'device_id' => $device->getId(),
                    'device_name' => $device->getName(),
                    'expected_file' => $expectedFile,
                    'expected_path' => $fullPath
                ];
                $report['status'] = 'warning';
            } else {
                $report['valid_templates'][] = [
                    'device_id' => $device->getId(),
                    'device_name' => $device->getName(),
                    'yaml_file' => $expectedFile
                ];
            }
        }

        // Vérifier les fichiers YAML orphelins (qui n'ont pas de device correspondant)
        if (is_dir($templatesDir)) {
            $yamlFiles = glob($templatesDir . "*.yaml");
            $report['total_yaml_files'] = count($yamlFiles);
            
            foreach ($yamlFiles as $yamlFile) {
                $fileName = basename($yamlFile);
                
                if (!in_array($fileName, $expectedFiles)) {
                    // Extraire l'ID du device depuis le nom du fichier
                    preg_match('/^(\d+)-/', $fileName, $matches);
                    $deviceId = $matches[1] ?? null;
                    
                    $report['orphaned_yaml_files'][] = [
                        'file_name' => $fileName,
                        'full_path' => $yamlFile,
                        'extracted_device_id' => $deviceId,
                        'device_exists' => $deviceId ? ($this->deviceRepository->find($deviceId) !== null) : false
                    ];
                    $report['status'] = 'warning';
                }
            }
        } else {
            $report['status'] = 'error';
            $report['error'] = "Le répertoire templates n'existe pas: " . $templatesDir;
        }

        // Résumé
        $report['summary'] = [
            'templates_ok' => count($report['valid_templates']),
            'missing_files' => count($report['missing_yaml_files']),
            'orphaned_files' => count($report['orphaned_yaml_files'])
        ];

        // Déterminer le statut final
        if (count($report['missing_yaml_files']) > 0 || count($report['orphaned_yaml_files']) > 0) {
            $report['status'] = 'warning';
        }
        if (!empty($report['error'])) {
            $report['status'] = 'error';
        }

        $this->logger->info("Template consistency check completed", [
            'status' => $report['status'],
            'templates_in_db' => $report['total_templates_in_db'],
            'yaml_files' => $report['total_yaml_files'],
            'missing' => count($report['missing_yaml_files']),
            'orphaned' => count($report['orphaned_yaml_files'])
        ]);

        if ('json' === $request->getRequestFormat()) {
            return $this->json($report);
        }

        /*
        return $this->render('device/template_check.html.twig', [
            'report' => $report
        ]);
        */
    }

    /**
     * Régénère les fichiers YAML manquants pour les devices templates
     * 
     * @return JsonResponse
     */
    #[Post('/api/devices/regenerate-missing-templates', name: 'api_regenerate_missing_templates')]
    #[Security("is_granted('ROLE_TEACHER_EDITOR')", message: "Access denied.")]
    public function regenerateMissingTemplates(Request $request): JsonResponse
    {

        $this->logger->debug("[DeviceController:regenerateMissingTemplates]::Check template YAML regenerated");

        $templatesDir = $this->getParameter('kernel.project_dir') . "/config/templates/";
        $regenerated = [];
        $errors = [];

        // Récupérer tous les devices qui sont des templates
        $templateDevices = $this->deviceRepository->findBy(['isTemplate' => true]);

        foreach ($templateDevices as $device) {
            $fileName = u($device->getName())->camel();
            $fullPath = $templatesDir . $device->getId() . "-" . $fileName . ".yaml";
            
            if (!file_exists($fullPath)) {
                try {
                    $this->update_yaml($device);
                    
                    $regenerated[] = [
                        'device_id' => $device->getId(),
                        'device_name' => $device->getName(),
                        'file_path' => $fullPath
                    ];
                    
                    $this->logger->info("Template YAML regenerated for device " . $device->getName());
                    $this->logger->debug("[DeviceController:regenerateMissingTemplates]::Template YAML regenerated for device " . $device->getName()." with id ".$device->getId());
                } catch (\Exception $e) {
                    $deviceName = $device->getName();
                    $errors[] = [
                        'device_id' => $device->getId(),
                        'device_name' => $deviceName,
                        'error' => $e->getMessage()
                    ];

                    $this->logger->error("Failed to regenerate template YAML for device " . $deviceName, [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return $this->json([
            'status' => empty($errors) ? 'success' : 'partial',
            'regenerated_count' => count($regenerated),
            'error_count' => count($errors),
            'regenerated' => $regenerated,
            'errors' => $errors
        ]);
    }
   
    #[GET('/api/device/yaml/{id<\d+>}', name: 'api_yaml_device')]
    #[Security("is_granted('ROLE_TEACHER_EDITOR')", message: "Access denied.")]
    private function update_yaml($device,$oldName=null){
        $device_name=$device->getName();
        $this->logger->debug("[DeviceController:update_yaml]::Update_yaml for device ".$device_name);

        $controlProtocolTypes= [];
        foreach($device->getControlProtocolTypes() as $controlProtocolType) {
            array_push($controlProtocolTypes, $controlProtocolType->getId());
        }
        if ($controlProtocolTypes == []) {
            $controlProtocolTypes = '';
        }

        $isos=[];
        foreach($device->getIsos() as $iso) {
            $id=$iso->getId();
            $this->logger->debug("[DeviceController:update_yaml]::Add iso id ".$id);
            array_push($isos, $id);
        }
        if ($isos == []) {
            $isos = '';
        }

        $deviceData = [
                "name" => $device_name,
                "type" => $device->getType(),
                "icon" => $device->getIcon(),
                "operatingSystem" => $device->getOperatingSystem()->getId(),
                "flavor" => $device->getFlavor()->getId(),
                "controlProtocol" => $controlProtocolTypes,
                "hypervisor" => $device->getHypervisor()->getId(),
                "brand" => $device->getBrand(),
                "model" => $device->getModel(),
                "bios_type" => $device->getBiosType(),
                "description" => $device->getName(),
                "networkInterfaceTemplate"=>$device->getNetworkInterfaceTemplate(),
                "cpu" => $device->getNbCpu(),
                "core" => $device->getNbCore(),
                "socket" => $device->getNbSocket(),
                "thread" => $device->getNbSocket(),
                "context" => "remotelabz",
                "config_script" => "embedded",
                "ethernet" => 1,
                "virtuality"=> $device->getVirtuality(),
                "other_options" => $device->getOtherOptions(),
                "cdrom_bus_type" => $device->getCdromBusType(),
                "isos" => $isos
        ];
        
        if (!is_null($device->getOperatingSystem()->getArch())){
            $deviceData["qemu_arch"]=$device->getOperatingSystem()->getArch()->getId();
        }
        else {
            if (!is_null($device->getIsoArch()))
                $deviceData["qemu_arch"]=$device->getIsoArch()->getName();
            else $deviceData["qemu_arch"]="x86_64";
        }

        $yamlContent = Yaml::dump($deviceData);
        $fileName = u($device->getName())->camel();
        $oldFileName = u($oldName)->camel();

        $isTemplate = $device->getIsTemplate();
        //$this->logger->debug("[DeviceController:update_yaml]::Variable isTemplate ".$isTemplate);
        if ($isTemplate == 1) {
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
        else {
            if (is_file($this->getParameter('kernel.project_dir')."/config/templates/".$device->getId()."-". $oldName . ".yaml")) {
                unlink($this->getParameter('kernel.project_dir')."/config/templates/".$device->getId()."-". $oldName . ".yaml");
            }
        }
    }
    
	#[Put('/api/labs/{labId<\d+>}/node/{id<\d+>}', name: 'api_edit_node')]
    public function updateActionTest(Request $request, int $id, int $labId)
    {
        $user = $this->getUser();
        $this->logger->debug("[DeviceController:updateActionTest]::update the devive ".$id." in lab ".$labId);
        $entityManager = $this->entityManager;

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
        $this->logger->debug("[DeviceController:updateActionTest]::data received :",$data);

        if(isset($data['count'])) {
            $device->setCount($data['count']);
        }

        if(isset($data['bios_type'])) {
            $device->setBiosType($data['bios_type']);
        }

        if(isset($data['cdrom_bus_type'])) {
            $device->setCdromBusType($data['cdrom_bus_type']);
        }

        if(isset($data['other_options'])) {
            $device->setOtherOptions($data['other_options']);
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
            // Récupérer les IDs des protocoles actuellement associés au device
            $currentProtocolIds = [];
            foreach ($device->getControlProtocolTypes() as $proto) {
                $currentProtocolIds[] = $proto->getId();
            }
            
            // Récupérer les IDs des protocoles demandés
            $requestedProtocolIds = array_map('intval', $data['controlProtocol']);
            
            // Déterminer les protocoles à supprimer (présents actuellement mais pas dans la requête)
            $protocolsToRemove = array_diff($currentProtocolIds, $requestedProtocolIds);
            
            // Déterminer les protocoles à ajouter (présents dans la requête mais pas actuellement)
            $protocolsToAdd = array_diff($requestedProtocolIds, $currentProtocolIds);
            
            // Supprimer les protocoles qui ne sont plus nécessaires
            if (!empty($protocolsToRemove)) {
                foreach ($device->getControlProtocolTypes() as $proto) {
                    if (in_array($proto->getId(), $protocolsToRemove)) {
                        $proto->removeDevice($device);
                        $device->removeControlProtocolType($proto);
                        $this->logger->debug("[DeviceController:updateActionTest]::Removed control protocol: " . $proto->getName() . " from device " . $device->getName());
                    }
                }
            }
            
            // Ajouter les nouveaux protocoles
            if (!empty($protocolsToAdd)) {
                foreach ($protocolsToAdd as $protocolId) {
                    $controlProtocolType = $this->controlProtocolTypeRepository->findById($protocolId);
                    if ($controlProtocolType && isset($controlProtocolType[0])) {
                        $device->addControlProtocolType($controlProtocolType[0]);
                        $this->logger->debug("Added control protocol: " . $controlProtocolType[0]->getName() . " to device " . $device->getName());
                    }
                }
            }
            
            // Un seul persist et flush à la fin
            $entityManager->persist($device);
            $entityManager->flush();
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

        
        $template=$device->getTemplate();
        preg_match('!(\d+)(.*)!',$template , $templateNumber);
        if (!is_null($template)) {
            preg_match('!(\d+)(.*)!', $device->getTemplate(), $templateNumber);
            $template = $this->deviceRepository->find($templateNumber[1]);
            $ip=$template->getIp();
            $port=$template->getPort();
            $device->setIp($ip);
            $device->setPort($port);
        }

        $entityManager = $this->entityManager;
        $entityManager->persist($device);
        $entityManager->flush();

        $this->logger->info("Device named " . $device->getName() . " modified by ".$user->getName());

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
    #[Route(path: '/admin/devices/{id<\d+>}/delete', name: 'delete_device', methods: 'DELETE')]
    public function deleteAction(Request $request, int $id)
    {
        $user = $this->getUser();
        $username = $user ? $user->getUserIdentifier() : 'anonymous';
        $device = $this->deviceRepository->find($id);
        if (!is_null($device)) {
            $this->delete_device($device);
            $this->logger->info("Device ".$device->getName()." deleted by user ".$username);

            if ('json' === $request->getRequestFormat()) {
                return $this->json();
            }
        }
        return $this->redirectToRoute('devices');
    }

    private function delete_device(Device $device, int $labId = null) {

        if (!$device = $this->deviceRepository->find($device->getId())) {
            throw new NotFoundHttpException();
        }
        $entityManager = $this->entityManager;
        $this->logger->debug("[DeviceController:delete_device]::Number of labs with this device: ".$device->getLabsUsingThisTemplate()->count());

        if ($device->getLabsUsingThisTemplate()->isEmpty()) {
            if ($device->getIsTemplate()) {
                $this->logger->debug("[DeviceController:delete_device]::This device template will be remove from all labs");

                foreach ($device->getLabsUsingThisTemplate() as $lab) {
                    $device->removeLabUsingTemplate($lab);
                }
            
                $fileName = u($device->getName())->camel();
                $yaml_file=$this->getParameter('kernel.project_dir')."/config/templates/".$device->getId()."-". $fileName . ".yaml";
                if (is_file($yaml_file)) {
                    unlink($yaml_file);
                }
                $entityManager->persist($device);
            }    

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
                $template=$device->getTemplate();
                if (!is_null($template)) {
                    preg_match('!(\d+)(.*)!', $device->getTemplate(), $templateNumber);
                    $template = $this->deviceRepository->find($templateNumber[1]);
                    if (!is_null($labId)) {
                        $lab = $this->labRepository->find($labId);
                        $this->logger->debug("[DeviceController:delete_device]::Delete from the template of device ".$templateNumber[1]." this lab usage");
                        if (!is_null($template)) {
                            $template->removeLabUsingTemplate($lab);
                            $entityManager->persist($template);
                        }
                    }
                }
                $this->logger->debug("[DeviceController:delete_device]::The device will be remove");

                $entityManager->remove($device);
                $entityManager->flush();        
                $this->addFlash('success', $device->getName() . ' has been deleted.');
            }
            catch (ForeignKeyConstraintViolationException $e) {
                $this->logger->error("ForeignKeyConstraintViolationException".$e->getMessage());
                $this->addFlash('danger', 'This device is still used in some lab. Please delete them first.');
            }
        } else
            $this->addFlash('danger', 'This device is still used by other device in some lab. Please delete them first.');
    }

    
	#[Delete('/api/labs/{labId<\d+>}/nodes/{id<\d+>}', name: 'api_delete_device_test')]
  	#[Security("is_granted('ROLE_TEACHER_EDITOR')", message: "Access denied.")]
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
