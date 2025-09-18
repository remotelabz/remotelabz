<?php

namespace App\Controller;

use App\Entity\Arch;
use App\Form\ArchType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ArchController extends AbstractController
{
    #[Route('/admin/arch/new', name: 'arch_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $arch = new Arch();
        $form = $this->createForm(ArchType::class, $arch);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($arch);
            $entityManager->flush();

            $this->addFlash('success', 'Architecture ajoutée !');
            return $this->redirectToRoute('arch_list');
        }

        return $this->render('arch/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/arch', name: 'arch_list')]
    public function list(EntityManagerInterface $entityManager): Response
    {
        $archs = $entityManager->getRepository(Arch::class)->findAll();

        return $this->render('arch/index.html.twig', [
            'archs' => $archs,
        ]);
    }
}