<?php

namespace App\Controller;

use App\Entity\NetworkSettings;
use App\Entity\NetworkInterface;
use App\Form\NetworkInterfaceType;
use FOS\RestBundle\Context\Context;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\NetworkInterfaceRepository;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class NetworkInterfaceController extends Controller
{
    public $networkInterfaceRepository;

    public function __construct(NetworkInterfaceRepository $networkInterfaceRepository)
    {
        $this->networkInterfaceRepository = $networkInterfaceRepository;
    }

    /**
     * @Route("/admin/network-interfaces", name="network_interfaces")
     * 
     * @Rest\Get("/api/network-interfaces", name="api_get_network_interfaces")
     */
    public function indexAction(Request $request)
    {
        if ('json' === $request->getRequestFormat()) {
            $search = $request->query->get('search', '');
            $template = $request->query->get('template', true);

            $criteria = Criteria::create()
                ->where(Criteria::expr()->contains('name', $search))
                ->andWhere(Criteria::expr()->eq('isTemplate', $template))
                ->orderBy([
                    'id' => Criteria::DESC
                ]);

            $networkInterfaces = $this->networkInterfaceRepository->matching($criteria);

            return $this->json($networkInterfaces->getValues(), 200, [], ['api_get_network_interface']);
        }

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
     * @Rest\Get("/api/network-interfaces/{id<\d+>}", name="api_get_network_interface")
     */
    public function showAction(Request $request, int $id)
    {
        if (!$networkInterface = $this->networkInterfaceRepository->find($id))
            throw new NotFoundHttpException("Network interface " . $id . " does not exist.");

        return $this->json($networkInterface, 200, [], [$request->get('_route')]);
    }

    /**
     * @Route("/admin/network-interfaces/new", name="new_network_interface", methods={"GET", "POST"})
     * 
     * @Rest\Post("/api/network-interfaces", name="api_new_network_interface")
     */
    public function newAction(Request $request)
    {
        $networkInterface = new NetworkInterface();
        $networkInterfaceForm = $this->createForm(NetworkInterfaceType::class, $networkInterface);
        $networkInterfaceForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $networkInterface = json_decode($request->getContent(), true);
            $networkInterfaceForm->submit($networkInterface, false);
        }

        if ($networkInterfaceForm->isSubmitted() && $networkInterfaceForm->isValid()) {
            /** @var NetworkInterface $networkInterface */
            $networkInterface = $networkInterfaceForm->getData();
            $networkSettings = new NetworkSettings();
            $networkSettings
                ->setName($networkInterface->getName() . '_settings');
            $networkInterface->setSettings($networkSettings);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($networkInterface);
            $entityManager->flush();

            if ('json' === $request->getRequestFormat()) {
                return $this->json($networkInterface, 201, [], ['api_get_network_interface']);
            }

            $this->addFlash('success', 'Network interface has been created.');

            return $this->redirectToRoute('network_interfaces');
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($networkInterfaceForm, 400, [], ['api_get_network_interface']);
        }

        return $this->render('network_interface/new.html.twig', [
            'networkInterfaceForm' => $networkInterfaceForm->createView()
        ]);
    }

    /**
     * @Route("/admin/network-interfaces/{id<\d+>}/edit", name="edit_network_interface", methods={"GET", "POST"})
     * 
     * @Rest\Put("/api/network-interfaces/{id<\d+>}", name="api_edit_network_interface")
     */
    public function editAction(Request $request, int $id)
    {
        $networkInterface = $this->networkInterfaceRepository->find($id);

        if (null === $networkInterface) {
            throw new NotFoundHttpException("Network interface " . $id . " does not exist.");
        }

        $networkInterfaceForm = $this->createForm(NetworkInterfaceType::class, $networkInterface);
        $networkInterfaceForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $networkInterface = json_decode($request->getContent(), true);
            $networkInterfaceForm->submit($networkInterface, false);
        }

        if ($networkInterfaceForm->isSubmitted() && $networkInterfaceForm->isValid()) {
            /** @var NetworkInterface $networkInterface */
            $networkInterface = $networkInterfaceForm->getData();
            $networkSettings = $networkInterface->getSettings();
            $networkSettings
                ->setName($networkInterface->getName() . '_settings');

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($networkInterface);
            $entityManager->flush();

            if ('json' === $request->getRequestFormat()) {
                return $this->json($networkInterface, 200, [], ['api_get_network_interface']);
            }

            $this->addFlash('success', 'Network interface has been edited.');

            return $this->redirectToRoute('network_interfaces');
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($networkInterfaceForm, 400, [], ['api_get_network_interface']);
        }

        return $this->render('network_interface/new.html.twig', [
            'networkInterfaceForm' => $networkInterfaceForm->createView(),
            'networkInterface' => $networkInterface
        ]);
    }

    /**
     * @Route("/admin/network-interfaces/{id<\d+>}/delete", name="delete_network_interface", methods="GET")
     * 
     * @Rest\Delete("/api/network-interfaces/{id<\d+>}", name="api_delete_network_interface")
     */
    public function deleteAction(Request $request, int $id)
    {
        if (!$networkInterface = $this->networkInterfaceRepository->find($id)) {
            throw new NotFoundHttpException("Network interface " . $id . " does not exist.");
        }

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($networkInterface);
        $entityManager->flush();

        if ('json' === $request->getRequestFormat()) {
            return $this->json();
        }

        $this->addFlash('success', $networkInterface->getName() . ' has been deleted.');

        return $this->redirectToRoute('network_interfaces');
    }
}
