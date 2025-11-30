<?php

namespace App\Controller;

use App\Entity\Arch;
use App\Entity\OperatingSystem;
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

    #[Route('/admin/arch/delete', name: 'arch_delete')]
    public function delete(Request $request, EntityManagerInterface $entityManager): Response
    {
        $id = $request->query->get('id');
        $arch = $entityManager->getRepository(Arch::class)->find($id);

        if ($arch) {

	    $operatingSystems = $entityManager->getRepository(OperatingSystem::class)->findBy(["arch"=>$arch]);

	    if (count($operatingSystems) > 0) {
                if ('json' === $request->getRequestFormat()) {
                    return $this->json([
                        'error' => 'Cannot delete arch because it is used by at least ' . count($operatingSystems) . ' operating system(s).'
                    ], 409); // 409 Conflict
                }

                $this->addFlash('danger', 'Cannot delete arch because it is used by at least ' . count($operatingSystems) . ' operating system(s) (OS Name:'.$operatingSystems[0]->getName().').');
                return $this->redirectToRoute('arch_list');
            }

            $entityManager->remove($arch);
            $entityManager->flush();
            $this->addFlash('success', 'Architecture supprimée !');
        } else {
            $this->addFlash('danger', 'Architecture non trouvée.');
        }

        return $this->redirectToRoute('arch_list');
    }

    

    #[Route('/admin/arch/edit', name: 'arch_edit')]
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        $id = $request->query->get('id');
        $arch = $entityManager->getRepository(Arch::class)->find($id);

        if (!$arch) {
            $this->addFlash('danger', 'Architecture non trouvée.');
            return $this->redirectToRoute('arch_list');
        }

        $form = $this->createForm(ArchType::class, $arch);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Architecture mise à jour !');
            return $this->redirectToRoute('arch_list');
        }

        return $this->render('arch/view.html.twig', [
            'form' => $form->createView(),
            'arch' => $arch,
        ]);
    }
    
}
