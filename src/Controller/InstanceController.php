<?php

namespace App\Controller;

use Exception;
use Psr\Log\LoggerInterface;
use App\Repository\LabRepository;
use App\Repository\UserRepository;
use App\Entity\InstancierInterface;
use App\Repository\GroupRepository;
use App\Exception\InstanceException;
use JMS\Serializer\SerializerInterface;
use App\Entity\NetworkInterfaceInstance;
use JMS\Serializer\SerializationContext;
use App\Repository\LabInstanceRepository;
use App\Service\Instance\InstanceManager;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use App\Repository\DeviceInstanceRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use App\Repository\NetworkInterfaceInstanceRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class InstanceController extends Controller
{
    private $logger;
    private $labInstanceRepository;
    private $deviceInstanceRepository;
    private $networkInterfaceInstanceRepository;
    //For issue #556
    protected $worker_server;
    protected $worker_port;
    protected $websocket_proxy_api_port;
    protected $websocket_proxy_port;
    
    public function __construct(
        LoggerInterface $logger,
        LabInstanceRepository $labInstanceRepository,
        DeviceInstanceRepository $deviceInstanceRepository,
        NetworkInterfaceInstanceRepository $networkInterfaceInstanceRepository,
        //For issue #556
        string $worker_server,
        string $worker_port,
        string $websocket_proxy_api_port,
        string $websocket_proxy_port
    ) {
        $this->logger = $logger;
        $this->labInstanceRepository = $labInstanceRepository;
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->networkInterfaceInstanceRepository = $networkInterfaceInstanceRepository;
        //For issue #556
        $this->worker_server = $worker_server;
        $this->worker_port = $worker_port;
        $this->websocket_proxy_api_port = $websocket_proxy_api_port;
        $this->websocket_proxy_port = $websocket_proxy_port;
    }

    // /**
    //  * @Route("/debug/network/{id}", name="debug_network")
    //  */
    // public function debugNetworkAction(int $id, LabInstanceRepository $labInstanceRepository, SerializerInterface $serializer)
    // {
    //     $labInstance = $labInstanceRepository->find($id);
    //     $context = SerializationContext::create()->setGroups("start_lab");
    //     $labJson = $serializer->serialize($labInstance, 'json', $context);
    //     return new Response($labJson);
    // }

    /**
     * @Route("/admin/instances", name="instances")
     * 
     * @Rest\Get("/api/instances", name="api_get_instances")
     */
    public function indexAction(Request $request)
    {
        if ($request->query->has('uuid')) {
            return $this->redirectToRoute('api_get_instance_by_uuid', ['uuid' => $request->query->get('uuid')]);
        }

        $search = $request->query->get('search', '');
        $filter = $request->query->get('filter', '');
        $type = $request->query->get('type', 'lab');

        switch ($type) {
            case 'lab':
                $data = $this->labInstanceRepository->findAll();
                break;

            case 'device':
                $data = $this->deviceInstanceRepository->findAll();
                break;

            default:
                throw new BadRequestHttpException('Unknown instance type.');
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($data, 200, [], ["instances"]);
        }

        return $this->render('instance/index.html.twig', [
            'labInstances' => $this->labInstanceRepository->findAll(),
            'deviceInstances' => $this->deviceInstanceRepository->findAll(),
            'networkInterfaceInstances' => $this->networkInterfaceInstanceRepository->findAll(),
            'search' => $search,
            'filter' => $filter
        ]);
    }

    /**
     * @Rest\Post("/api/instances/create", name="api_create_instance")
     */
    public function createAction(Request $request, InstanceManager $instanceManager, UserRepository $userRepository, GroupRepository $groupRepository, LabRepository $labRepository)
    {
        $data = json_decode($request->getContent(), true);
        $labUuid = $data['lab'];
        $instancierUuid = $data['instancier'];
        $instancierType = $data['instancierType'];

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

        try {
            $instance = $instanceManager->create($lab, $instancier);
        } catch (Exception $e) {
            throw $e;
        }

        return $this->json($instance, 200, [], ["instances", "instance_manager", "user"]);
    }

    /**
     * @Rest\Get("/api/instances/start/by-uuid/{uuid}", name="api_start_instance_by_uuid")
     */
    public function startByUuidAction(Request $request, string $uuid, InstanceManager $instanceManager)
    {
        if (!$deviceInstance = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid])) {
            throw new NotFoundHttpException('No instance with UUID ' . $uuid . ".");
        }

        $instanceManager->start($deviceInstance);

        return $this->json();
    }

    /**
     * @Rest\Get("/api/instances/stop/by-uuid/{uuid}", name="api_stop_instance_by_uuid")
     */
    public function stopByUuidAction(Request $request, string $uuid, InstanceManager $instanceManager)
    {
        if (!$deviceInstance = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid])) {
            throw new NotFoundHttpException('No instance with UUID ' . $uuid . ".");
        }

        $instanceManager->stop($deviceInstance);

        return $this->json();
    }

    // /**
    //  * @Rest\Get("/api/instances/state/by-uuid/{uuid}", name="api_get_instance_state_by_uuid")
    //  */
    // public function fetchStateByUuidAction(Request $request, string $uuid, InstanceManager $instanceManager)
    // {
    //     if (!$deviceInstance = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid])) {
    //         throw new NotFoundHttpException('No instance with UUID ' . $uuid . ".");
    //     }

    //     $state = $instanceManager->state($deviceInstance);

    //     return $this->json($state);
    // }

    /**
     * @Rest\Get("/api/instances/by-uuid/{uuid}", name="api_get_instance_by_uuid")
     */
    public function fetchByUuidAction(Request $request, string $uuid)
    {
        $data = $this->labInstanceRepository->findOneBy(['uuid' => $uuid]);

        if (!$data) $data = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid]);
        // uuid not found
        if (!$data) throw new NotFoundHttpException('No instance with UUID ' . $uuid . ".");

        return $this->json($data, 200, [], ["instances", "instance_manager"]);
    }

    /**
     * @Rest\Get("/api/instances/lab/{labUuid}/by-user/{userUuid}", name="api_get_lab_instance_by_user")
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

        return $this->json($labInstance, 200, [], ['instance_manager', 'user']);
    }

    /**
     * @Rest\Get("/api/instances/lab/{labUuid}/by-group/{groupUuid}", name="api_get_lab_instance_by_group")
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

        return $this->json($labInstance, 200, [], ['instance_manager', 'user']);
    }

    /**
     * @Rest\Get("/api/instances/by-user/{uuid}", name="api_get_instance_by_user")
     */
    public function fetchByUserAction(Request $request, string $uuid, UserRepository $userRepository)
    {
        $type = $request->query->get('type', 'lab');
        $user = is_numeric($uuid) ? $userRepository->find($uuid) : $userRepository->findOneBy(['uuid' => $uuid]);

        if (!$user) throw new NotFoundHttpException('User not found.');

        switch ($type) {
            case 'lab':
                $data = $this->labInstanceRepository->findOneBy(['user' => $user]);
                break;

            case 'device':
                $data = $this->deviceInstanceRepository->findBy(['user' => $user]);
                break;

            default:
                throw new BadRequestHttpException('Unknown instance type.');
        }

        if (!$data) throw new NotFoundHttpException('No instance found.');

        return $this->json($data, 200, [], ['instance_manager', 'user']);
    }

    /**
     * @Rest\Get("/api/instances/by-group/{uuid}", name="api_get_instance_by_group")
     */
    public function fetchByGroupAction(Request $request, string $uuid, GroupRepository $groupRepository)
    {
        $type = $request->query->get('type', 'lab');
        $group = is_numeric($uuid) ? $groupRepository->find($uuid) : $groupRepository->findOneBy(['uuid' => $uuid]);

        if (!$group) throw new NotFoundHttpException('Group not found.');

        switch ($type) {
            case 'lab':
                $data = $this->labInstanceRepository->findOneBy(['_group' => $group]);
                break;

            case 'device':
                $data = $this->deviceInstanceRepository->findBy(['_group' => $group]);
                break;

            default:
                throw new BadRequestHttpException('Unknown instance type.');
        }

        if (!$data) throw new NotFoundHttpException();

        return $this->json($data, 200, [], ['instance_manager']);
    }

    /**
     * @Rest\Delete("/api/instances/{uuid}", name="api_delete_instance")
     */
    public function deleteRestAction(Request $request, string $uuid, InstanceManager $instanceManager)
    {
        if (!$instance = $this->labInstanceRepository->findOneBy(['uuid' => $uuid])) {
            throw new NotFoundHttpException('No instance with UUID ' . $uuid . '.');
        }

        $instanceManager->delete($instance);

        return $this->json();
    }

    /**
     * @Route("/admin/instances/{type}/{id<\d+>}/delete", name="delete_instance", methods="GET")
     * 
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
     * @Route("/instances/{uuid}/view", name="view_instance")
     */
    public function viewInstanceAction(Request $request, string $uuid, InstanceManager $instanceManager)
    {
        if (!$deviceInstance = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid])) {
            throw new NotFoundHttpException();
        }

        $lab = $deviceInstance->getLab();
        $device = $deviceInstance->getDevice();

        /** @var NetworkInterfaceInstance */
        foreach ($deviceInstance->getNetworkInterfaceInstances() as $networkInterfaceInstance) {
            $networkInterface = $networkInterfaceInstance->getNetworkInterface();

            // if vnc access is requested, register the port in CHP
            if ('VNC' == $networkInterface->getSettings()->getProtocol()) {
                try {
                    $instanceManager->createDeviceInstanceProxyRoute(
                        $deviceInstance->getUuid(),
                        $networkInterfaceInstance->getRemotePort()
                    );
                } catch (ServerException $exception) {
                    $this->logger->error($exception->getResponse()->getBody()->getContents());

                    $this->addFlash('danger', 'Cannot forward VNC connection to client. Please try again later or contact an administrator.');
                } catch (RequestException $exception) {
                    $this->logger->error($exception);

                    $this->addFlash('danger', 'Cannot forward VNC connection to client. Please try again later or contact an administrator.');
                } catch (ConnectException $exception) {
                    $this->logger->error($exception);

                    $this->addFlash('danger', 'Cannot forward VNC connection to client. Please try again later or contact an administrator.');
                }
            }
        }

        if ($request->get('size') == "fullscreen") {
            $fullscreen = true;
        } else {
            $fullscreen = false;
        }
        if (array_key_exists('REQUEST_SCHEME', $_SERVER))
            if (explode('://', strtolower($_SERVER['REQUEST_SCHEME']))[0] == 'https') //False = 0 en php et strpos retourne 0 pour la 1ère place
                $protocol = "wss://";
            else
                $protocol = "ws://";
        else if (array_key_exists('HTTPS', $_SERVER))
            if ($_SERVER['HTTPS'] == 'on')
                $protocol = "wss://";
            else
                $protocol = "ws://";

        $this->logger->debug("Request to ".$request->getHost().":".$this->websocket_proxy_port."/".'device/'.$deviceInstance->getUuid());
                
        return $this->render(($fullscreen ? 'lab/vm_view_fullscreen.html.twig' : 'lab/vm_view.html.twig'), [
            'lab' => $lab,
            'device' => $device,
            'deviceInstance' => $deviceInstance,
            'uuid' => $uuid,
            'host' => $protocol . "" . ($request->get('host') ?: $request->getHost()),
            //'port' => $request->get('port') ?: getenv('WEBSOCKET_PROXY_PORT'),
            'port' => $request->get('port') ?: $this->websocket_proxy_port,
            'path' => $request->get('path') ?: 'device/' . $deviceInstance->getUuid()
        ]);
    }
}
