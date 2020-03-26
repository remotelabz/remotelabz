<?php

namespace App\Controller;

use App\Utils\JsonRequest;
use App\Instance\InstanceManager;
use App\Repository\UserRepository;
use App\Repository\GroupRepository;
use FOS\RestBundle\Context\Context;
use App\Repository\LabInstanceRepository;
use App\Repository\DeviceInstanceRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use App\Repository\NetworkInterfaceInstanceRepository;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InstanceController extends AbstractFOSRestController
{
    private $labInstanceRepository;
    private $deviceInstanceRepository;
    private $networkInterfaceInstanceRepository;

    public function __construct(
        LabInstanceRepository $labInstanceRepository,
        DeviceInstanceRepository $deviceInstanceRepository,
        NetworkInterfaceInstanceRepository $networkInterfaceInstanceRepository
    ) {
        $this->labInstanceRepository = $labInstanceRepository;
        $this->deviceInstanceRepository = $deviceInstanceRepository;
        $this->networkInterfaceInstanceRepository = $networkInterfaceInstanceRepository;
    }

    /**
     * @Route("/admin/instances", name="instances")
     * 
     * @Rest\Get("/api/instances", name="api_get_instances")
     */
    public function indexAction(Request $request)
    {
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
        }

        $context = new Context();

        $view = $this->view($data)
            ->setTemplate("instance/index.html.twig")
            ->setTemplateData([
                'labInstances' => $this->labInstanceRepository->findAll(),
                'deviceInstances' => $this->deviceInstanceRepository->findAll(),
                'networkInterfaceInstances' => $this->networkInterfaceInstanceRepository->findAll(),
                'search' => $search,
                'filter' => $filter
            ])
            ->setContext($context->setGroups(["instances"]));

        return $this->handleView($view);
    }

    /**
     * @Rest\Get("/api/instances/start/by-uuid/{uuid}", name="api_start_instance_by_uuid")
     */
    public function startByUuidAction(Request $request, string $uuid, InstanceManager $instanceManager)
    {
        $data = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid]);

        if (null === $data) { // uuid not found
            throw new NotFoundHttpException('No instance with UUID ' . $uuid . ".");
        }

        $instanceManager->start($uuid);

        $view = $this->view(null, 200);

        return $this->handleView($view);
    }

    /**
     * @Rest\Get("/api/instances/by-uuid/{uuid}", name="api_get_instance_by_uuid")
     */
    public function fetchByUuidAction(Request $request, string $uuid)
    {
        $data = $this->labInstanceRepository->findOneBy(['uuid' => $uuid]);

        if (null === $data) {
            $data = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid]);
        }

        if (null === $data) { // uuid not found
            throw new NotFoundHttpException('No instance with UUID ' . $uuid . ".");
        }

        $context = new Context();
        $view = $this->view($data)
            ->setContext($context->setGroups(["instances", "instance_manager"]));

        return $this->handleView($view);
    }

    /**
     * @Rest\Get("/api/instances/by-user/{userId}", name="api_get_instance_by_user")
     */
    public function fetchByUserAction(Request $request, string $userId, UserRepository $userRepository)
    {
        $type = $request->query->get('type', 'lab');
        $user = is_numeric($userId) ? $userRepository->find($userId) : $userRepository->findOneBy(['email' => $userId]);

        if (null === $user) {
            throw new NotFoundHttpException('User not found.');
        }

        switch ($type) {
            case 'lab':
                $data = $this->labInstanceRepository->findOneBy(['user' => $user]);
                break;

            case 'device':
                $data = $this->deviceInstanceRepository->findBy(['user' => $user]);
                break;
        }

        $context = new Context();
        $view = $this->view($data)
            ->setContext($context->setGroups(["instances"]));

        return $this->handleView($view);
    }

    /**
     * @Rest\Get("/api/instances/by-group/{groupId}", name="api_get_instance_by_group")
     */
    public function fetchByGroupAction(Request $request, string $groupId, GroupRepository $groupRepository)
    {
        $type = $request->query->get('type', 'lab');
        $group = is_numeric($groupId) ? $groupRepository->find($groupId) : $groupRepository->findOneBy(['uuid' => $groupId]);

        if (null === $group) {
            throw new NotFoundHttpException('Group not found.');
        }

        switch ($type) {
            case 'lab':
                $data = $this->labInstanceRepository->findOneBy(['_group' => $group]);
                break;

            case 'device':
                $data = $this->deviceInstanceRepository->findBy(['_group' => $group]);
                break;
        }

        $context = new Context();
        $view = $this->view($data)
            ->setContext($context->setGroups(["instances"]));

        return $this->handleView($view);
    }

    /**
     * @Route("/admin/instances/{type}/{id<\d+>}/delete", name="delete_instance", methods="GET")
     * 
     * @Rest\Delete("/api/instances/{type}/{id<\d+>}", name="api_delete_instance")
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
                throw new NotFoundHttpException();
                break;
        }

        $data = null;
        $status = 200;

        $instance = $repository->find($id);

        if ($instance == null) {
            $status = 404;
            $this->addFlash('danger', 'No such instance.');
        } else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($instance);
            $em->flush();

            $data = [
                'message' => 'Instance has been deleted.'
            ];

            $this->addFlash('success', $instance->getUuid() . ' has been deleted.');
        }

        $view = $this->view('Instance has been deleted.')
            ->setLocation('instances');

        return $this->handleView($view);

        // return $this->redirectToRoute('instances');
    }
}
