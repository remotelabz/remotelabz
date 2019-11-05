<?php

namespace App\Controller;

use App\Entity\Flavor;
use App\Form\FlavorType;
use Swagger\Annotations as SWG;
use App\Repository\FlavorRepository;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\AbstractFOSRestController;

class FlavorController extends AbstractFOSRestController
{
    public $flavorRepository;

    public function __construct(FlavorRepository $flavorRepository)
    {
        $this->flavorRepository = $flavorRepository;
    }

    /**
     * @Route("/admin/flavors", name="flavors")
     * 
     * @Rest\Get("/api/flavors", name="api_flavors")
     * 
     * @SWG\Response(
     *     response=200,
     *     description="Returns all existing flavors",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(ref=@Model(type=Flavor::class))
     *     )
     * )
     * 
     * @SWG\Tag(name="Flavor")
     */
    public function indexAction(Request $request)
    {
        $flavors = $this->flavorRepository->findAll();

        $view = $this->view($flavors, 200)
            ->setTemplate("flavor/index.html.twig")
            ->setTemplateData([
                'flavors' => $flavors
            ])
        ;

        return $this->handleView($view);
    }

    /**
     * @Route("/admin/flavors/new", name="new_flavor", methods={"GET", "POST"})
     * 
     * @Rest\Post("/api/flavors", name="api_new_flavor")
     * 
     * @SWG\Parameter(
     *     name="flavor",
     *     in="body",
     *     @SWG\Schema(ref=@Model(type=Flavor::class, groups={"api"})),
     *     description="Flavor data."
     * )
     * 
     * @SWG\Response(
     *     response=201,
     *     description="Returns the newly created flavor.",
     *     @SWG\Schema(ref=@Model(type=Flavor::class))
     * )
     * 
     * @SWG\Tag(name="Flavor")
     */
    public function newAction(Request $request)
    {
        $flavor = new Flavor();
        $flavorForm = $this->createForm(FlavorType::class, $flavor);
        $flavorForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $flavor = json_decode($request->getContent(), true);
            $flavorForm->submit($flavor);
        } 

        $view = $this->view($flavorForm)
            ->setTemplate("flavor/new.html.twig")
        ;

        if ($flavorForm->isSubmitted() && $flavorForm->isValid()) {
            /** @var Flavor $flavor */
            $flavor = $flavorForm->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($flavor);
            $entityManager->flush();

            $this->addFlash('success', 'Flavor has been created.');

            $view->setLocation($this->generateUrl('flavors'));
            $view->setStatusCode(201);
            $view->setData($flavor);
        }

        return $this->handleView($view);
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
     * @Route("/admin/flavors/{id<\d+>}", name="delete_flavor", methods="DELETE")
     * 
     * @Rest\Delete("/api/flavors/{id<\d+>}", name="api_delete_flavor")
     * 
     * @SWG\Parameter(
     *     name="id",
     *     in="path",
     *     type="integer",
     *     description="ID of the flavor."
     * )
     * 
     * @SWG\Response(
     *     response=200,
     *     description="Returns when the flavor is deleted."
     * )
     * 
     * @SWG\Tag(name="Flavor")
     */
    public function deleteAction(int $id)
    {
        $view = $this->view(null, 200);
            
        $flavor = $this->flavorRepository->find($id);
            
        if (!$flavor) {
            $view->setStatusCode(404);
        } else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($flavor);
            $em->flush();
        }
        
        return $this->handleView($view);
    }
}
