<?php

namespace App\Controller;

use App\Entity\Flavor;
use App\Form\FlavorType;
use App\Repository\FlavorRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class FlavorController extends AppController
{
    public $flavorRepository;

    public function __construct(FlavorRepository $flavorRepository)
    {
        $this->flavorRepository = $flavorRepository;
    }

    /**
     * @Route("/admin/flavors", name="flavors")
     */
    public function indexAction(Request $request)
    {
        return $this->render('flavor/index.html.twig');
    }

    /**
     * @Route("/admin/flavors/new", name="new_flavor", methods={"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $flavor = new Flavor();
        $flavorForm = $this->createForm(FlavorType::class, $flavor);
        $flavorForm->handleRequest($request);

        if ($flavorForm->isSubmitted() && $flavorForm->isValid()) {
            /** @var Flavor $flavor */
            $flavor = $flavorForm->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($flavor);
            $entityManager->flush();

            $this->addFlash('success', 'Flavor has been created.');

            return $this->redirectToRoute('flavors');
        }

        return $this->render('flavor/new.html.twig', [
            'flavorForm' => $flavorForm->createView()
        ]);
    }

    /**
     * @Route("/admin/flavors/{id<\d+>}/edit", name="edit_flavor", methods={"GET", "POST"})
     */
    public function editAction(Request $request, int $id)
    {
        $flavor = $this->flavorRepository->find($id);

        if (null === $flavor) {
            throw new NotFoundHttpException();
        }
        
        $flavorForm = $this->createForm(FlavorType::class, $flavor);
        $flavorForm->handleRequest($request);

        if ($flavorForm->isSubmitted() && $flavorForm->isValid()) {
            /** @var Flavor $flavor */
            $flavor = $flavorForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($flavor);
            $entityManager->flush();

            $this->addFlash('success', 'Flavor has been edited.');

            return $this->redirectToRoute('flavors');
        }

        return $this->render('flavor/new.html.twig', [
            'flavorForm' => $flavorForm->createView(),
            'flavor' => $flavor
        ]);
    }
        
    /**
     * @Route("/flavors", name="get_flavors", methods="GET")
     */
    public function cgetAction()
    {
        return $this->renderJson($this->flavorRepository->findAll());
    }
        
    /**
     * @Route("/admin/flavors/{id<\d+>}", name="delete_flavor", methods="DELETE")
     */
    public function deleteAction($id)
    {
        $status = 200;
        $data = [];
            
        $flavor = $this->flavorRepository->find($id);
            
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
