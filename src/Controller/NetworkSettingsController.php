<?php

namespace App\Controller;

use App\Entity\NetworkSettings;
use App\Form\NetworkSettingsType;
use App\Repository\NetworkSettingsRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\ORM\EntityManagerInterface;

class NetworkSettingsController extends Controller
{
    public $networkSettingsRepository;

    public function __construct(NetworkSettingsRepository $networkSettingsRepository, EntityManagerInterface $entityManager)
    {
        $this->networkSettingsRepository = $networkSettingsRepository;
        $this->entityManager = $entityManager;
    }
    
    #[Route(path: '/admin/network-settings', name: 'network_settings')]
    public function indexAction(Request $request)
    {
        return $this->render('network_settings/index.html.twig');
    }

    #[Route(path: '/admin/network-settings/new', name: 'new_network_settings', methods: ['GET', 'POST'])]
    public function newAction(Request $request)
    {
        $networkSettings = new NetworkSettings();
        $networkSettingsForm = $this->createForm(NetworkSettingsType::class, $networkSettings);
        $networkSettingsForm->handleRequest($request);

        if ($networkSettingsForm->isSubmitted() && $networkSettingsForm->isValid()) {
            /** @var NetworkSettings $networkSettings */
            $networkSettings = $networkSettingsForm->getData();

            $entityManager = $this->entityManager;
            $entityManager->persist($networkSettings);
            $entityManager->flush();

            $this->addFlash('success', 'Network settings has been created.');

            return $this->redirectToRoute('network_settings');
        }

        return $this->render('network_settings/new.html.twig', [
            'networkSettingsForm' => $networkSettingsForm->createView()
        ]);
    }

    #[Route(path: '/admin/network-settings/{id<\d+>}/edit', name: 'edit_network_settings', methods: ['GET', 'POST'])]
    public function editAction(Request $request, int $id)
    {
        $networkSettings = $this->networkSettingsRepository->find($id);

        if (null === $networkSettings) {
            throw new NotFoundHttpException();
        }
        
        $networkSettingsForm = $this->createForm(NetworkSettingsType::class, $networkSettings);
        $networkSettingsForm->handleRequest($request);

        if ($networkSettingsForm->isSubmitted() && $networkSettingsForm->isValid()) {
            /** @var NetworkSettings $networkSettings */
            $networkSettings = $networkSettingsForm->getData();
            
            $entityManager = $this->entityManager;
            $entityManager->persist($networkSettings);
            $entityManager->flush();

            $this->addFlash('success', 'Network settings has been edited.');

            return $this->redirectToRoute('network_settings');
        }

        return $this->render('network_settings/new.html.twig', [
            'networkSettingsForm' => $networkSettingsForm->createView(),
            'networkSettings' => $networkSettings
        ]);
    }
        
    #[Route(path: '/network-settings', name: 'get_network_settings', methods: 'GET')]
    public function cgetAction()
    {
        return $this->json($this->networkSettingsRepository->findAll());
    }
        
    #[Route(path: '/admin/network-settings/{id<\d+>}', name: 'delete_network_settings', methods: 'DELETE')]
    public function deleteAction($id)
    {
        $status = 200;
        $data = [];
            
        $networkSettings = $this->networkSettingsRepository->find($id);
            
        if ($networkSettings == null) {
            $status = 404;
        } else {
            $em = $this->entityManager;
            $em->remove($networkSettings);
            $em->flush();
                
            $data['message'] = 'Settings has been deleted.';
        }

        return $this->json($data, $status);
    }
}
