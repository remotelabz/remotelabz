<?php

namespace App\Controller;

use App\Utils\JsonRequest;
use App\Repository\LabInstanceRepository;
use App\Repository\DeviceInstanceRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\NetworkInterfaceInstanceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InstanceController extends AppController
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
     */
    public function indexAction(Request $request)
    {
        $search = $request->query->get('search', '');
        
        // if ($search !== '') {
        //     $data = $this->labRepository->findByNameLike($search);
        // } else {
        //     $data = $this->labRepository->findAll();
        // }

        $data = [];

        $data['lab'] = $this->labInstanceRepository->findAll();
        $data['device'] = $this->deviceInstanceRepository->findAll();
        $data['network_interface'] = $this->networkInterfaceInstanceRepository->findAll();

        if ($this->getRequestedFormat($request) === JsonRequest::class) {
            return $this->json($data);
        }
        
        return $this->render('instance/index.html.twig', [
            'labInstances' => $data['lab'],
            'deviceInstances' => $data['device'],
            'networkInterfaceInstances' => $data['network_interface'],
            'search' => $search
        ]);
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

            $this->addFlash('success', $instance->getUuid().' has been deleted.');
        }

        if ($this->getRequestedFormat($request) === JsonRequest::class) {
            return $this->json($data, $status);
        }

        return $this->redirectToRoute('instances');
    }
}
