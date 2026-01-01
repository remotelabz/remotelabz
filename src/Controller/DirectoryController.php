<?php

namespace App\Controller;

use App\Entity\Directory;
use App\Entity\Device;
use App\Entity\Iso;
use App\Entity\OperatingSystem;
use App\Repository\DirectoryRepository;
use App\Repository\DeviceRepository;
use App\Repository\IsoRepository;
use App\Repository\OperatingSystemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class DirectoryController extends Controller
{
    /**
     * Get all root directories
     * 
     * @Route("/api/directories/roots", name="api_directories_roots", methods={"GET"})
     */
    public function getRootDirectories(DirectoryRepository $directoryRepo): JsonResponse
    {
        $roots = $directoryRepo->findRoots();
        
        // Retourner la réponse JSON (utilisez votre serializer habituel)
        return $this->json($roots, Response::HTTP_OK, [], ['groups' => ['api_directory']]);
    }

    /**
     * Get directory contents (children directories and items)
     * 
     * @Route("/api/directories/{id}/contents", name="api_directory_contents", methods={"GET"})
     */
    public function getDirectoryContents(
        int $id,
        DirectoryRepository $directoryRepo
    ): JsonResponse {
        // Utiliser findWithContents pour éviter les requêtes N+1
        $directory = $directoryRepo->findWithContents($id);
        
        if (!$directory) {
            return $this->json(['error' => 'Directory not found'], Response::HTTP_NOT_FOUND);
        }
        
        $contents = [
            'directory' => $directory,
            'children' => $directory->getChildren()->toArray(),
            'devices' => $directory->getDevices()->toArray(),
            'isos' => $directory->getIsos()->toArray(),
            'operatingSystems' => $directory->getOperatingSystems()->toArray(),
            'stats' => [
                'devicesCount' => $directory->getDevices()->count(),
                'isosCount' => $directory->getIsos()->count(),
                'osCount' => $directory->getOperatingSystems()->count(),
                'childrenCount' => $directory->getChildren()->count(),
                'totalItems' => $directory->getTotalItemsCount()
            ]
        ];
        
        return $this->json($contents, Response::HTTP_OK, [], [
            'groups' => ['api_directory', 'api_get_device', 'worker', 'sandbox']
        ]);
    }

    /**
     * Create a new directory
     * 
     * @Route("/api/directories", name="api_directory_create", methods={"POST"})
     */
    public function createDirectory(
        Request $request,
        EntityManagerInterface $em,
        DirectoryRepository $directoryRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        $directory = new Directory();
        $directory->setName($data['name'] ?? 'New Directory');
        $directory->setDescription($data['description'] ?? null);
        
        // Set parent if provided
        if (!empty($data['parent_id'])) {
            $parent = $directoryRepo->find($data['parent_id']);
            if ($parent) {
                $directory->setParent($parent);
            }
        }
        
        $em->persist($directory);
        $em->flush();
        
        return $this->json($directory, Response::HTTP_CREATED, [], ['groups' => ['api_directory']]);
    }

    /**
     * Move directory to another parent
     * 
     * @Route("/api/directories/{id}/move", name="api_directory_move", methods={"PUT"})
     */
    public function moveDirectory(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        DirectoryRepository $directoryRepo
    ): JsonResponse {
        $directory = $directoryRepo->find($id);
        
        if (!$directory) {
            return $this->json(['error' => 'Directory not found'], Response::HTTP_NOT_FOUND);
        }
        
        $data = json_decode($request->getContent(), true);
        $newParentId = $data['parent_id'] ?? null;
        
        if ($newParentId) {
            $newParent = $directoryRepo->find($newParentId);
            
            if (!$newParent) {
                return $this->json(['error' => 'Parent directory not found'], Response::HTTP_NOT_FOUND);
            }
            
            // Check for circular reference
            if ($this->wouldCreateCircularReference($directory, $newParent)) {
                return $this->json(
                    ['error' => 'Cannot move directory: would create circular reference'],
                    Response::HTTP_BAD_REQUEST
                );
            }
            
            $directory->setParent($newParent);
        } else {
            // Move to root
            $directory->setParent(null);
        }
        
        $em->flush();
        
        return $this->json($directory, Response::HTTP_OK, [], ['groups' => ['api_directory']]);
    }

    /**
     * Check if moving would create a circular reference
     */
    private function wouldCreateCircularReference(Directory $directory, Directory $newParent): bool
    {
        $current = $newParent;
        
        while ($current !== null) {
            if ($current->getId() === $directory->getId()) {
                return true;
            }
            $current = $current->getParent();
        }
        
        return false;
    }

    /**
     * Get directory tree structure
     * 
     * @Route("/api/directories/tree", name="api_directory_tree", methods={"GET"})
     */
    public function getDirectoryTree(
        Request $request,
        DirectoryRepository $directoryRepo
    ): JsonResponse {
        $maxDepth = (int) ($request->query->get('max_depth', -1));
        $parentId = $request->query->get('parent_id');
        
        $parent = null;
        if ($parentId) {
            $parent = $directoryRepo->find($parentId);
        }
        
        $tree = $directoryRepo->getTreeStructure($parent, $maxDepth);
        
        return $this->json($tree, Response::HTTP_OK, [], ['groups' => ['api_directory']]);
    }

    /**
     * Delete directory (soft delete)
     * 
     * @Route("/api/directories/{id}", name="api_directory_delete", methods={"DELETE"})
     */
    public function deleteDirectory(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        DirectoryRepository $directoryRepo
    ): JsonResponse {
        $directory = $directoryRepo->find($id);
        
        if (!$directory) {
            return $this->json(['error' => 'Directory not found'], Response::HTTP_NOT_FOUND);
        }
        
        $force = $request->query->get('force', false);
        
        if (!$directory->isEmpty() && !$force) {
            return $this->json([
                'error' => 'Directory is not empty. Use ?force=true to delete anyway.',
                'stats' => [
                    'devices' => $directory->getDevices()->count(),
                    'isos' => $directory->getIsos()->count(),
                    'operatingSystems' => $directory->getOperatingSystems()->count(),
                    'children' => $directory->getChildren()->count()
                ]
            ], Response::HTTP_BAD_REQUEST);
        }
        
        // Soft delete
        $directory->delete();
        $em->flush();
        
        return $this->json(['message' => 'Directory deleted successfully'], Response::HTTP_OK);
    }

    /**
     * Search directories
     * 
     * @Route("/api/directories/search", name="api_directory_search", methods={"GET"})
     */
    public function searchDirectories(
        Request $request,
        DirectoryRepository $directoryRepo
    ): JsonResponse {
        $query = $request->query->get('q', '');
        
        if (empty($query)) {
            return $this->json(['error' => 'Search query required'], Response::HTTP_BAD_REQUEST);
        }
        
        $results = $directoryRepo->searchByName($query);
        
        return $this->json($results, Response::HTTP_OK, [], ['groups' => ['api_directory']]);
    }




    // ========================================
    // MÉTHODES UTILITAIRES
    // ========================================

    /**
     * Get statistics about directories
     * 
     * @Route("/api/directories/statistics", name="api_directory_statistics", methods={"GET"})
     */
    public function getDirectoryStatistics(DirectoryRepository $directoryRepo): JsonResponse
    {
        $stats = $directoryRepo->getStatistics();
        
        return $this->json($stats, Response::HTTP_OK);
    }

    /**
     * Batch move items to directory
     * 
     * @Route("/api/directories/{id}/batch-move", name="api_directory_batch_move", methods={"POST"})
     */
    public function batchMoveToDirectory(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        DirectoryRepository $directoryRepo,
        DeviceRepository $deviceRepo,
        IsoRepository $isoRepo,
        OperatingSystemRepository $osRepo
    ): JsonResponse {
        $directory = $directoryRepo->find($id);
        
        if (!$directory) {
            return $this->json(['error' => 'Directory not found'], Response::HTTP_NOT_FOUND);
        }
        
        $data = json_decode($request->getContent(), true);
        $moved = ['devices' => 0, 'isos' => 0, 'operatingSystems' => 0];
        
        // Move devices
        if (!empty($data['device_ids'])) {
            foreach ($data['device_ids'] as $deviceId) {
                $device = $deviceRepo->find($deviceId);
                if ($device) {
                    $device->setDirectory($directory);
                    $moved['devices']++;
                }
            }
        }
        
        // Move ISOs
        if (!empty($data['iso_ids'])) {
            foreach ($data['iso_ids'] as $isoId) {
                $iso = $isoRepo->find($isoId);
                if ($iso) {
                    $iso->setDirectory($directory);
                    $moved['isos']++;
                }
            }
        }
        
        // Move Operating Systems
        if (!empty($data['os_ids'])) {
            foreach ($data['os_ids'] as $osId) {
                $os = $osRepo->find($osId);
                if ($os) {
                    $os->setDirectory($directory);
                    $moved['operatingSystems']++;
                }
            }
        }
        
        $em->flush();
        
        return $this->json([
            'message' => 'Items moved successfully',
            'moved' => $moved,
            'directory' => $directory
        ], Response::HTTP_OK, [], ['groups' => ['api_directory']]);
    }
}