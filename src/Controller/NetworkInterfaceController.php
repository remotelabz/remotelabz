<?php

namespace App\Controller;

use App\Entity\NetworkInterface;
use App\Form\NetworkInterfaceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class NetworkInterfaceController extends AppController
{
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
     * @Route("/admin/network-interface/{id<\d+>}",
     * name="edit_network_interface", methods={"GET", "POST"})
     */
    public function editAction(Request $request, $id)
    {
        $repository = $this->getDoctrine()->getRepository('App:NetworkInterface');
        
        $networkInterface = $repository->find($id);
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
     * @Route("/network-interfaces", name="get_network_interfaces", methods="GET")
     */
    public function cgetAction()
    {
        $repository = $this->getDoctrine()->getRepository('App:NetworkInterface');
            
        $data = $repository->findAll();
            
        return $this->renderJson($data);
    }
        
    /**
     * @Route("/network-interface/{id<\d+>}", name="delete_network_interface", methods="DELETE")
     */
    public function deleteAction($id)
    {
        $repository = $this->getDoctrine()->getRepository('App:NetworkInterface');
            
        $status = 200;
        $data = [];
            
        $networkInterface = $repository->find($id);
            
        if ($networkInterface == null) {
            $status = 404;
        } else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($networkInterface);
            $em->flush();
                
            $data['message'] = 'Interface has been deleted.';
        }
            
        return $this->renderJson($data, $status);
    }
}
