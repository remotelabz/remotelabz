<?php

namespace App\Controller;

use Exception;
use Psr\Log\LoggerInterface;
use App\Entity\DeviceInstanceLog;
use App\Entity\InstancierInterface;
use App\Entity\InvitationCode;

use App\Repository\UserRepository;
use App\Repository\GroupRepository;
use App\Repository\LabInstanceRepository;
use App\Repository\DeviceInstanceLogRepository;
use App\Repository\LabRepository;
use App\Repository\NetworkInterfaceInstanceRepository;
use App\Repository\DeviceInstanceRepository;
use App\Repository\DeviceRepository;
use App\Repository\InvitationCodeRepository;
use App\Repository\ConfigWorkerRepository;

use App\Service\Proxy\ProxyManager;
use App\Service\Instance\InstanceManager;

use App\Form\InstanceType;

use App\EventSubscriber\AddFilterInstanceSubscriber;

use App\Security\ACL\LabVoter;
use App\Security\ACL\InstanceVoter;

use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

use Symfony\Component\Security\Http\Attribute\Security;

use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;

use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Doctrine\ORM\EntityManagerInterface;

class InstanceController extends Controller
{
    private $logger;
    protected $proxyManager;
    private $labInstanceRepository;
    private $deviceInstanceRepository;
    private $networkInterfaceInstanceRepository;
    private $groupRepository;
    private $userRepository;
    private $serializer;
    protected $remotelabzProxyUseWss;
    private $configworkerRepository;

    
    /** @var LabRepository $labRepository */
    private $labRepository;

    public function __construct(
        LoggerInterface $logger,
        ProxyManager $proxyManager,
        LabInstanceRepository $labInstanceRepository,
        DeviceInstanceRepository $deviceInstanceRepository,
        LabRepository $labRepository,
        NetworkInterfaceInstanceRepository $networkInterfaceInstanceRepository,
        GroupRepository $groupRepository,
        UserRepository $userRepository,
        SerializerInterface $serializerInterface,
        bool $remotelabzProxyUseWss,
        ConfigWorkerRepository $configworkerRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->logger = $logger;
        $this->labInstanceRepository = $labInstanceRepository;
        $this->labRepository = $labRepository;
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->networkInterfaceInstanceRepository = $networkInterfaceInstanceRepository;
        $this->groupRepository = $groupRepository;
        $this->userRepository = $userRepository;
        $this->proxyManager = $proxyManager;
        $this->serializer = $serializerInterface;
        $this->remotelabzProxyUseWss = $remotelabzProxyUseWss;
        $this->configworkerRepository = $configworkerRepository;
        $this->entityManager = $entityManager;
    }

    
	#[Get('/api/instances', name: 'api_get_instances')]
	#[Security("is_granted('ROLE_USER')", message: "Access denied.")]
    #[Route(path: '/instances', name: 'instances')]
    public function indexAction(
        Request $request,
        SerializerInterface $serializer,
        UserRepository $userRepository)
    {
        if ($request->query->has('uuid')) {
            return $this->redirectToRoute('api_get_instance_by_uuid', ['uuid' => $request->query->get('uuid')]);
        }
        $user=$this->getUser();

        $search = $request->query->get('search', '');
        #$instance = $request->query->get('instance');
        $instance = $request->query->all('instance');

        $filter = $instance ? $instance['filter'] : "none";
        $subFilter = $instance ? $instance['subFilter'] : "allInstances";
        $page = (int)$request->query->get('page', 1);
        $limit = 10;

        if ($user->getHighestRole() != "ROLE_USER") {
            $addFilterForm = $this->createForm(InstanceType::class, ["action"=> "/instances", "method"=>"GET", "filter"=>$filter, "subFilter" => $subFilter]);
            $addFilterForm->handleRequest($request);
        }
        else {
            $addFilterForm = null;
        }

        if($subFilter == "allInstances") {
            if ($user->isAdministrator()) {
                $instances=$this->labInstanceRepository->findAll();
            }
            else {
                //$AllLabInstances=$this->labInstanceRepository->findByUserAndGroups($user);
                $instances=$this->labInstanceRepository->findByUserAndAllMembersGroups($user);
            }
        }
        else {
            if ($user->getHighestRole() == "ROLE_USER") {
                $filter = "none";
                $subFilter = "allInstances";
                $instances=$this->labInstanceRepository->findByUserAndAllMembersGroups($user);
            }
            else {
                $instances = $this->getLabInstances($filter, $subFilter);
            }
        }

        $AllLabInstances = [];
        foreach ($instances as $instance) {
            
            array_push($AllLabInstances, $instance);
        }

        $count = count($AllLabInstances);
        try {
            $AllLabInstances = array_slice($AllLabInstances, $page * $limit - $limit, $limit);
        } catch (ORMException $e) {
            throw new NotFoundHttpException('Incorrect order field or sort direction', $e, $e->getCode());
        }

        if ('json' === $request->getRequestFormat()) {            
            return $this->json($AllLabInstances, 200, [], ['api_get_lab_instance']);
        }

        $labInstances=[];
        
        foreach ( $AllLabInstances as $instance){
            $instanceManagerProps = [
                'labInstance' => $instance,
            ];

            $tmp_json=$serializer->serialize(
                $instanceManagerProps,
                'json',
                SerializationContext::create()->setGroups(['api_get_lab_instance'])
            );

            array_push($labInstances, [
                'instance' => $instance,
                'props' => $tmp_json
            ]);
        }
        
        $instanceManagerProps = [
            'labInstances' =>$AllLabInstances,
            'user'=> $this->getUser()
        ];
        $props=$serializer->serialize(
            $instanceManagerProps,
            'json',
            SerializationContext::create()->setGroups(['api_get_lab_instance'])
        );
        return $this->render('instance/index.html.twig', [
            'labInstances' => $labInstances,
            'props'=> $props,
            'addFilterForm' => $addFilterForm == null ? "": $addFilterForm->createView(),
            'count'=> $count,
            'page' => $page,
            'limit' => $limit,
            'filter' => $filter,
            'subFilter' => $subFilter,
        ]);
    }

    private function getLabInstances($filter, $subFilter) {
        if ($subFilter == "allGroups") {
            $instances = $this->fetchLabInstancesByGroup();   
        }
        else if ($filter == "group" && $subFilter != "allGroups") {
            $instances = $this->fetchLabInstancesByGroupUuid($subFilter);
        }
        else if ($subFilter == "allLabs" || $subFilter == "allWorkers") {
            $instances = $this->fetchLabInstancesOrderedByLab();
        }
        else if ($filter == "lab" && $subFilter != "allLabs") {
            $instances = $this->fetchLabInstancesByLabUuid($subFilter);
        }
        else if ($subFilter == "allTeachers" || $subFilter == "allStudents" || $subFilter == "allEditors"|| $subFilter == "allAdmins" ) {
            $userType = "";
            if($subFilter == "allTeachers") {
                $userType = "teachers";
            }
            else if ($subFilter == "allEditors") {
                $userType = "editors";
            }
            else if ($subFilter == "allStudents") {
                $userType = "students";
            }
            else if ($subFilter == "allAdmins") {
                $userType = "admins";
            }

            $instances = $this->fetchLabInstancesOwnedByUserType($userType);
        }
        else if (($filter == "teacher" && $subFilter != "allTeachers") || ($filter == "student" && $subFilter != "allStudents") || ($filter == "admin" && $subFilter != "allAdmins") || ($filter == "editor" && $subFilter != "allEditors")) {
            $instances = $this->fetchLabInstancesByUserUuid($subFilter);
        }
        else if ($filter == "worker" && $subFilter != "allWorkers")
            $instances = $this->fetchLabInstancesByWorker($subFilter);

        return $instances;
    }

    
	#[Get('/api/filter', name: 'api_list_instances_filter')]
	#[Security("is_granted('ROLE_TEACHER') or is_granted('ROLE_ADMINISTRATOR')", message: "Access denied.")]
    public function listInstancesFilterAction(
        Request $request)
    {
        $filter = $request->query->get("filter");
        $user=$this->getUser();
        $subFilter = [];

        if ($filter == "group") {
            array_push($subFilter, [
                "uuid" => "allGroups",
                "name" => "All groups"
            ]);
            if ($user->isAdministrator()) {
                $groups = $this->groupRepository->findAll();
            }
            else {
                $groups = $user->getGroupsInfo();
            }

            foreach ($groups as $group) {
                array_push($subFilter, [
                    "uuid" => $group->getUuid(),
                    "name" => $group->getName()
                ]);
            }
        }
        else if ($filter == "lab") {
            array_push($subFilter, [
                "uuid" => "allLabs",
                "name" => "All labs"
            ]);
            if ($user->isAdministrator()) {
                $labs = $this->labRepository->findBy(["isTemplate"=>false]);
            }
            else if ($user->hasRole("ROLE_TEACHER") || $user->hasRole("ROLE_TEACHER_EDITOR")){
                $labs = $this->labRepository->findByAuthorAndGroups($user);
            }

            foreach ($labs as $lab) {
                array_push($subFilter, [
                    "uuid" => $lab->getUuid(),
                    "name" => $lab->getName()
                ]);
            }
        }
        else if ($filter == "student" || $filter == "teacher" || $filter == "editor" || $filter == "admin") {
            if ($user->isAdministrator()) {
                if ($filter == "admin") {
                    $role = "%ADMIN%";
                    array_push($subFilter, [
                        "uuid" => "allAdmins",
                        "name" => "All administrators"
                    ]);
                }
                else if ($filter == "editor") {
                    $role = "%EDITOR%";
                    array_push($subFilter, [
                        "uuid" => "allEditors",
                        "name" => "All editors"
                    ]);
                }
                else if ($filter == "teacher") {
                    $role = "%TEACHER__";
                    array_push($subFilter, [
                        "uuid" => "allTeachers",
                        "name" => "All teachers"
                    ]);
                }
                else {
                    $role = "%USER%";
                    array_push($subFilter, [
                        "uuid" => "allStudents",
                        "name" => "All students"
                    ]);
                }

                $users = $this->userRepository->findByRole($role);

            }
            else {
                if ($filter == "teacher") {
                    $role = "teachers";
                    array_push($subFilter, [
                        "uuid" => "allTeachers",
                        "name" => "All teachers"
                    ]);
                }
                else if ($filter == "editor") {
                    $role = "editors";
                    array_push($subFilter, [
                        "uuid" => "allEditors",
                        "name" => "All editors"
                    ]);
                }
                else {
                    $role = "students";
                    array_push($subFilter, [
                        "uuid" => "allStudents",
                        "name" => "All students"
                    ]);
                }
                $users = $this->userRepository->findUserTypesByGroups($role, $user);
            }

            usort($users, function ($a,$b) {return strcmp($a->getLastName(), $b->getLastName());});
            foreach ($users as $user) {
                array_push($subFilter, [
                    "uuid" => $user->getUuid(),
                    "name" => $user->getName()
                ]);
            }
           
        }
        else if ($filter == "worker") {
            array_push($subFilter, [
                "uuid" => "allworkers",
                "name" => "All workers"
            ]);
            $workers = $this->configworkerRepository->findAll();
            foreach($workers as $worker ){
                array_push($subFilter, [
                    "uuid" => $worker->getIPv4(),
                    "name" => $worker->getIPv4()
                ]);
            }
            
        }
        else if ($filter == "none") {
            array_push($subFilter, [
                "uuid" => "allInstances",
                "name" => "All instances"
            ]);
        }
        return new JsonResponse($subFilter);
    }

    
	#[Post('/api/instances/create', name: 'api_create_instance')]
    public function createAction(Request $request, InstanceManager $instanceManager, UserRepository $userRepository, InvitationCodeRepository $invitationCodeRepository, GroupRepository $groupRepository, LabRepository $labRepository)
    {
        #$labUuid = $request->request->get('lab');
        $labUuid = $request->request->all()['lab'] ?? [];
        #$instancierUuid = $request->request->get('instancier');
        $instancierUuid = $request->request->all()['instancier'] ?? [];
        #$instancierType = $request->request->get('instancierType');
        $instancierType = $request->request->all()['instancierType'] ?? [];


        switch ($instancierType) {
            case 'user':
                $repository = $userRepository;
                break;
            case 'guest':
                $repository = $invitationCodeRepository;
                break;
            case 'group':
                $repository = $groupRepository;
                break;
            default:
                throw new BadRequestHttpException('Instancier type must be one of "user" or "group".');
        }

        /** @var InstancierInterface $instancier */
        $instancier = $repository->findOneBy(['uuid' => $instancierUuid]);
        $lab = $labRepository->findOneBy(['uuid' => $labUuid]);
        $this->denyAccessUnlessGranted(LabVoter::SEE, $lab);
        
        /*foreach ($request->headers as $key => $part) {
            $this->logger->debug("Key: ".$key);
        }*/
        try {
            $username=$this->getUser()->getName();
            $this->logger->debug("Lab instance creation: ".$lab->getName());
            if ($instancierType == "guest") {
                $this->logger->info("The guest".$this->getUser()->getMail()." ".$this->getUser()->getUuid()." enter in lab ".$lab->getName()." ".$lab->getUuid());
            }
            else {
                $this->logger->info($this->getUser()->getFirstname()." ".$username." ".$this->getUser()->getUuid()." enter in lab ".$lab->getName()." ".$lab->getUuid());
            }

            $instance = $instanceManager->create($lab, $instancier);                        
            if (!is_null($instance)) {
                switch($instancierType) {
                    case "guest":
                        $this->logger->info("Lab instance ".$instance->getUuid()." created by guest ".$this->getUser()->getMail()." ".$this->getUser()->getUuid()." Wait ack created message");
                        $this->logger->info("Lab instance ".$instance->getUuid()." executed on Worker ".$instance->getWorkerIp());
                        break;
                    case "user":
                        $this->logger->info("Lab instance ".$instance->getUuid()." created by user ".$this->getUser()->getFirstname()." ".$username." ".$this->getUser()->getUuid()." Wait ack created message");
                        $this->logger->info("Lab instance ".$instance->getUuid()." executed on Worker ".$instance->getWorkerIp());
                        break;
                    case "group":
                        $this->logger->info("Lab instance ".$instance->getUuid()." created by group ".$instancier->getName()." Wait ack created message");
                        $this->logger->info("Lab instance ".$instance->getUuid()." executed on Worker ".$instance->getWorkerIp());
                        break;
                }
            }
            else
                $this->logger->info("User ".$username." has already an instance of the lab ".$lab->getName());

        } catch (Exception $e) {
            throw $e;
        }

        return $this->json($instance, 200, [], ['api_get_lab_instance']);
    }

    
	#[Get('/api/instances/start/by-uuid/{uuid}', name: 'api_start_instance_by_uuid', requirements: ["uuid"=>"[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"])]
    public function startByUuidAction(Request $request, string $uuid, InstanceManager $instanceManager)
    {
        if (!$deviceInstance = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid])) {
            throw new NotFoundHttpException('No instance with UUID ' . $uuid . ".");
        }
        $this->denyAccessUnlessGranted(InstanceVoter::START_DEVICE, $deviceInstance);
        
        $json = $instanceManager->start($deviceInstance);
        $status = empty($json) ? 204 : 200;

        return $this->json($json, $status, [], [], true);
    }

    
	#[Post('/api/labs/{labId<\d+>}/nodes/{deviceId<\d+>}/start', name: 'api_start_instance_by_id')]
    public function startByIdAction(Request $request, int $labId, int $deviceId, InstanceManager $instanceManager, LabRepository $labRepository, DeviceRepository $deviceRepository)
    {
        $lab = $labRepository->find($labId);
        $device = $deviceRepository->find($deviceId);
        $data = json_decode($request->getContent(), true);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');

        if($data['edition'] == 0 && $data['labInstance'] != null) {
            $labInstance = $this->labInstanceRepository->find($data['labInstance']);
            if (!$deviceInstance = $this->deviceInstanceRepository->findByDeviceAndLabInstance($device, $labInstance)) {
                $response->setContent(json_encode([
                    'code'=> 404,
                    'status'=>'Not Found',
                    'message' => 'Device Instance is not found']));
                    return $response;
            }
        }
        
        if($data['edition'] == 0 && $data['labInstance'] == null) {
            $response->setContent(json_encode([
                'code'=> 400,
                'status'=>'fail',
                'message' => 'Lab Instance is null']));
                return $response;
        }
        if($data['edition'] == 1) {
            $response->setContent(json_encode([
                'code '=> 400,
                'status'=>'fail',
                'message' => 'You can not start device in edit mode.']));
                return $response;
        }

        $this->denyAccessUnlessGranted(InstanceVoter::START_DEVICE, $deviceInstance);
        $entityManager = $this->entityManager;
        //var_dump($deviceInstance->getDevice()); exit;
        $json = $instanceManager->start($deviceInstance);
        $status = empty($json) ? 204 : 200;
        //$device->setStatus(2);
        $entityManager->flush();

        //return $this->json($json, $status, [], [], true);

        $response = new Response();
        $response->setContent(json_encode([
            'code'=> $status,
            'status'=>'success',
            'message' => 'Node started (80049).']));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    
	#[Get('/api/instances/stop/by-uuid/{uuid}', name: 'api_stop_instance_by_uuid', requirements: ["uuid"=>"[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"])]
    public function stopByUuidAction(Request $request, string $uuid, InstanceManager $instanceManager)
    {
        if (!$deviceInstance = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid])) {
            throw new NotFoundHttpException('No instance with UUID ' . $uuid . ".");
        }
        if ($_SERVER['REMOTE_ADDR'] != "127.0.0.1") {
            $this->denyAccessUnlessGranted(InstanceVoter::STOP_DEVICE, $deviceInstance);
        }
        
        $instanceManager->stop($deviceInstance);

        return $this->json();
    }

    
	#[Post('/api/labs/{labId<\d+>}/nodes/{deviceId<\d+>}/stop', name: 'api_stop_instance_by_id')]
    public function stopByIdAction(Request $request, int $labId, int $deviceId, InstanceManager $instanceManager, LabRepository $labRepository, DeviceRepository $deviceRepository)
    {
        $lab = $labRepository->find($labId);
        $device = $deviceRepository->find($deviceId);
        $data = json_decode($request->getContent(), true);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');

        if($data['edition'] == 0 && $data['labInstance'] != null) {
            $labInstance = $this->labInstanceRepository->find($data['labInstance']);
            if (!$deviceInstance = $this->deviceInstanceRepository->findByDeviceAndLabInstance($device, $labInstance)) {
                $response->setContent(json_encode([
                    'code'=> 404,
                    'status'=>'Not Found',
                    'message' => 'Device Instance is not found']));
                    return $response;
            }
        }
        if($data['edition'] == 0 && $data['labInstance'] == null) {
            $response->setContent(json_encode([
                'code'=> 400,
                'status'=>'fail',
                'message' => 'Lab Instance is null']));
                return $response;
        }
        if($data['edition'] == 1) {
            $response->setContent(json_encode([
                'code'=> 400,
                'status'=>'fail',
                'message' => 'You can not stop device in edit mode.']));
                return $response;
        }
        $this->denyAccessUnlessGranted(InstanceVoter::STOP_DEVICE, $deviceInstance);

        $entityManager = $this->entityManager;
        //var_dump($deviceInstance->getDevice()); exit;
        $json = $instanceManager->stop($deviceInstance);
        $status = empty($json) ? 204 : 200;
        //$device->setStatus(0);
        $entityManager->flush();

        //return $this->json($json, $status, [], [], true);

        $response = new Response();
        $response->setContent(json_encode([
            'code'=> $status,
            'status'=>'success',
            'message' => 'Node stoped (80051).']));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    
	#[Get('/api/instances/reset/by-uuid/{uuid}', name: 'api_reset_instance_by_uuid', requirements: ["uuid"=>"[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"])]
    public function resetByUuidAction(Request $request, string $uuid, InstanceManager $instanceManager)
    {
        if (!$deviceInstance = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid])) {
            throw new NotFoundHttpException('No instance with UUID ' . $uuid . ".");
        }
        $this->denyAccessUnlessGranted(InstanceVoter::RESET_DEVICE, $deviceInstance);

        $instanceManager->reset($deviceInstance);

        return $this->json();
    }

    
	#[Get('/api/instances/export/by-uuid/{uuid}', name: 'api_export_instance_by_uuid', requirements: ["uuid"=>"[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"])]
    public function exportByUuidAction(Request $request, string $uuid, InstanceManager $instanceManager)
    {
        $name = $request->query->get('name', '');
        $type = $request->query->get('type', '');

        if($name == '') {
            throw new BadRequestHttpException('Name must not be empty.');
        }

        if($type == '') {
            throw new BadRequestHttpException('Instance type must not be empty.');
        }
        
        if ($type == "device") {
            if (!$deviceInstance = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid])) {
                throw new NotFoundHttpException('No instance with UUID ' . $uuid . ".");
            }

            $this->denyAccessUnlessGranted(InstanceVoter::EXPORT_INSTANCE, $deviceInstance);

            $instanceManager->exportDevice($deviceInstance, $name);
        }
        else if ($type == "lab") {
            if (!$labInstance = $this->labInstanceRepository->findOneBy(['uuid' => $uuid])) {
                throw new NotFoundHttpException('No instance with UUID ' . $uuid . ".");
            }

            $this->denyAccessUnlessGranted(InstanceVoter::EXPORT_INSTANCE, $labInstance);

            $instanceManager->exportLab($labInstance, $name);
        }
        else {
            throw new BadRequestHttpException('Instance type must be device or lab.');
        }

        return $this->json();
    }

    
    
	#[Get('/api/instances/by-uuid/{uuid}', name: 'api_get_instance_by_uuid', requirements: ["uuid"=>"[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"])]
    public function fetchByUuidAction(Request $request, string $uuid)
    {
        $data = $this->labInstanceRepository->findOneBy(['uuid' => $uuid]);
        $groups = ['api_get_lab_instance'];

        if (!$data) {
            $data = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid]);
            $groups = ['api_get_device_instance'];
        }

        // uuid not found
        if (!$data) {
            throw new NotFoundHttpException('No instance with UUID ' . $uuid . ".");
        }
        $this->denyAccessUnlessGranted(InstanceVoter::VIEW, $data);

        return $this->json($data, 200, [], $groups);
    }

    
	#[Get('/api/instances/lab/{labUuid}/by-user/{userUuid}', name: 'api_get_lab_instance_by_user', requirements: ["labUuid"=>"[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"])]
    public function fetchLabInstanceByUserAction(
        Request $request,
        string $labUuid,
        string $userUuid,
        UserRepository $userRepository,
        LabRepository $labRepository
    ) {
        if (!$user = $userRepository->findOneBy(['uuid' => $userUuid])) {
            throw new NotFoundHttpException();
        }

        if (!$lab = $labRepository->findOneBy(['uuid' => $labUuid])) {
            throw new NotFoundHttpException();
        }

        if (!$labInstance = $this->labInstanceRepository->findOneBy(['user' => $user, 'lab' => $lab])) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(InstanceVoter::VIEW, $labInstance);
        return $this->json($labInstance, 200, [], ['api_get_lab_instance']);
    }

    
	#[Get('/api/instances/lab/{labUuid}/by-guest/{guestUuid}', name: 'api_get_lab_instance_by_guest', requirements: ["labUuid"=>"[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"])]
    public function fetchLabInstanceByGuestAction(
        Request $request,
        string $labUuid,
        string $guestUuid,
        InvitationCodeRepository $invitationCodeRepository,
        LabRepository $labRepository
    ) {
        if (!$guest = $invitationCodeRepository->findOneBy(['uuid' => $guestUuid])) {
            throw new NotFoundHttpException();
        }

        if (!$lab = $labRepository->findOneBy(['uuid' => $labUuid])) {
            throw new NotFoundHttpException();
        }

        if (!$labInstance = $this->labInstanceRepository->findOneBy(['guest' => $guest, 'lab' => $lab])) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(InstanceVoter::VIEW, $labInstance);
   
        return $this->json($labInstance, 200, [], ['api_get_lab_instance']);
    }

    
	#[Get('/api/instances/lab/{labUuid}/by-group/{groupUuid}', name: 'api_get_lab_instance_by_group', requirements: ["labUuid"=>"[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"])]
    public function fetchLabInstanceByGroupAction(
        Request $request,
        string $labUuid,
        string $groupUuid,
        GroupRepository $groupRepository,
        LabRepository $labRepository
    ) {
        if (!$group = $groupRepository->findOneBy(['uuid' => $groupUuid])) {
            throw new NotFoundHttpException();
        }

        if (!$lab = $labRepository->findOneBy(['uuid' => $labUuid])) {
            throw new NotFoundHttpException();
        }

        if (!$labInstance = $this->labInstanceRepository->findOneBy(['_group' => $group, 'lab' => $lab])) {
            throw new NotFoundHttpException();
        }

        $this->denyAccessUnlessGranted(InstanceVoter::VIEW, $labInstance);

        return $this->json($labInstance, 200, [], ['api_get_lab_instance']);
    }

    /*    /*public function fetchByUserAction(Request $request, string $uuid, UserRepository $userRepository)
    {
        $type = $request->query->get('type', 'lab');
        $user = is_numeric($uuid) ? $userRepository->find($uuid) : $userRepository->findOneBy(['uuid' => $uuid]);

        if (!$user) throw new NotFoundHttpException('User not found.');

        switch ($type) {
            case 'lab':
                $data = $this->labInstanceRepository->findOneBy(['user' => $user]);
                $groups = ['api_get_lab_instance'];
                break;

            case 'device':
                $data = $this->deviceInstanceRepository->findBy(['user' => $user]);
                $groups = ['api_get_device_instance'];
                break;

            default:
                throw new BadRequestHttpException('Unknown instance type.');
        }

        if (!$data) throw new NotFoundHttpException('No instance found.');

        return $this->json($data, 200, [], $groups);
    }*/

    /*    /*public function fetchByGroupAction(Request $request, string $uuid, GroupRepository $groupRepository)
    {
        $type = $request->query->get('type', 'lab');
        $group = is_numeric($uuid) ? $groupRepository->find($uuid) : $groupRepository->findOneBy(['uuid' => $uuid]);

        if (!$group) throw new NotFoundHttpException('Group not found.');

        switch ($type) {
            case 'lab':
                $data = $this->labInstanceRepository->findOneBy(['_group' => $group]);
                $groups = ['api_get_lab_instance'];
                break;

            case 'device':
                $data = $this->deviceInstanceRepository->findBy(['_group' => $group]);
                $groups = ['api_get_device_instance'];
                break;

            default:
                throw new BadRequestHttpException('Unknown instance type.');
        }

        if (!$data) throw new NotFoundHttpException();

        return $this->json($data, 200, [], $groups);
    }*/

    public function fetchLabInstancesByUserUuid(string $uuid)
    {
        $user = is_numeric($uuid) ? $this->userRepository->find($uuid) : $this->userRepository->findOneBy(['uuid' => $uuid]);

        if (!$user) throw new NotFoundHttpException('User not found.');

        $currentUser = $this->getUser();
        if ($currentUser->isAdministrator() || $currentUser == $user) {
            $data = $this->labInstanceRepository->findBy(['user' => $user]);
        }
        else {
            $data = $this->labInstanceRepository->findByUserOfOwnerGroup($user, $currentUser);
        }

        if (!$data) $data=[];

        return $data;
    }

    public function fetchLabInstancesOwnedByUserType(string $userType)
    {

        $instances = $this->labInstanceRepository->findBy(['ownedBy' => 'user']);
        $data = [];
        $user = $this->getUser();

        if ($user->isAdministrator()) {
            if ($userType == "teachers") {
                foreach($instances as $instance) {
                    if ($instance->getOwner()->getHighestRole() == "ROLE_TEACHER") {
                        array_push($data, $instance);
                    }
                }
            }
            else if ($userType == "editors") {
                foreach($instances as $instance) {
                    if ($instance->getOwner()->getHighestRole() == "ROLE_TEACHER_EDITOR") {
                        array_push($data, $instance);
                    }
                }
            }
            else if ($userType == "admins") {
                foreach($instances as $instance) {
                    if ($instance->getOwner()->hasRole("ROLE_ADMINISTRATOR") || $instance->getOwner()->hasRole("ROLE_SUPER_ADMINISTRATOR")) {
                        array_push($data, $instance);
                    }
                }
            }
            else if ($userType == "students") {
                foreach($instances as $instance) {
                    if ($instance->getOwner()->getHighestRole() == "ROLE_USER") {
                        array_push($data, $instance);
                    }
                }
            }
            else {
                $data = false;
            }
        }
        else {
            if ($userType == "teachers" || $userType == "editors") {
                $users = $this->userRepository->findUserTypesByGroups($userType, $user);
                foreach($instances as $instance) {
                    if ($instance->getOwner() == $user) {
                        array_push($data, $instance);
                    }
                    foreach ($users as $teacher) {
                        if ($teacher !== $user) {
                            foreach($this->fetchLabInstancesByUserUuid($teacher->getUuid()) as $userInstance) {
                                if ($userInstance == $instance) {
                                    array_push($data, $instance);
                                }
                            }
                        }
                    }
                }
            }
            else if ($userType == "students") {
                $data = $this->labInstanceRepository->findByUserAndGroupStudents($user);
            }
            else {
                $data = false;
            }
        }
        

        if (!$data) $data= [];

        return $data;
    }

    public function fetchLabInstancesByGroupUuid(string $uuid)
    {
        $group = is_numeric($uuid) ? $this->groupRepository->find($uuid) : $this->groupRepository->findOneBy(['uuid' => $uuid]);
        
        if (!$group) throw new NotFoundHttpException('Group not found.');
        $user = $this->getUser();
        //if ($user->isAdministrator() || $group->isElevatedUser($user)) {
            $data = $this->labInstanceRepository->findByGroup($group, $user);
        /*}
        else {
            throw new AccessDeniedHttpException();
        }*/
       

        if (!$data) $data =[];

        return $data;
    }

    public function fetchLabInstancesByGroup()
    {
        $user = $this->getUser();
        $data = $this->labInstanceRepository->findByUserGroups($user);

        if (!$data) $data=[];

        return $data;
    }

    public function fetchLabInstancesByLabUuid(string $uuid)
    {
        $lab = is_numeric($uuid) ? $this->labRepository->find($uuid) : $this->labRepository->findOneBy(['uuid' => $uuid]);

        if (!$lab) throw new NotFoundHttpException('Lab not found.');

        $user = $this->getUser();
        if ($user->isAdministrator() || $user == $lab->getAuthor()) {
            $data = $this->labInstanceRepository->findBy(['lab' => $lab]);
        }
        else {
            $data = $this->labInstanceRepository->findByLabAndUserGroup($lab, $user);
        }

        if (!$data) $data=[];

        return $data;
    }

    public function fetchLabInstancesOrderedByLab()
    {
       $user = $this->getUser();
       if ($user->isAdministrator())
       {
            $data = $this->labInstanceRepository->findBy([], ['lab'=> 'ASC']);
       }
       else {
            $data = $this->labInstanceRepository->findByLabAuthorAndGroups($user);
       }
       if (!$data) $data= [];

        return $data;
    }

    public function fetchLabInstancesByWorker(string $workerIp)
    {
        $data = $this->labInstanceRepository->findBy(['workerIp' => $workerIp ]);

        return $data;
    }

    
	#[Delete('/api/instances/{uuid}', name: 'api_delete_instance', requirements: ["uuid"=>"[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"])]
    public function deleteRestAction(Request $request, string $uuid, InstanceManager $instanceManager)
    {
        if (!$instance = $this->labInstanceRepository->findOneBy(['uuid' => $uuid])) {
            throw new NotFoundHttpException('No instance with UUID ' . $uuid . '.');
        }

        if ($_SERVER['REMOTE_ADDR'] != "127.0.0.1") {
            
            $this->denyAccessUnlessGranted(InstanceVoter::DELETE, $instance);
        }
        
        
        $lab=$instance->getLab();
        $device=$lab->getDevices();
        
        $from_export=strstr($request->headers->get('referer'),"sandbox");
        
            $instanceManager->delete($instance);
            //$this->logger->debug("Delete from export");
            /*$instanceManager->entityManager->remove($lab);
            $instanceManager->entityManager->persist($lab);
            $instanceManager->entityManager->flush();*/

        return $this->json();
    }

    #[Route(path: '/admin/instances/{type}/{id<\d+>}/delete', name: 'delete_instance', methods: 'GET')]
    public function deleteAction(Request $request, string $type, int $id)
    {
        switch ($type) {
            case 'lab':
                $repository = $this->labInstanceRepository;
                break;

            case 'device':
                $repository = $this->deviceInstanceRepository;
                break;

            case 'network-interface':
                $repository = $this->networkInterfaceInstanceRepository;
                break;

            default:
                throw new BadRequestHttpException('Unknown instance type.');
        }

        $instance = $repository->find($id);

        if (!$instance) throw new NotFoundHttpException('Instance not found.');

        $em = $this->entityManager;
        $em->remove($instance);
        $em->flush();

        if ('json' === $request->getRequestFormat()) {
            return $this->json('Instance has been deleted.');
        }

        $this->addFlash('success', $instance->getUuid() . ' has been deleted.');

        return $this->redirectToRoute('instances');
    }

    #[Route(path: '/instances/{uuid}/view/{type}', name: 'view_instance', requirements: ['uuid' => '[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}', 'type' => 'vnc|login|serial|admin'])]
    public function viewInstanceAction(Request $request, string $uuid, string $type)
    {        
        
        if (!$deviceInstance = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid])) {
            throw new NotFoundHttpException();
        }

        $user = $this->getUser();
        $isOwner;
        $isAdmin = false;
        $isAuthor = false;
        $isTeacher = false;
        $adminConnection = false;
        if ($deviceInstance->getOwnedBy() == 'group'){
            $isOwner = $user->isMemberOf($deviceInstance->getOwner());
        }
        else {
            $isOwner = ($deviceInstance->getOwner() == $user);
        }

        $isAuthor = ($deviceInstance->getLabInstance()->getLab()->getAuthor() == $user);
        if ($user instanceof InvitationCode) {
            $isAdmin = false;
        }
        else {
            $isAdmin =  $user->isAdministrator();
        }

        if(!$isAdmin && !$isAuthor && !$isOwner) {
            return $this->redirectToRoute("index");
        }

        $isTeacherAuthor = (($user->hasRole('ROLE_TEACHER') || $user->hasRole('ROLE_TEACHER_EDITOR')) && $isAuthor); 
        $lab = $deviceInstance->getLab();
        $device = $deviceInstance->getDevice();
        if ($device->getHypervisor()->getName() != "physical") {
            if ($type == "admin") {
                $adminConnection = true;
                $type = "login";
            }
        }
        
        $port_number=$this->isRemoteAccess($deviceInstance,$type);
        if ($port_number) {
            $this->logger->debug("Creation proxy rule to port ".$port_number);
            try {
                if ($type=="vnc")
                $this->proxyManager->createDeviceInstanceProxyRoute(
                    $deviceInstance->getUuid(),
                    $port_number,
                    $deviceInstance->getLabInstance()->getWorkerIp()
                );
                elseif ($type =="login" && $adminConnection && ($isAdmin || $isAuthor))
                $this->proxyManager->createContainerInstanceProxyRoute(
                    $deviceInstance->getUuid(),
                    $port_number+1,
                    $deviceInstance->getLabInstance()->getWorkerIp()
                );
                else $this->proxyManager->createContainerInstanceProxyRoute(
                    $deviceInstance->getUuid(),
                    $port_number,
                    $deviceInstance->getLabInstance()->getWorkerIp()
                );
            } catch (ServerException $exception) {
                $this->logger->error($exception->getResponse()->getBody()->getContents());

                $this->addFlash('danger', 'Cannot forward '.$type.' connection to client. Please try again later or contact an administrator.');
            } catch (RequestException $exception) {
                $this->logger->error($exception);

                $this->addFlash('danger', 'Cannot forward '.$type.' connection to client. Please try again later or contact an administrator.');
            } catch (ConnectException $exception) {
                $this->logger->error($exception);

                $this->addFlash('danger', 'Cannot forward '.$type.' connection to client. Please try again later or contact an administrator.');
            }
        }

        if ($request->get('size') == "fullscreen") {
            $fullscreen = true;
        } else {
            $fullscreen = false;
        }
        
        $this->logger->debug("Fullscreen ?". $fullscreen );
        $ssl=($this->remotelabzProxyUseWss ? 'https' : 'http');
        $this->logger->debug("Proxy in SSL ? ". $ssl);
        return $this->render(($fullscreen ? 'lab/vm_view_fullscreen.html.twig' : 'lab/vm_view.html.twig'), [
            'lab' => $lab,
            'uuid' => $uuid,
            'device' => $device,
            'deviceInstance' => $deviceInstance,
            'ssl' => $ssl,
            'type_control_access' => $type,
            'protocol' => $request->get('protocol') ?: ($this->proxyManager->getRemotelabzProxyUseWss() ? 'wss' : 'ws'),
            'host' => $request->get('host') ?: $this->proxyManager->getRemotelabzProxyServer(),
            'port' => $request->get('port') ?: $this->proxyManager->getRemotelabzProxyPort(),
            'path' => $request->get('path') ?: 'device/' . $deviceInstance->getUuid()
        ]);
    }

    
	#[Get('/api/instances/{uuid}/logs', name: 'api_get_instance_logs', requirements: ["uuid"=>"[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"])]
    public function getLogsAction(Request $request, string $uuid, DeviceInstanceLogRepository $deviceInstanceLogRepository)
    {
        if (!$deviceInstance = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid])) {
            throw new NotFoundHttpException('No instance with UUID ' . $uuid . '.');
        }

        $this->denyAccessUnlessGranted(InstanceVoter::GET_LOGS, $deviceInstance);

        $logs = $deviceInstanceLogRepository->findBy([
            'deviceInstance' => $deviceInstance,
            'scope' => DeviceInstanceLog::SCOPE_PUBLIC
        ], [
            'id' => 'asc'
        ]);

        return $this->json($logs);
    }

    
	#[Post('/api/editButton/display', name: 'api_display_edit_button')]
    public function displayEditButtonAction(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        $lab = $data['lab'];
        $user= $data['user'];
        $labInstance = $data['labInstance'];

        $html = $this->renderView('editButtonDisplay.html.twig', [
            'lab'=>$lab,
            'user'=>$user,
            'labInstance'=>$labInstance
        ]);
        $response = new Response();
        $response->setContent(json_encode([
            'code'=> 200,
            'status'=>'success',
            'data'=>[
                'html'=>$html
            ]]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    //DeviceInstance $deviceInstance : a device Instance
    //String $controlprotocoltype : type of control protocol  
    private function isRemoteAccess($deviceInstance,$controlprotocoltype) {
        $result=false;
        //$this->logger->debug($controlprotocoltype." test ".$deviceInstance->getDevice()->getName()." ".$deviceInstance->getUUID());

        foreach ($deviceInstance->getControlProtocolTypeInstances() as $control_protocol) {
            if (strtolower($control_protocol->getControlProtocolType()->getName())===$controlprotocoltype) {
                $this->logger->debug($controlprotocoltype." detected in ".$deviceInstance->getDevice()->getName()." ".$deviceInstance->getUUID());
                $result=$control_protocol->getPort();
            } else {
                $this->logger->debug($controlprotocoltype." not found : ".$deviceInstance->getDevice()->getName()." ".$deviceInstance->getUUID()." ".$control_protocol->getControlProtocolType()->getName());
            }
        }
        return $result;
    }
}
