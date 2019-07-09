<?php

namespace App\Controller;

use App\Entity\Hypervisor;
use App\Form\HypervisorType;
use App\Controller\AppController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HypervisorController extends AppController
{
    /**
     * @Route("/admin/hypervisors", name="hypervisors")
     */
    public function indexAction(Request $request)
    {
        $hypervisor = new Hypervisor();
        $hypervisorForm = $this->createForm(HypervisorType::class, $hypervisor);
        $hypervisorForm->handleRequest($request);
        
        if ($hypervisorForm->isSubmitted() && $hypervisorForm->isValid()) {
            $hypervisor = $hypervisorForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($hypervisor);
            $entityManager->flush();
            
            $this->addFlash('success', 'Hypervisor has been created.');
        }
        
        return $this->render('hypervisor/index.html.twig', [
            'hypervisorForm' => $hypervisorForm->createView(),
        ]);
    }
        
    /**
     * @Route("/hypervisors", name="get_hypervisors", methods="GET")
     */
    public function cgetAction()
    {
        $repository = $this->getDoctrine()->getRepository('App:Hypervisor');
            
        $data = $repository->findAll();
            
        return $this->renderJson($data);
    }
        
    /**
     * @Route("/hypervisors/{id<\d+>}", name="delete_hypervisor", methods="DELETE")
     */
    public function deleteAction($id)
    {
        $repository = $this->getDoctrine()->getRepository('App:Hypervisor');
            
        $status = 200;
        $data = [];
            
        $hypervisor = $repository->find($id);
            
        if ($hypervisor == null) {
            $status = 404;
        } else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($hypervisor);
            $em->flush();
                
            $data['message'] = 'Hypervisor has been deleted.';
        }
            
        return $this->renderJson($data, $status);
    }
}
