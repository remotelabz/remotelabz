<?php

namespace App\Controller;

use App\Entity\Directory;
use App\Repository\DirectoryRepository;
use App\Service\DirectoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted("ROLE_TEACHER_EDITOR", message: "Access denied.")]
class AdminDirectoryController extends AbstractController
{
    #[Route('/admin/directories', name: 'admin_directories', methods: ['GET'])]
    public function index(DirectoryRepository $directoryRepository, DirectoryService $directoryService): Response
    {
        $counts = $directoryRepository->findAllActiveCounts();
        $rows = [];

        foreach ($directoryRepository->findActiveOrdered() as $directory) {
            $countsForDirectory = $counts[$directory->getId()] ?? [
                'devices' => 0,
                'isos' => 0,
                'operatingSystems' => 0,
                'children' => 0,
            ];
            $rows[] = [
                'directory' => $directory,
                'counts' => $countsForDirectory,
            ];
        }

        $deleted = $directoryRepository->findDeleted();

        return $this->render('directory/index.html.twig', [
            'rows' => $rows,
            'directoryOptions' => $directoryService->getSelectOptions(),
            'deletedDirectories' => $deleted,
            'statistics' => $directoryRepository->getStatistics(),
        ]);
    }

    #[Route('/admin/directories/create', name: 'admin_directory_create', methods: ['POST'])]
    public function create(Request $request, DirectoryService $directoryService): Response
    {
        if (!$this->isCsrfTokenValid('directory_admin', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token');
            return $this->redirectToRoute('admin_directories');
        }

        $name = $request->request->get('name', '');
        $parentId = $request->request->get('parent_id');
        $returnTo = $directoryService->getReturnTo($request, $this->generateUrl('admin_directories'));

        try {
            $directoryService->create(
                $name,
                $parentId ? (int) $parentId : null,
                $request->request->get('description')
            );
            $this->addFlash('success', sprintf('Directory "%s" created', trim($name)));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirect($returnTo);
    }

    #[Route('/admin/directories/{id<\d+>}/rename', name: 'admin_directory_rename', methods: ['POST'])]
    public function rename(int $id, Request $request, DirectoryRepository $directoryRepository, DirectoryService $directoryService): Response
    {
        if (!$this->isCsrfTokenValid('directory_admin', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token');
            return $this->redirectToRoute('admin_directories');
        }

        $directory = $directoryRepository->find($id);
        if (!$directory || $directory->isDeleted()) {
            $this->addFlash('danger', 'Directory not found');
            return $this->redirectToRoute('admin_directories');
        }

        $returnTo = $directoryService->getReturnTo($request, $this->generateUrl('admin_directories'));

        try {
            $directoryService->rename($directory, $request->request->get('name', ''));
            $this->addFlash('success', 'Directory renamed');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirect($returnTo);
    }

    #[Route('/admin/directories/{id<\d+>}/move', name: 'admin_directory_move', methods: ['POST'])]
    public function move(int $id, Request $request, DirectoryRepository $directoryRepository, DirectoryService $directoryService): Response
    {
        if (!$this->isCsrfTokenValid('directory_admin', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token');
            return $this->redirectToRoute('admin_directories');
        }

        $directory = $directoryRepository->find($id);
        if (!$directory || $directory->isDeleted()) {
            $this->addFlash('danger', 'Directory not found');
            return $this->redirectToRoute('admin_directories');
        }

        $returnTo = $directoryService->getReturnTo($request, $this->generateUrl('admin_directories'));
        $newParentId = $request->request->get('parent_id');
        $newParent = $newParentId ? $directoryRepository->find((int) $newParentId) : null;

        try {
            $directoryService->move($directory, $newParent);
            $this->addFlash('success', 'Directory moved');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirect($returnTo);
    }

    #[Route('/admin/directories/{id<\d+>}/delete', name: 'admin_directory_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, DirectoryRepository $directoryRepository, DirectoryService $directoryService): Response
    {
        if (!$this->isCsrfTokenValid('directory_admin', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token');
            return $this->redirectToRoute('admin_directories');
        }

        $directory = $directoryRepository->find($id);
        if (!$directory || $directory->isDeleted()) {
            $this->addFlash('danger', 'Directory not found');
            return $this->redirectToRoute('admin_directories');
        }

        $returnTo = $directoryService->getReturnTo($request, $this->generateUrl('admin_directories'));
        $force = filter_var($request->request->get('force'), FILTER_VALIDATE_BOOL);

        try {
            $directoryService->softDelete($directory, $force);
            $this->addFlash('success', sprintf('Directory "%s" deleted', $directory->getName()));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirect($returnTo);
    }

    #[Route('/admin/directories/{id<\d+>}/restore', name: 'admin_directory_restore', methods: ['POST'])]
    public function restore(int $id, Request $request, DirectoryRepository $directoryRepository, DirectoryService $directoryService): Response
    {
        if (!$this->isCsrfTokenValid('directory_admin', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid security token');
            return $this->redirectToRoute('admin_directories');
        }

        $directory = $directoryRepository->find($id);
        if (!$directory) {
            $this->addFlash('danger', 'Directory not found');
            return $this->redirectToRoute('admin_directories');
        }

        $directoryService->restore($directory);
        $this->addFlash('success', sprintf('Directory "%s" restored', $directory->getName()));

        return $this->redirect($this->generateUrl('admin_directories'));
    }
}
