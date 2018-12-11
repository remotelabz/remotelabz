<?php

namespace App\Controller;

use App\Entity\NetworkSettings;
use App\Form\NetworkSettingsType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class NetworkSettingsController extends AppController
{
    /**
     * @Route("/admin/network-settings", name="network_settings")
     */
    public function indexAction(Request $request)
    {
        $networkSettings = new NetworkSettings();
        $networkSettingsForm = $this->createForm(NetworkSettingsType::class, $networkSettings);
        $networkSettingsForm->handleRequest($request);
        
        if ($networkSettingsForm->isSubmitted() && $networkSettingsForm->isValid()) {
            $networkSettings = $networkSettingsForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($networkSettings);
            $entityManager->flush();
            
            $this->addFlash('success', 'Settings has been created.');
        }
        
        return $this->render('network_settings/index.html.twig', [
            'networkSettingsForm' => $networkSettingsForm->createView(),
        ]);
    }
        
    /**
     * @Route("/network-settings", name="get_network_settings", methods="GET")
     */
    public function cgetAction()
    {
        $repository = $this->getDoctrine()->getRepository('App:NetworkSettings');
            
        $data = $repository->findAll();
            
        return $this->json($data);
    }
        
    /**
     * @Route("/network-settings/{id<\d+>}", name="delete_network_settings", methods="DELETE")
     */
    public function deleteAction($id)
    {
        $repository = $this->getDoctrine()->getRepository('App:NetworkSettings');
            
        $status = 200;
        $data = [];
            
        $networkSettings = $repository->find($id);
            
        if ($networkSettings == null) {
            $status = 404;
        } else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($networkSettings);
            $em->flush();
                
            $data['message'] = 'Settings has been deleted.';
        }
            
        return $this->json($data, $status);
    }
}
