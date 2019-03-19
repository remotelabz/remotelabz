<?php

namespace App\Controller;

use App\Entity\OperatingSystem;
use App\Form\OperatingSystemType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class OperatingSystemController extends AppController
{
    /**
     * @Route("/admin/operating-systems", name="operating_systems")
     */
    public function indexAction(Request $request)
    {
        $operatingSystem = new OperatingSystem();
        $operatingSystemForm = $this->createForm(OperatingSystemType::class, $operatingSystem);
        $operatingSystemForm->handleRequest($request);
        
        if ($operatingSystemForm->isSubmitted() && $operatingSystemForm->isValid()) {
            $operatingSystem = $operatingSystemForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($operatingSystem);
            $entityManager->flush();
            
            $this->addFlash('success', 'Operating system has been created.');
        }
        
        return $this->render('operating_system/index.html.twig', [
            'operatingSystemForm' => $operatingSystemForm->createView(),
        ]);
    }

    /**
     * @Route("/admin/operating-system/{id<\d+>}",
     * name="edit_operating_system", methods={"GET", "POST"})
     */
    public function editAction(Request $request, $id)
    {
        $repository = $this->getDoctrine()->getRepository('App:OperatingSystem');
        
        $operatingSystem = $repository->find($id);
        $operatingSystemForm = $this->createForm(OperatingSystemType::class, $operatingSystem);
        $operatingSystemForm->handleRequest($request);
        
        if ($operatingSystemForm->isSubmitted() && $operatingSystemForm->isValid()) {
            $operatingSystem = $operatingSystemForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($operatingSystem);
            $entityManager->flush();
            
            $this->addFlash('success', 'Operating system has been created.');
        }
        
        return $this->render('operating_system/index.html.twig', [
            'operatingSystemForm' => $operatingSystemForm->createView(),
        ]);
    }
        
    /**
     * @Route("/operating-systems", name="get_operating_systems", methods="GET")
     */
    public function cgetAction()
    {
        $repository = $this->getDoctrine()->getRepository('App:OperatingSystem');
            
        $data = $repository->findAll();
            
        return $this->json($data);
    }
        
    /**
     * @Route("/operating-system/{id<\d+>}", name="delete_operating_system", methods="DELETE")
     */
    public function deleteAction($id)
    {
        $repository = $this->getDoctrine()->getRepository('App:OperatingSystem');
            
        $status = 200;
        $data = [];
            
        $operatingSystem = $repository->find($id);
            
        if ($operatingSystem == null) {
            $status = 404;
        } else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($operatingSystem);
            $em->flush();
                
            $data['message'] = 'Operating system has been deleted.';
        }
            
        return $this->json($data, $status);
    }
}
