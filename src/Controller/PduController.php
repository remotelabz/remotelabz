<?php

namespace App\Controller;

use App\Entity\Pdu;
use App\Entity\PduOutletDevice;
use App\Form\PduType;
use App\Form\PduOutletDeviceType;
use App\Repository\PduRepository;
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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;

class PduController extends Controller
{
    public $pduRepository;

    public function __construct(PduRepository $pduRepository, EntityManagerInterface $entityManager)
    {
        $this->pduRepository = $pduRepository;
        $this->entityManager = $entityManager;
    }

    
	#[Get('/api/pdus', name: 'api_pdus')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    #[Route(path: '/admin/pdus', name: 'pdus')]
    public function indexAction(Request $request)
    {
        $pdus = $this->pduRepository->findAll();

        if ('json' === $request->getRequestFormat()) {
            return $this->json($pdus, 200, [], ['api_get_pdus']);
        }

        return $this->render('pdu/index.html.twig', [
            'pdus' => $pdus,
        ]);
    }

    
	#[Get('/api/pdu/{id<\d+>}', name: 'api_show_pdu')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    #[Route(path: '/admin/pdu/{id<\d+>}', name: 'show_pdu')]
    public function showAction(Request $request, int $id)
    {
        if (!$pdu = $this->pduRepository->find($id)) {
            throw new NotFoundHttpException("PDU " . $id . " does not exist.");
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($pdu, 200, [], ['api_get_pdus']);
        }

        return $this->render('pdu/view.html.twig', [
            'pdu' => $pdu,
        ]);
    }

    
	#[Post('/api/pdus', name: 'api_new_pdu')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    #[Route(path: '/admin/pdus/new', name: 'new_pdu', methods: ['GET', 'POST'])]
    public function newAction(Request $request)
    {
        $pdu = new Pdu();
        $pduForm = $this->createForm(PduType::class, $pdu);
        $pduForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $pdu = json_decode($request->getContent(), true);
            $pduForm->submit($pdu);
        }

        if ($pduForm->isSubmitted() && $pduForm->isValid()) {
            /** @var Pdu $pdu */
            $pdu = $pduForm->getData();
            $numberOfOutlets = $pdu->getNumberOfOutlets();
            $entityManager = $this->entityManager;
            $entityManager->persist($pdu);
            $entityManager->flush();
            for ($i =1; $i<=$numberOfOutlets; $i++) {
                $pduOutlet = new PduOutletDevice();
                $pduOutlet->setPdu($pdu);
                $pduOutlet->setOutlet($i);
                $entityManager->persist($pduOutlet);
                $entityManager->flush();
            }

            if ('json' === $request->getRequestFormat()) {
                return $this->json($pdu, 201, [], ['api_get_pdus']);
            }

            $this->addFlash('success', 'PDU has been created.');

            return $this->redirectToRoute('pdus');
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($pduForm, 200, [], ['api_get_pdus']);
        }

        return $this->render('pdu/new.html.twig', [
            'pduForm' => $pduForm->createView()
        ]);
    }

    
	#[Put('/api/pdus/{id<\d+>}/outlet', name: 'api_add_pdu_outlet')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    #[Route(path: '/admin/pdus/{id<\d+>}/outlet', name: 'add_pdu_outlet')]
    public function addPduOutlet(Request $request, int $id)
    {   
        if (!$pdu = $this->pduRepository->find($id)) {
            throw new NotFoundHttpException("PDU " . $id . " does not exist.");
        }

        if (count($pdu->getOutlets()) >= $pdu->getNumberOfOutlets()) {
            throw new BadRequestHttpException("The PDU ".$pdu->getIp()." already contains its max number of outlets(".$pdu->getNumberOfOutlets().").");
        }
        
        $pduOutletDevice = new PduOutletDevice();
        $pduOutletDeviceForm = $this->createForm(PduOutletDeviceType::class, $pduOutletDevice);
        $pduOutletDeviceForm->handleRequest($request);

        if ($pduOutletDeviceForm->isSubmitted() && $pduOutletDeviceForm->isValid()) {
            $outletNumbers = [];
            foreach ($pdu->getOutlets() as $outlet) {
                array_push($outletNumbers,$outlet->getOutlet());
            }
            sort($outletNumbers);
            $outletNumber = null;
            for ($i = 1; $i <= $pdu->getNumberOfOutlets(); $i++) {
                if (!in_array($i, $outletNumbers)) {
                    $outletNumber = $i;
                    break;
                }
            }
            if ($outletNumber != null) {
                $pduOutlet = $pduOutletDeviceForm->getData();
                $device = $pduOutlet->getDevice();
                if ($device != null && $device->getOutlet() != null) {
                    $pduOutlet->setDevice(null);
                    $this->addFlash('warning', 'The device is already connected to another outlet');
                }
                $entityManager = $this->entityManager;
                $pduOutlet->setPdu($pdu);
                $pduOutlet->setOutlet($outletNumber);
                $entityManager->persist($pduOutlet);
                $pdu->addOutlet($pduOutlet);
                $entityManager->flush();
                $this->addFlash('success', 'The outlet has been added');
            }
            return $this->render('pdu/view.html.twig', [
                'pdu' => $pdu,
            ]);
        }
        return $this->render('pdu/addOutlet.html.twig', [
            'pduOutletDeviceForm' => $pduOutletDeviceForm->createView()
        ]);
    }

    
	#[Put('/api/pdus/{id<\d+>}', name: 'api_edit_pdu')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    #[Route(path: '/admin/pdus/{id<\d+>}/edit', name: 'edit_pdu')]
    public function editAction(Request $request, int $id)
    {
        if (!$pdu = $this->pduRepository->find($id)) {
            throw new NotFoundHttpException("PDU " . $id . " does not exist.");
        }
        $oldNumberOfOutlets = $pdu->getNumberOfOutlets();
        $outlets = $pdu->getOutlets();

        $pduForm = $this->createForm(PduType::class, $pdu);
        $pduForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $pdu = json_decode($request->getContent(), true);
            $pduForm->submit($pdu, false);
        }

        if ($pduForm->isSubmitted() && $pduForm->isValid()) {
            /** @var Flavor $flavor */
            $pdu = $pduForm->getData();
            $numberOfOutlets = $pdu->getNumberOfOutlets();
            $entityManager = $this->entityManager;
            $entityManager->persist($pdu);
            $entityManager->flush();
            if ($numberOfOutlets < $oldNumberOfOutlets) {
                for($i = $oldNumberOfOutlets; $i >= $numberOfOutlets +1; $i--) {
                    foreach ($outlets as $outlet) {
                        if ($outlet->getOutlet() == $i) {
                            $entityManager->remove($outlet);
                            $entityManager->flush();
                            break;
                        }
                    }
                }
            }
            else if ($numberOfOutlets > $oldNumberOfOutlets) {
                for ($i = $oldNumberOfOutlets +1; $i <= $numberOfOutlets; $i++) {
                    $alReadyExist = false;
                    foreach ($outlets as $outlet) {
                        if ($outlet->getOutlet() == $i) {
                            $alReadyExist = true;
                            break;
                        }
                    }
                    if ($alReadyExist == false) {
                        $pduOutlet = new PduOutletDevice();
                        $pduOutlet->setPdu($pdu);
                        $pduOutlet->setOutlet($i);
                        $entityManager->persist($pduOutlet);
                        $entityManager->flush();
                    }
                }
            }

            if ('json' === $request->getRequestFormat()) {
                return $this->json($pdu, 200, [], ['api_get_pdus']);
            }

            $this->addFlash('success', 'PDU has been edited.');

            return $this->redirectToRoute('pdus');
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($pduForm, 200, [], ['api_get_pdus']);
        }

        return $this->render('pdu/new.html.twig', [
            'pduForm' => $pduForm->createView(),
            'pdu' => $pdu
        ]);
    }

    
	#[Delete('/api/pdu/{id<\d+>}/delete', name: 'api_delete_pdu')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    #[Route(path: '/admin/pdu/{id<\d+>}/delete', name: 'delete_pdu', methods: 'GET')]
    public function deleteAction(Request $request, int $id)
    {
        if (!$pdu = $this->pduRepository->find($id)) {
            throw new NotFoundHttpException("PDU " . $id . " does not exist.");
        }

        $entityManager = $this->entityManager;
        $entityManager->remove($pdu);
        $entityManager->flush();

        if ('json' === $request->getRequestFormat()) {
            return $this->json();
        }

        return $this->redirectToRoute('pdus');
    }
}
