<?php

namespace App\Controller;

use Exception;
use Psr\Log\LoggerInterface;
use App\Entity\DeviceInstanceLog;
use App\Entity\InstancierInterface;

use App\Repository\UserRepository;
use App\Repository\GroupRepository;
use App\Repository\LabInstanceRepository;
use App\Repository\DeviceInstanceLogRepository;
use App\Repository\LabRepository;
use App\Repository\NetworkInterfaceInstanceRepository;
use App\Repository\DeviceInstanceRepository;

use App\Service\Proxy\ProxyManager;
use App\Service\Instance\InstanceManager;

use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use FOS\RestBundle\Controller\Annotations as Rest;

use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;

class InstanceController extends Controller
{
    private $logger;
    protected $proxyManager;
    private $labInstanceRepository;
    private $deviceInstanceRepository;
    private $networkInterfaceInstanceRepository;
    private $serializer;
    protected $remotelabzProxyUseWss;
    
    /** @var LabRepository $labRepository */
    private $labRepository;

    public function __construct(
        LoggerInterface $logger,
        ProxyManager $proxyManager,
        LabInstanceRepository $labInstanceRepository,
        DeviceInstanceRepository $deviceInstanceRepository,
        LabRepository $labRepository,
        NetworkInterfaceInstanceRepository $networkInterfaceInstanceRepository,
        SerializerInterface $serializerInterface,
        bool $remotelabzProxyUseWss
    ) {
        $this->logger = $logger;
        $this->labInstanceRepository = $labInstanceRepository;
        $this->labRepository = $labRepository;
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->networkInterfaceInstanceRepository = $networkInterfaceInstanceRepository;
        $this->proxyManager = $proxyManager;
        $this->serializer = $serializerInterface;
        $this->remotelabzProxyUseWss = $remotelabzProxyUseWss;
    }

    /**
     * @Route("/instances", name="instances")
     * 
     * @Rest\Get("/api/instances", name="api_get_instances")
     */
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
        $filter = $request->query->get('filter', '');
        $type = $request->query->get('type', 'lab');

        //Only fetch the instance of the user and in which it is group admin or group owner
        if ($user->isAdministrator()) {
            $AllLabInstances=$this->labInstanceRepository->findAll();
        }
        else {
            //Return all instances of the 
            //$AllLabInstances=$this->labInstanceRepository->findByUserAndGroups($user);
            $AllLabInstances=$this->labInstanceRepository->findByUserAndAllMembersGroups($user);
        }
        
        switch ($type) {
            case 'lab':
                $data = $AllLabInstances;
                $groups = ['api_get_lab_instance'];
                break;
            default:
                throw new BadRequestHttpException('Unknown instance type.');
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($data, 200, [], $groups);
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
        
        return $this->render('instance/index.html.twig', [
            'labInstances' => $labInstances
        ]);
    }

    /**
     * @Rest\Post("/api/instances/create", name="api_create_instance")
     */
    public function createAction(Request $request, InstanceManager $instanceManager, UserRepository $userRepository, GroupRepository $groupRepository, LabRepository $labRepository)
    {
        $labUuid = $request->request->get('lab');
        $instancierUuid = $request->request->get('instancier');
        $instancierType = $request->request->get('instancierType');

        switch ($instancierType) {
            case 'user':
                $repository = $userRepository;
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
        
        /*foreach ($request->headers as $key => $part) {
            $this->logger->debug("Key: ".$key);
        }*/
        try {
            $this->logger->debug("Lab instance creation: ".$lab->getName());
            $this->logger->info($this->getUser()->getFirstname()." ".$this->getUser()->getName()." ".$this->getUser()->getUuid()." enter in lab ".$lab->getName()." ".$lab->getUuid());
            $instance = $instanceManager->create($lab, $instancier);
            $this->logger->info("Lab instance ".$instance->getUuid()." created by ".$this->getUser()->getFirstname()." ".$this->getUser()->getName()." ".$this->getUser()->getUuid()." Wait ack created message");
        } catch (Exception $e) {
            throw $e;
        }

        return $this->json($instance, 200, [], ['api_get_lab_instance']);
    }

    /**
     * @Rest\Get("/api/instances/start/by-uuid/{uuid}", name="api_start_instance_by_uuid", requirements={"uuid"="[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"})
     */
    public function startByUuidAction(Request $request, string $uuid, InstanceManager $instanceManager)
    {
        if (!$deviceInstance = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid])) {
            throw new NotFoundHttpException('No instance with UUID ' . $uuid . ".");
        }

        $json = $instanceManager->start($deviceInstance);
        $status = empty($json) ? 204 : 200;

        return $this->json($json, $status, [], [], true);
    }

    /**
     * @Rest\Get("/api/instances/stop/by-uuid/{uuid}", name="api_stop_instance_by_uuid", requirements={"uuid"="[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"})
     */
    public function stopByUuidAction(Request $request, string $uuid, InstanceManager $instanceManager)
    {
        if (!$deviceInstance = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid])) {
            throw new NotFoundHttpException('No instance with UUID ' . $uuid . ".");
        }

        $instanceManager->stop($deviceInstance);

        return $this->json();
    }

    /**
     * @Rest\Get("/api/instances/export/by-uuid/{uuid}", name="api_export_instance_by_uuid", requirements={"uuid"="[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"})
     */
    public function exportByUuidAction(Request $request, string $uuid, InstanceManager $instanceManager)
    {
        $name = $request->query->get('name', '');

        if($name == '') {
            throw new BadRequestHttpException('Name must not be empty.');
        }
        
        if (!$deviceInstance = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid])) {
            throw new NotFoundHttpException('No instance with UUID ' . $uuid . ".");
        }

        $instanceManager->export($deviceInstance, $name);

        return $this->json();
    }

    
    /**
     * @Rest\Get("/api/instances/by-uuid/{uuid}", name="api_get_instance_by_uuid", requirements={"uuid"="[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"})
     */
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

        return $this->json($data, 200, [], $groups);
    }

    /**
     * @Rest\Get("/api/instances/lab/{labUuid}/by-user/{userUuid}", name="api_get_lab_instance_by_user", requirements={"labUuid"="[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"})
     */
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

        return $this->json($labInstance, 200, [], ['api_get_lab_instance']);
    }

    /**
     * @Rest\Get("/api/instances/lab/{labUuid}/by-group/{groupUuid}", name="api_get_lab_instance_by_group", requirements={"labUuid"="[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"})
     */
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

        return $this->json($labInstance, 200, [], ['api_get_lab_instance']);
    }

    /**
     * @Rest\Get("/api/instances/by-user/{uuid}", name="api_get_instance_by_user", requirements={"uuid"="[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"})
     */
    public function fetchByUserAction(Request $request, string $uuid, UserRepository $userRepository)
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
    }

    /**
     * @Rest\Get("/api/instances/by-group/{uuid}", name="api_get_instance_by_group", requirements={"uuid"="[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"})
     */
    public function fetchByGroupAction(Request $request, string $uuid, GroupRepository $groupRepository)
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
    }

    /**
     * @Rest\Delete("/api/instances/{uuid}", name="api_delete_instance", requirements={"uuid"="[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"})
     */
    public function deleteRestAction(Request $request, string $uuid, InstanceManager $instanceManager)
    {
        if (!$instance = $this->labInstanceRepository->findOneBy(['uuid' => $uuid])) {
            throw new NotFoundHttpException('No instance with UUID ' . $uuid . '.');
        }

        $lab=$instance->getLab();
        $device=$lab->getDevices();
        
        $from_export=strstr($request->headers->get('referer'),"devices_sandbox");
        
            $instanceManager->delete($instance);
            $this->logger->debug("Delete from export");
            /*$instanceManager->entityManager->remove($lab);
            $instanceManager->entityManager->persist($lab);
            $instanceManager->entityManager->flush();*/

        return $this->json();
    }

    /**
     * @Route("/admin/instances/{type}/{id<\d+>}/delete", name="delete_instance", methods="GET")
     */
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

        $em = $this->getDoctrine()->getManager();
        $em->remove($instance);
        $em->flush();

        if ('json' === $request->getRequestFormat()) {
            return $this->json('Instance has been deleted.');
        }

        $this->addFlash('success', $instance->getUuid() . ' has been deleted.');

        return $this->redirectToRoute('instances');
    }

    /**
     * @Route("/instances/{uuid}/view/{type}", name="view_instance", requirements={
     * "uuid"="[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}","type"="vnc|login|serial"
     * }
     * )
     */
    public function viewInstanceAction(Request $request, string $uuid, string $type)
    {
        if (!$deviceInstance = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid])) {
            throw new NotFoundHttpException();
        }

        $lab = $deviceInstance->getLab();
        $device = $deviceInstance->getDevice();

        $port_number=$this->isRemoteAccess($deviceInstance,$type);
        if ($port_number) {
            $this->logger->debug("Creation proxy rule to port ".$port_number);
            try {
                if ($type=="vnc")
                $this->proxyManager->createDeviceInstanceProxyRoute(
                    $deviceInstance->getUuid(),
                    $port_number
                );
                else $this->proxyManager->createContainerInstanceProxyRoute(
                    $deviceInstance->getUuid(),
                    $port_number
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
        $this->logger->debug("Proxy in SSL ?". $ssl);
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

    /**
     * @Rest\Get("/api/instances/{uuid}/logs", name="api_get_instance_logs", requirements={"uuid"="[[:xdigit:]]{8}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{4}-[[:xdigit:]]{12}"})
     */
    public function getLogsAction(Request $request, string $uuid, DeviceInstanceLogRepository $deviceInstanceLogRepository)
    {
        if (!$deviceInstance = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid])) {
            throw new NotFoundHttpException('No instance with UUID ' . $uuid . '.');
        }

        $logs = $deviceInstanceLogRepository->findBy([
            'deviceInstance' => $deviceInstance,
            'scope' => DeviceInstanceLog::SCOPE_PUBLIC
        ], [
            'id' => 'asc'
        ]);

        return $this->json($logs);
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
