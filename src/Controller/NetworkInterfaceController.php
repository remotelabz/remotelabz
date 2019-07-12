<?php

namespace App\Controller;

use App\Entity\NetworkSettings;
use App\Entity\NetworkInterface;
use App\Form\NetworkInterfaceType;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\NetworkInterfaceRepository;
use Symfony\Component\Routing\Annotation\Route;

class NetworkInterfaceController extends AppController
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
     * @Route("/admin/network-interfaces/new", name="new_network_interface", methods={"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $networkInterface = new NetworkInterface();
        $networkInterfaceForm = $this->createForm(NetworkInterfaceType::class, $networkInterface);
        $networkInterfaceForm->handleRequest($request);

        if ($networkInterfaceForm->isSubmitted() && $networkInterfaceForm->isValid()) {
            /** @var NetworkInterface $networkInterface */
            $networkInterface = $networkInterfaceForm->getData();
            $accessType = $networkInterfaceForm->get('accessType')->getData();
            $networkSettings = new NetworkSettings();
            $networkSettings
                ->setName($networkInterface->getName() . '_settings')
                ->setProtocol($accessType);
            ;
            $networkInterface->setSettings($networkSettings);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($networkInterface);
            $entityManager->flush();

            $this->addFlash('success', 'Network interface has been created.');

            return $this->redirectToRoute('network_interfaces');
        }

        return $this->render('network_interface/new.html.twig', [
            'networkInterfaceForm' => $networkInterfaceForm->createView()
        ]);
    }

    /**
     * @Route("/admin/network-interfaces/{id<\d+>}/edit", name="edit_network_interface", methods={"GET", "POST"})
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

        if ($networkInterfaceForm->isSubmitted() && $networkInterfaceForm->isValid()) {
            /** @var NetworkInterface $networkInterface */
            $networkInterface = $networkInterfaceForm->getData();
            $accessType = $networkInterfaceForm->get('accessType')->getData();
            $networkSettings = $networkInterface->getSettings();
            $networkSettings
                ->setName($networkInterface->getName() . '_settings')
                ->setProtocol($accessType);
            ;
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($networkInterface);
            $entityManager->flush();

            $this->addFlash('success', 'Network interface has been edited.');

            return $this->redirectToRoute('network_interfaces');
        }

        return $this->render('network_interface/new.html.twig', [
            'networkInterfaceForm' => $networkInterfaceForm->createView(),
            'networkInterface' => $networkInterface
        ]);
    }
        
    /**
     * @Route("/network-interfaces", name="get_network_interfaces", methods="GET")
     */
    public function cgetAction()
    {
        $repository = $this->getDoctrine()->getRepository('App:NetworkInterface');
            
        $data = $repository->findAll();
            
        return $this->renderJson($data);
    }
        
    /**
     * @Route("/admin/network-interface/{id<\d+>}", name="delete_network_interface", methods="DELETE")
     */
    public function deleteAction($id)
    {
        $status = 200;
        $data = [];
            
        $networkInterface = $this->networkInterfaceRepository->find($id);
            
        if ($networkInterface == null) {
            $status = 404;
        } elseif ($networkInterface->getDevice() !== null && $networkInterface->getDevice()->getInstances()->count() > 0) {
            $status = 403;
            $data['message'] = "This interface is attached to a running device. Please stop all devices instances before you delete it.";
        } else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($networkInterface);
            $em->flush();
                
            $data['message'] = 'Interface has been deleted.';
        }
            
        return $this->renderJson($data, $status);
    }
}
