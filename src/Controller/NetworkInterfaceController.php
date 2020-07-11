<?php

namespace App\Controller;

use App\Entity\NetworkSettings;
use App\Entity\NetworkInterface;
use App\Form\NetworkInterfaceType;
use FOS\RestBundle\Context\Context;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\NetworkInterfaceRepository;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class NetworkInterfaceController extends Controller
{
    public $networkInterfaceRepository;

    public function __construct(NetworkInterfaceRepository $networkInterfaceRepository)
    {
        $this->networkInterfaceRepository = $networkInterfaceRepository;
    }

    /**
     * @Route("/admin/network-interfaces", name="network_interfaces")
     */
    public function indexAction(Request $request)
    {
        $networkInterface = new NetworkInterface();
        $networkInterfaceForm = $this->createForm(NetworkInterfaceType::class, $networkInterface);
        $networkInterfaceForm->handleRequest($request);

        if ($networkInterfaceForm->isSubmitted() && $networkInterfaceForm->isValid()) {
            $networkInterface = $networkInterfaceForm->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($networkInterface);
            $entityManager->flush();

            $this->addFlash('success', 'Interface has been created.');
        }

        return $this->render('network_interface/index.html.twig', [
            'networkInterfaceForm' => $networkInterfaceForm->createView(),
        ]);
    }

    /**
     * @Rest\Get("/api/network-interfaces/{id<\d+>}", name="api_get_network_interface")
     */
    public function getAction(Request $request, int $id)
    {
        if (!$networkInterface = $this->networkInterfaceRepository->find($id))
            throw new NotFoundHttpException();

        return $this->json($networkInterface, 200, [], ['network_interfaces']);
    }

    /**
     * @Route("/admin/network-interfaces/new", name="new_network_interface", methods={"GET", "POST"})
     * 
     * @Rest\Post("/api/network-interfaces", name="api_new_network_interface")
     */
    public function newAction(Request $request)
    {
        $networkInterface = new NetworkInterface();
        $networkInterfaceForm = $this->createForm(NetworkInterfaceType::class, $networkInterface);
        $networkInterfaceForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $networkInterface = json_decode($request->getContent(), true);
            $networkInterfaceForm->submit($networkInterface, false);
        }

        if ($networkInterfaceForm->isSubmitted() && $networkInterfaceForm->isValid()) {
            /** @var NetworkInterface $networkInterface */
            $networkInterface = $networkInterfaceForm->getData();
            $accessType = $networkInterfaceForm->get('accessType')->getData();
            $networkSettings = new NetworkSettings();
            $networkSettings
                ->setName($networkInterface->getName() . '_settings')
                ->setProtocol($accessType);
            $networkInterface->setSettings($networkSettings);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($networkInterface);
            $entityManager->flush();

            if ('json' === $request->getRequestFormat()) {
                return $this->json($networkInterface, 201, [], ['network_interfaces']);
            }

            $this->addFlash('success', 'Network interface has been created.');

            return $this->redirectToRoute('network_interfaces');
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($networkInterfaceForm, 400, [], ['network_interfaces']);
        }

        return $this->render('network_interface/new.html.twig', [
            'networkInterfaceForm' => $networkInterfaceForm->createView()
        ]);
    }

    /**
     * @Route("/admin/network-interfaces/{id<\d+>}/edit", name="edit_network_interface", methods={"GET", "POST"})
     * 
     * @Rest\Put("/api/network-interfaces/{id<\d+>}", name="api_edit_network_interface")
     */
    public function editAction(Request $request, int $id)
    {
        $networkInterface = $this->networkInterfaceRepository->find($id);

        if (null === $networkInterface) {
            throw new NotFoundHttpException();
        }

        $networkInterfaceForm = $this->createForm(NetworkInterfaceType::class, $networkInterface);
        $networkInterfaceForm->get('accessType')->setData($networkInterface->getSettings()->getProtocol());
        $networkInterfaceForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $networkInterface = json_decode($request->getContent(), true);
            $networkInterfaceForm->submit($networkInterface, false);
        }

        if ($networkInterfaceForm->isSubmitted() && $networkInterfaceForm->isValid()) {
            /** @var NetworkInterface $networkInterface */
            $networkInterface = $networkInterfaceForm->getData();
            $accessType = $networkInterfaceForm->get('accessType')->getData();
            $networkSettings = $networkInterface->getSettings();
            $networkSettings
                ->setName($networkInterface->getName() . '_settings')
                ->setProtocol($accessType);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($networkInterface);
            $entityManager->flush();

            if ('json' === $request->getRequestFormat()) {
                return $this->json($networkInterface, 200, [], ['network_interfaces']);
            }

            $this->addFlash('success', 'Network interface has been edited.');

            return $this->redirectToRoute('network_interfaces');
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($networkInterfaceForm, 400, [], ['network_interfaces']);
        }

        return $this->render('network_interface/new.html.twig', [
            'networkInterfaceForm' => $networkInterfaceForm->createView(),
            'networkInterface' => $networkInterface
        ]);
    }

    /**
     * @Route("/network-interfaces", name="get_network_interface", methods="GET")
     */
    public function cgetAction()
    {
        return $this->json($this->networkInterfaceRepository->findAll());
    }

    /**
     * @Route("/admin/network-interfaces/{id<\d+>}/delete", name="delete_network_interface", methods="GET")
     * 
     * @Rest\Delete("/api/network-interfaces/{id<\d+>}", name="api_delete_network_interface")
     */
    public function deleteAction(Request $request, int $id)
    {
        if (!$networkInterface = $this->networkInterfaceRepository->find($id)) {
            throw new NotFoundHttpException();
        }

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($networkInterface);
        $entityManager->flush();

        if ('json' === $request->getRequestFormat()) {
            return $this->json();
        }

        $this->addFlash('success', $networkInterface->getName() . ' has been deleted.');

        return $this->redirectToRoute('network_interfaces');
    }

    // /**
    //  * @Route("/admin/network-interface/{id<\d+>}", name="delete_network_interface", methods="DELETE")
    //  */
    // public function deleteAction($id)
    // {
    //     $status = 200;
    //     $data = [];

    //     $networkInterface = $this->networkInterfaceRepository->find($id);

    //     if ($networkInterface == null) {
    //         $status = 404;
    //     } elseif ($networkInterface->getDevice() !== null && $networkInterface->getDevice()->getInstances()->count() > 0) {
    //         $status = 403;
    //         $data['message'] = "This interface is attached to a running device. Please stop all devices instances before you delete it.";
    //     } else {
    //         $em = $this->getDoctrine()->getManager();
    //         $em->remove($networkInterface);
    //         $em->flush();

    //         $data['message'] = 'Interface has been deleted.';
    //     }

    //     return $this->renderJson($data, $status);
    // }
}
