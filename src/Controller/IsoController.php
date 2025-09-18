<?php

namespace App\Controller;

use App\Entity\Iso;
use App\Entity\Arch;
use App\Entity\User;

use App\Form\IsoType;
use App\Repository\IsoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Psr\Log\LoggerInterface;

#[IsGranted("ROLE_TEACHER_EDITOR", message: "Access denied.")]
#[Route(path: '/admin/isos', name: 'app_iso_')]
class IsoController extends AbstractController
{

     /** @var LoggerInterface $logger */
    private $logger;
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }   

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(IsoRepository $isoRepository): Response
    {
        return $this->render('iso/index.html.twig', [
            'isos' => $isoRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $iso = new Iso();
        $form = $this->createForm(IsoType::class, $iso);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->logger->info('Uploading file to iso_directory: ' . $this->getParameter('iso_directory')." by user :".$this->getUser()->getName());
            $fileSourceType = $form->get('fileSourceType')->getData();
            
            if ($fileSourceType === 'upload') {
                // Gérer l'upload du fichier
                $uploadedFile = $form->get('uploadedFile')->getData();
                
                if ($uploadedFile) {
                    $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $uploadedFile->guessExtension();

                    try {
                        $uploadedFile->move(
                            $this->getParameter('iso_directory'),
                            $newFilename
                        );
                        $iso->setFilename($newFilename);
                        $iso->setFilenameUrl(null);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Error uploading file');
                        return $this->render('iso/new.html.twig', [
                            'iso' => $iso,
                            'form' => $form,
                        ]);
                    }
                }
            } else {
                // Utiliser l'URL, nettoyer le nom de fichier
                $iso->setFilename(null);
            }

            $entityManager->persist($iso);
            $entityManager->flush();

            $this->addFlash('success', 'ISO created successfully');
            return $this->redirectToRoute('app_iso_index');
        }

        return $this->render('iso/new.html.twig', [
            'iso' => $iso,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Iso $iso): Response
    {
        return $this->render('iso/view.html.twig', [
            'iso' => $iso,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Iso $iso, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(IsoType::class, $iso);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fileSourceType = $form->get('fileSourceType')->getData();
            
            if ($fileSourceType === 'upload') {
                $uploadedFile = $form->get('uploadedFile')->getData();
                
                if ($uploadedFile) {
                    // Supprimer l'ancien fichier si il existe
                    if ($iso->getFilename()) {
                        $oldFile = $this->getParameter('iso_directory') . '/' . $iso->getFilename();
                        if (file_exists($oldFile)) {
                            unlink($oldFile);
                        }
                    }
                    
                    $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $uploadedFile->guessExtension();

                    try {
                        $uploadedFile->move(
                            $this->getParameter('iso_directory'),
                            $newFilename
                        );
                        $iso->setFilename($newFilename);
                        $iso->setFilenameUrl(null);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Erreur lors de l\'upload du fichier');
                    }
                }
            } else {
                // Si on passe à l'URL, supprimer l'ancien fichier
                if ($iso->getFilename()) {
                    $oldFile = $this->getParameter('iso_directory') . '/' . $iso->getFilename();
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }
                $iso->setFilename(null);
            }

            $entityManager->flush();

            $this->addFlash('success', 'ISO modifiée avec succès');
            return $this->redirectToRoute('app_iso_index');
        }

        return $this->render('iso/new.html.twig', [
            'iso' => $iso,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Iso $iso, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$iso->getId(), $request->request->get('_token'))) {
            // Supprimer le fichier physique si il existe
            if ($iso->getFilename()) {
                $file = $this->getParameter('iso_directory') . '/' . $iso->getFilename();
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            
            $entityManager->remove($iso);
            $entityManager->flush();
            
            $this->addFlash('success', 'ISO supprimée avec succès');
        }

        return $this->redirectToRoute('app_iso_index');
    }
}