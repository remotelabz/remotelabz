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
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

class PduOutletDeviceController extends Controller
{
    public $pduOutletDeviceRepository;

    public function __construct(PduOutletDeviceRepository $pduOutletDeviceRepository)
    {
        $this->pduOutletDeviceRepository = $pduOutletDeviceRepository;
    }

    /**
     * @Route("/admin/pdu/outlet/{id<\d+>}/edit", name="edit_pdu_outlet")
     * 
     * @Rest\Put("/api/pdu/outlet/{id<\d+>}/edit", name="edit_add_pdu_outlet")
     * 
     * @IsGranted("ROLE_ADMINISTRATOR", message="Access denied.") 
     */
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
            $entityManager = $this->getDoctrine()->getManager();
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

    /**
     * @Route("/admin/outlet/{id<\d+>}/delete", name="delete_outlet", methods="GET")
     * 
     * @Rest\Delete("/api/outlet/{id<\d+>}/delete", name="api_delete_outlet")
     * 
     * @IsGranted("ROLE_ADMINISTRATOR", message="Access denied.") 
     */
    public function deleteAction(Request $request, int $id)
    {
        if (!$pduOutletDevice = $this->pduOutletDeviceRepository->find($id)) {
            throw new NotFoundHttpException("PDU outlet " . $id . " does not exist.");
        }
        $pdu = $pduOutletDevice->getPdu();
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($pduOutletDevice);
        $entityManager->flush();

        if ('json' === $request->getRequestFormat()) {
            return $this->json();
        }

        return $this->redirectToRoute('show_pdu', ["id"=> $pdu->getId()]);
    }
}
