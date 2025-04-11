<?php

namespace App\Controller;

use App\Entity\Flavor;
use App\Form\FlavorType;
use App\Repository\FlavorRepository;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;

class FlavorController extends Controller
{
    public $flavorRepository;

    public function __construct(FlavorRepository $flavorRepository, EntityManagerInterface $entityManager)
    {
        $this->flavorRepository = $flavorRepository;
        $this->entityManager = $entityManager;
    }

    
	#[Get('/api/flavors', name: 'api_flavors')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    #[Route(path: '/admin/flavors', name: 'flavors')]
    public function indexAction(Request $request)
    {
        $search = $request->query->get('search', '');

        $criteria = Criteria::create()
            ->where(Criteria::expr()->contains('name', $search))
            ->orderBy([
                'name' => Criteria::ASC
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

    
	#[Get('/api/flavors/{id<\d+>}', name: 'api_get_flavor')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    public function showAction(Request $request, int $id)
    {
        if (!$flavor = $this->flavorRepository->find($id)) {
            throw new NotFoundHttpException("Flavor " . $id . " does not exist.");
        }

        return $this->json($flavor, 200, [], [$request->get("_route")]);
    }

    
	#[Post('/api/flavors', name: 'api_new_flavor')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    #[Route(path: '/admin/flavors/new', name: 'new_flavor', methods: ['GET', 'POST'])]
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

            $entityManager = $this->entityManager;
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

    
	#[Put('/api/flavors/{id<\d+>}', name: 'api_edit_flavor')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    #[Route(path: '/admin/flavors/{id<\d+>}/edit', name: 'edit_flavor')]
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

            $entityManager = $this->entityManager;
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

    
	#[Delete('/api/flavors/{id<\d+>}', name: 'api_delete_flavor')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    #[Route(path: '/admin/flavors/{id<\d+>}', name: 'delete_flavor', methods: 'DELETE')]
    public function deleteAction(Request $request, int $id)
    {
        if (!$flavor = $this->flavorRepository->find($id)) {
            throw new NotFoundHttpException("Flavor " . $id . " does not exist.");
        }

        $entityManager = $this->entityManager;
        $entityManager->remove($flavor);
        $entityManager->flush();

        if ('json' === $request->getRequestFormat()) {
            return $this->json();
        }

        return $this->redirectToRoute('flavors');
    }
}
