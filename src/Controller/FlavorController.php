<?php

namespace App\Controller;

use App\Entity\Flavor;
use App\Form\FlavorType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class FlavorController extends AppController
{
    /**
     * @Route("/admin/flavors", name="flavors")
     */
    public function indexAction(Request $request)
    {
        $flavor = new Flavor();
        $flavorForm = $this->createForm(FlavorType::class, $flavor);
        $flavorForm->handleRequest($request);
        
        if ($flavorForm->isSubmitted() && $flavorForm->isValid()) {
            $flavor = $flavorForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($flavor);
            $entityManager->flush();
            
            $this->addFlash('success', 'Flavor has been created.');
        }
        
        return $this->render('flavor/index.html.twig', [
            'flavorForm' => $flavorForm->createView(),
        ]);
    }
        
    /**
     * @Route("/flavors", name="get_flavors", methods="GET")
     */
    public function cgetAction()
    {
        $repository = $this->getDoctrine()->getRepository('App:Flavor');
            
        $data = $repository->findAll();
            
        return $this->renderJson($data);
    }
        
    /**
     * @Route("/flavors/{id<\d+>}", name="delete_flavor", methods="DELETE")
     */
    public function deleteAction($id)
    {
        $repository = $this->getDoctrine()->getRepository('App:Flavor');
            
        $status = 200;
        $data = [];
            
        $flavor = $repository->find($id);
            
        if ($flavor == null) {
            $status = 404;
        } else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($flavor);
            $em->flush();
                
            $data['message'] = 'Flavor has been deleted.';
        }
            
        return $this->renderJson($data, $status);
    }
}
