<?php

namespace App\Controller;

use App\Entity\Pdu;
use App\Entity\PduOutletDevice;
use App\Form\PduType;
use App\Form\PduOutletDeviceType;
use App\Repository\PduOutletDeviceRepository;
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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;

class PduOutletDeviceController extends Controller
{
    public $pduOutletDeviceRepository;

    public function __construct(PduOutletDeviceRepository $pduOutletDeviceRepository, EntityManagerInterface $entityManager)
    {
        $this->pduOutletDeviceRepository = $pduOutletDeviceRepository;
        $this->entityManager = $entityManager;
    }

    
	#[Put('/api/pdu/outlet/{id<\d+>}/edit', name: 'edit_add_pdu_outlet')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    #[Route(path: '/admin/pdu/outlet/{id<\d+>}/edit', name: 'edit_pdu_outlet')]
    public function editPduOutlet(Request $request, int $id)
    {   
        if (!$pduOutletDevice = $this->pduOutletDeviceRepository->find($id)) {
            throw new NotFoundHttpException("PDU outlet " . $id . " does not exist.");
        }

        $pdu = $pduOutletDevice->getPdu();
        $oldDevice = $pduOutletDevice->getDevice();

        $pduOutletDeviceForm = $this->createForm(PduOutletDeviceType::class, $pduOutletDevice,[
            'device' => $pduOutletDevice->getDevice()
        ]);
        $pduOutletDeviceForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $pduOutletDeviceJson = json_decode($request->getContent(), true);
            $pduOutletDeviceForm->submit($pduOutletDeviceJson, false);
        }        

        if ($pduOutletDeviceForm->isSubmitted() && $pduOutletDeviceForm->isValid()) {
            $pduOutlet = $pduOutletDeviceForm->getData();
            $device = $pduOutlet->getDevice();
            if ($device != null  && $device->getOutlet() != null && $device->getOutlet()->getId() != $pduOutletDevice->getId()) {
                $pduOutlet->setDevice($oldDevice);
                $this->addFlash('danger', 'The outlet device has not been edited. It is already in use.');
            }
            $entityManager = $this->entityManager;
            $pduOutletDevice->setDevice($pduOutlet->getDevice());
            $entityManager->persist($pduOutlet);
            $entityManager->flush();

            return $this->render('pdu/view.html.twig', [
                'pdu' => $pdu,
            ]);
        }
        return $this->render('pdu/addOutlet.html.twig', [
            'pduOutletDeviceForm' => $pduOutletDeviceForm->createView(),
            'pduOutletDevice' => $pduOutletDevice
        ]);
    }

    
	#[Delete('/api/outlet/{id<\d+>}/delete', name: 'api_delete_outlet')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    #[Route(path: '/admin/outlet/{id<\d+>}/delete', name: 'delete_outlet', methods: 'GET')]
    public function deleteAction(Request $request, int $id)
    {
        if (!$pduOutletDevice = $this->pduOutletDeviceRepository->find($id)) {
            throw new NotFoundHttpException("PDU outlet " . $id . " does not exist.");
        }
        $pdu = $pduOutletDevice->getPdu();
        $entityManager = $this->entityManager;
        $entityManager->remove($pduOutletDevice);
        $entityManager->flush();

        if ('json' === $request->getRequestFormat()) {
            return $this->json();
        }

        return $this->redirectToRoute('show_pdu', ["id"=> $pdu->getId()]);
    }
}
