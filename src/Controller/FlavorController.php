<?php

namespace App\Controller;

use App\Entity\Flavor;
use App\Form\FlavorType;
use App\Repository\FlavorRepository;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

class FlavorController extends Controller
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
     * @IsGranted("ROLE_ADMINISTRATOR", message="Access denied.") 
     */
    public function indexAction(Request $request)
    {
        $search = $request->query->get('search', '');

        $criteria = Criteria::create()
            ->where(Criteria::expr()->contains('name', $search))
            ->orderBy([
                'id' => Criteria::DESC
            ]);

        $flavors = $this->flavorRepository->matching($criteria)->getValues();

        if ('json' === $request->getRequestFormat()) {
            return $this->json($flavors, 200, [], ['api_get_flavor']);
        }

        return $this->render('flavor/index.html.twig', [
            'flavors' => $flavors,
            'search' => $search
        ]);
    }

    /**
     * @Rest\Get("/api/flavors/{id<\d+>}", name="api_get_flavor")
     * 
     * @IsGranted("ROLE_ADMINISTRATOR", message="Access denied.") 
     */
    public function showAction(Request $request, int $id)
    {
        if (!$flavor = $this->flavorRepository->find($id)) {
            throw new NotFoundHttpException("Flavor " . $id . " does not exist.");
        }

        return $this->json($flavor, 200, [], [$request->get("_route")]);
    }

    /**
     * @Route("/admin/flavors/new", name="new_flavor", methods={"GET", "POST"})
     * 
     * @Rest\Post("/api/flavors", name="api_new_flavor")
     * 
     * @IsGranted("ROLE_ADMINISTRATOR", message="Access denied.") 
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

        if ($flavorForm->isSubmitted() && $flavorForm->isValid()) {
            /** @var Flavor $flavor */
            $flavor = $flavorForm->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($flavor);
            $entityManager->flush();

            if ('json' === $request->getRequestFormat()) {
                return $this->json($flavor, 201, [], ['api_get_flavor']);
            }

            $this->addFlash('success', 'Flavor has been created.');

            return $this->redirectToRoute('flavors');
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($flavorForm, 200, [], ['api_get_flavor']);
        }

        return $this->render('flavor/new.html.twig', [
            'form' => $flavorForm->createView()
        ]);
    }

    /**
     * @Route("/admin/flavors/{id<\d+>}/edit", name="edit_flavor")
     * 
     * @Rest\Put("/api/flavors/{id<\d+>}", name="api_edit_flavor")
     * 
     * @IsGranted("ROLE_ADMINISTRATOR", message="Access denied.") 
     */
    public function editAction(Request $request, int $id)
    {
        if (!$flavor = $this->flavorRepository->find($id)) {
            throw new NotFoundHttpException("Flavor " . $id . " does not exist.");
        }

        $flavorForm = $this->createForm(FlavorType::class, $flavor);
        $flavorForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $flavor = json_decode($request->getContent(), true);
            $flavorForm->submit($flavor, false);
        }

        if ($flavorForm->isSubmitted() && $flavorForm->isValid()) {
            /** @var Flavor $flavor */
            $flavor = $flavorForm->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($flavor);
            $entityManager->flush();

            if ('json' === $request->getRequestFormat()) {
                return $this->json($flavor, 200, [], ['api_get_flavor']);
            }

            $this->addFlash('success', 'Flavor has been edited.');

            return $this->redirectToRoute('flavors');
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($flavorForm, 200, [], ['api_get_flavor']);
        }

        return $this->render('flavor/new.html.twig', [
            'form' => $flavorForm->createView(),
            'flavor' => $flavor
        ]);
    }

    /**
     * @Route("/admin/flavors/{id<\d+>}", name="delete_flavor", methods="DELETE")
     * 
     * @Rest\Delete("/api/flavors/{id<\d+>}", name="api_delete_flavor")
     * 
     * @IsGranted("ROLE_ADMINISTRATOR", message="Access denied.") 
     */
    public function deleteAction(Request $request, int $id)
    {
        if (!$flavor = $this->flavorRepository->find($id)) {
            throw new NotFoundHttpException("Flavor " . $id . " does not exist.");
        }

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($flavor);
        $entityManager->flush();

        if ('json' === $request->getRequestFormat()) {
            return $this->json();
        }

        return $this->redirectToRoute('flavors');
    }
}
