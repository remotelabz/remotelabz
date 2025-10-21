<?php

namespace App\Controller;

use App\Entity\FlavorDisk;
use App\Form\FlavorDiskType;
use App\Repository\FlavorDiskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/flavor-disk')]
class FlavorDiskController extends AbstractController
{
    #[Route('/', name: 'flavor_disk_index', methods: ['GET'])]
    public function index(FlavorDiskRepository $repository): Response
    {
        $flavorDisks = $repository->findAll();

        return $this->render('flavor_disk/index.html.twig', [
            'flavorDisks' => $flavorDisks,
        ]);
    }

    #[Route('/new', name: 'flavor_disk_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $flavorDisk = new FlavorDisk();
        $form = $this->createForm(FlavorDiskType::class, $flavorDisk);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($flavorDisk);
            $entityManager->flush();

            $this->addFlash('success', 'Disk Flavor created successfully!');

            return $this->redirectToRoute('flavor_disk_index');
        }

        return $this->render('flavor_disk/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'flavor_disk_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, FlavorDisk $flavorDisk, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FlavorDiskType::class, $flavorDisk);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Disk Flavor updated successfully!');

            return $this->redirectToRoute('flavor_disk_index');
        }

        return $this->render('flavor_disk/edit.html.twig', [
            'flavorDisk' => $flavorDisk,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'flavor_disk_delete', methods: ['POST', 'DELETE'])]
    public function delete(Request $request, FlavorDisk $flavorDisk, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$flavorDisk->getId(), $request->request->get('_token'))) {
            $entityManager->remove($flavorDisk);
            $entityManager->flush();

            $this->addFlash('success', 'Disk Flavor deleted successfully!');
        }

        return $this->redirectToRoute('flavor_disk_index');
    }
}