<?php

namespace App\Controller;

use App\Entity\OperatingSystem;
use Psr\Log\LoggerInterface;
use App\Form\OperatingSystemType;
use App\Service\ImageFileUploader;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\Filesystem\Filesystem;
use App\Repository\OperatingSystemRepository;
use Symfony\Component\HttpFoundation\Request;
//use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Attribute\Route;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Remotelabz\Message\Message\InstanceActionMessage;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\ConfigWorkerRepository;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted("ROLE_TEACHER_EDITOR", message: "Access denied.")]
class OperatingSystemController extends Controller
{
    /**
     * @var OperatingSystemRepository
     */
    private $workerRepository;
    private $operatingSystemRepository;
    private $logger;
    private $serializer;
    protected $bus;
    private $configWorkerRepository;
    private $entityManager;
    private $paginator;

    public function __construct(LoggerInterface $logger,
        OperatingSystemRepository $operatingSystemRepository,
        SerializerInterface $serializerInterface,
        MessageBusInterface $bus,
        ConfigWorkerRepository $configWorkerRepository,
        EntityManagerInterface $entityManager,
        PaginatorInterface $paginator
        )
    {
        $this->logger = $logger;
        $this->operatingSystemRepository = $operatingSystemRepository;
        $this->serializer = $serializerInterface;
        $this->bus = $bus;
        $this->configWorkerRepository = $configWorkerRepository;
        $this->entityManager = $entityManager;
        $this->paginator = $paginator;
    }
    
	#[IsGranted("ROLE_TEACHER_EDITOR", message: "Access denied.")]
    #[Route(path: '/admin/operating-systems', name: 'operating_systems', methods: ['GET'])]
    public function indexAction(Request $request)
    {
        $search = trim($request->query->get('search', ''));
        $architecture = $request->query->get('arch', '');
        $imageType = $request->query->get('type', ''); // 'file', 'url', 'none'
        $hypervisorId = $request->query->get('hypervisor', '');
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        // Build criteria for filtering
        $criteria = $this->buildSearchCriteria($search, $architecture, $imageType, $hypervisorId);
        
        // Get filtered results
        $queryBuilder = $this->operatingSystemRepository->createQueryBuilder('os')
            ->leftJoin('os.hypervisor', 'h')
            ->addSelect('h');

        // Apply search filters
        if ($search) {
            $queryBuilder->andWhere($queryBuilder->expr()->orX(
                $queryBuilder->expr()->like('LOWER(os.name)', ':search'),
                $queryBuilder->expr()->like('LOWER(os.description)', ':search'),
                $queryBuilder->expr()->like('LOWER(a.Name)', ':search'),
                $queryBuilder->leftJoin('os.arch', 'a')
            ))->setParameter('search', '%' . strtolower($search) . '%');
        }

        if ($architecture) {
    $queryBuilder
        ->leftJoin('os.arch', 'a')
        ->andWhere('a.Name = :arch')
        ->setParameter('arch', $architecture);
}

        if ($hypervisorId) {
            $queryBuilder->andWhere('os.hypervisor = :hypervisorId')
                ->setParameter('hypervisorId', $hypervisorId);
        }

        // Apply image type filter
        switch ($imageType) {
            case 'file':
                $queryBuilder->andWhere('os.imageFilename IS NOT NULL');
                break;
            case 'url':
                $queryBuilder->andWhere('os.imageUrl IS NOT NULL');
                break;
            case 'none':
                $queryBuilder->andWhere('os.imageFilename IS NULL AND os.imageUrl IS NULL');
                break;
        }

        $queryBuilder->orderBy('os.name', 'ASC');

        // Paginate results
        $pagination = $this->paginator->paginate(
            $queryBuilder->getQuery(),
            $page,
            $limit
        );

        // Get statistics for badges
        $stats = $this->getOperatingSystemStats();

        if ('json' === $request->getRequestFormat()) {
            return $this->json($pagination->getItems(), 200, [], ['api_get_operating_system']);
        }

        return $this->render('operating_system/index.html.twig', [
            'operatingSystems' => $pagination->getItems(),
            'pagination' => $pagination,
            'search' => $search,
            'selectedArch' => $architecture,
            'selectedType' => $imageType,
            'selectedHypervisor' => $hypervisorId,
            'stats' => $stats,
            'hypervisors' => $this->getHypervisorsList(),
            'architectures' => $this->getAvailableArchitectures(),
        ]);
    }

    /**
     * API endpoint for operating systems
     */
    #[Route(path: '/api/operating-systems', name: 'api_get_operating_systems', methods: ['GET'])]
    #[IsGranted("ROLE_TEACHER_EDITOR", message: "Access denied.")]
    public function apiIndex(Request $request): Response
    {
        $search = trim($request->query->get('search', ''));
        $architecture = $request->query->get('arch', '');
        $hypervisorId = $request->query->get('hypervisor', '');
        $limit = $request->query->getInt('limit', 100);
        $offset = $request->query->getInt('offset', 0);

        $queryBuilder = $this->operatingSystemRepository->createQueryBuilder('os')
            ->leftJoin('os.hypervisor', 'h')
            ->addSelect('h')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        // Apply filters (same logic as web interface)
        if ($search) {
            $queryBuilder->andWhere($queryBuilder->expr()->orX(
                $queryBuilder->expr()->like('LOWER(os.name)', ':search'),
                $queryBuilder->expr()->like('LOWER(os.description)', ':search')
            ))->setParameter('search', '%' . strtolower($search) . '%');
        }

        if ($architecture) {
            $queryBuilder->andWhere('os.arch = :arch')
                ->setParameter('arch', $architecture);
        }

        if ($hypervisorId) {
            $queryBuilder->andWhere('os.hypervisor = :hypervisorId')
                ->setParameter('hypervisorId', $hypervisorId);
        }

        $queryBuilder->orderBy('os.name', 'ASC');

        $operatingSystems = $queryBuilder->getQuery()->getResult();
        $total = $this->operatingSystemRepository->count([]);

        return $this->json([
            'data' => $operatingSystems,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ], 200, [], ['groups' => ['api_get_operating_system']]);
    }

    /**
     * Show single operating system
     */
    #[Route(path: '/admin/operating-systems/{id<\d+>}', name: 'show_operating_system', methods: ['GET'])]
    #[IsGranted("ROLE_TEACHER_EDITOR", message: "Access denied.")]
    public function show(OperatingSystem $operatingSystem): Response
    {
        return $this->render('operating_system/view.html.twig', [
            'operatingSystem' => $operatingSystem,
        ]);
    }


    #[Route(path: '/admin/operating-systems/new', name: 'new_operating_system')]
    public function newAction(Request $request, ImageFileUploader $imageFileUploader)
    {
        $operatingSystem = new OperatingSystem();
        $operatingSystemForm = $this->createForm(OperatingSystemType::class, $operatingSystem);
        $operatingSystemForm->handleRequest($request);

        if ($operatingSystemForm->isSubmitted() && $operatingSystemForm->isValid()) {
            /** @var OperatingSystem $operatingSystem */
            $operatingSystemEdited = $operatingSystemForm->getData();

            // Récupération des différents champs
            $uploadedFilename = $operatingSystemForm->get('uploaded_filename')->getData();
            $imageFile = $operatingSystemForm->get('imageFilename')->getData();
            $imageUrl = $operatingSystemForm->get('imageUrl')->getData();
            $filenameOnly = $operatingSystemForm->get('image_Filename')->getData();
            $hypervisor = $operatingSystemForm->get('hypervisor')->getData();

            $this->logger->debug('New OS form submission values:', [
                'hypervisor' => $hypervisor ? $hypervisor->getName() : 'null',
                'uploadedFilename' => $uploadedFilename,
                'imageFile' => $imageFile ? $imageFile->getClientOriginalName() : 'null',
                'imageUrl' => $imageUrl,
                'filenameOnly' => $filenameOnly,
            ]);

            // Déterminer le type d'hyperviseur
            $hypervisorType = $hypervisor ? strtolower($hypervisor->getName()) : '';
            
            if (strpos($hypervisorType, 'lxc') !== false) {
                // Gestion LXC : seulement le nom du template
                if ($filenameOnly) {
                    $operatingSystemEdited->setImageFilename($filenameOnly);
                    $operatingSystemEdited->setImageUrl(null);
                    $this->logger->info("Edit OS - LXC template set: " . $filenameOnly);
                } else {
                    $operatingSystemEdited->setImageFilename(null);
                    $operatingSystemEdited->setImageUrl(null);
                }
            } else if (strpos($hypervisorType, 'qemu') !== false) {
                // Gestion QEMU : selon le type de source
                
                // 1. Gestion du fichier uploadé ou sélectionné
                if ($filenameOnly) {
                    $operatingSystemEdited->setImageFilename($filenameOnly);
                    $operatingSystemEdited->setImageUrl(null);
                    $this->logger->info("Edit OS - QEMU new file uploaded: " . $filenameOnly);
                }
                // 2. Gestion de l'URL
                elseif ($imageUrl) {
                    $operatingSystemEdited->setImageUrl($imageUrl);
                    $operatingSystemEdited->setImageFilename(null);
                    $this->logger->info("Edit OS - QEMU URL set: " . $imageUrl);
                }
                else {
                    $operatingSystemEdited->setImageFilename(null);
                    $operatingSystemEdited->setImageUrl(null);
                    $this->logger->info("Edit OS - QEMU no image source specified");
                }
            } else {
                // Hyperviseur non reconnu - nettoyer les champs image
                $operatingSystemEdited->setImageFilename(null);
                $operatingSystemEdited->setImageUrl(null);
                $this->logger->warning("Edit OS - Unknown hypervisor type: " . $hypervisorType);
            }

            // Sauvegarder
            $entityManager = $this->entityManager;
            $entityManager->persist($operatingSystemEdited);
            $entityManager->flush();

            $this->addFlash('success', 'Operating system has been created.');
            $this->logger->info("New OS - Operating system " . $operatingSystemEdited->getName() . " has been created");

            return $this->redirectToRoute('operating_systems');
        }

        $maxUploadSize = min(
            ini_get('upload_max_filesize'), ini_get('post_max_size')
        );
        
        return $this->render('operating_system/new.html.twig', [
            'operatingSystemForm' => $operatingSystemForm->createView(),
            'sizeLimit' => $maxUploadSize
        ]);
    }

    #[Route(path: '/admin/operating-systems/{id<\d+>}/edit', name: 'edit_operating_system', methods: ['GET', 'POST'])]
    public function editAction(Request $request, int $id, ImageFileUploader $imageFileUploader)
    {
        $operatingSystem = $this->operatingSystemRepository->find($id);
        if (null === $operatingSystem) {
            throw new NotFoundHttpException("Operating system " . $id . " does not exist.");
        }

        $operatingSystemForm = $this->createForm(OperatingSystemType::class, $operatingSystem);
        $operatingSystemForm->handleRequest($request);

        if ($operatingSystemForm->isSubmitted() && $operatingSystemForm->isValid()) {
            /** @var OperatingSystem $operatingSystemEdited */
            $operatingSystemEdited = $operatingSystemForm->getData();

            // Récupération des différents champs
            $uploadedFilename = $operatingSystemForm->get('uploaded_filename')->getData();
            $imageFile = $operatingSystemForm->get('imageFilename')->getData();
            $imageUrl = $operatingSystemForm->get('imageUrl')->getData();
            $filenameOnly = $operatingSystemForm->get('image_Filename')->getData();
            $hypervisor = $operatingSystemForm->get('hypervisor')->getData();

            $this->logger->debug('Form submission values:', [
                'hypervisor' => $hypervisor ? $hypervisor->getName() : 'null',
                'uploadedFilename' => $uploadedFilename,
                'imageFile' => $imageFile ? $imageFile->getClientOriginalName() : 'null',
                'imageUrl' => $imageUrl,
                'filenameOnly' => $filenameOnly,
            ]);

            // Déterminer le type d'hyperviseur
            $hypervisorType = $hypervisor ? strtolower($hypervisor->getName()) : '';
            
            if (strpos($hypervisorType, 'lxc') !== false) {
                // Gestion LXC : seulement le nom du template
                if ($filenameOnly) {
                    $operatingSystemEdited->setImageFilename($filenameOnly);
                    $operatingSystemEdited->setImageUrl(null);
                    $this->logger->info("Edit OS - LXC template set: " . $filenameOnly);
                } else {
                    $operatingSystemEdited->setImageFilename(null);
                    $operatingSystemEdited->setImageUrl(null);
                }
            } else if (strpos($hypervisorType, 'qemu') !== false) {
                // Gestion QEMU : selon le type de source
                
                // 1. Gestion du fichier uploadé ou sélectionné
                if ($filenameOnly) {
                    $operatingSystemEdited->setImageFilename($filenameOnly);
                    $operatingSystemEdited->setImageUrl(null);
                    $this->logger->info("Edit OS - QEMU new file uploaded: " . $filenameOnly);
                }
                // 2. Gestion de l'URL
                elseif ($imageUrl) {
                    $operatingSystemEdited->setImageUrl($imageUrl);
                    $operatingSystemEdited->setImageFilename(null);
                    $this->logger->info("Edit OS - QEMU URL set: " . $imageUrl);
                }
                else {
                    $operatingSystemEdited->setImageFilename(null);
                    $operatingSystemEdited->setImageUrl(null);
                    $this->logger->info("Edit OS - QEMU no image source specified");
                }
            } else {
                // Hyperviseur non reconnu - nettoyer les champs image
                $operatingSystemEdited->setImageFilename(null);
                $operatingSystemEdited->setImageUrl(null);
                $this->logger->warning("Edit OS - Unknown hypervisor type: " . $hypervisorType);
            }

            // Sauvegarder
            $this->entityManager->persist($operatingSystemEdited);
            $this->entityManager->flush();

            $this->addFlash('success', 'Operating system has been edited.');
            $this->logger->info("Edit OS - Successfully edited: " . $operatingSystemEdited->getName());

            return $this->redirectToRoute('show_operating_system', [
                'id' => $id
            ]);
        }

        $maxUploadSize = min(
            ini_get('upload_max_filesize'), ini_get('post_max_size')
        );
        
        return $this->render('operating_system/new.html.twig', [
            'operatingSystem' => $operatingSystem,
            'operatingSystemForm' => $operatingSystemForm->createView(),
            'sizeLimit' => $maxUploadSize,
            'edit' => true  // Ajouter cette variable pour le template
        ]);
    }

    /**
     * Delete operating system
     */
    #[Route(path: '/admin/operating-systems/{id}/delete', name: 'delete_operating_system', methods: ['POST'])]
    #[IsGranted("ROLE_TEACHER_EDITOR", message: "Access denied.")]
    public function delete(Request $request, OperatingSystem $operatingSystem): Response
    {
        // Vérifier le token CSRF
        if ($this->isCsrfTokenValid('delete' . $operatingSystem->getId(), $request->request->get('_token'))) {
            $entityManager = $this->entityManager;

            $devicesUsingOs = $entityManager
                ->getRepository(\App\Entity\Device::class)
                ->findBy(['operatingSystem' => $operatingSystem]);

            if (count($devicesUsingOs) === 0) {
                // Si c'est un fichier local, vous pourriez vouloir le supprimer du disque
                if ($operatingSystem->getImageFilename()) {
                    $imagePath = $this->getParameter('kernel.project_dir') . '/public/uploads/images/' . $operatingSystem->getImageFilename();
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }
                
                $entityManager->remove($operatingSystem);
                $entityManager->flush();

                $this->addFlash('success', 'Operating system "' . $operatingSystem->getName() . '" has been deleted successfully.');
            } else {
                $this->addFlash('danger', 'Unable to delete this OS: it is being used by at least one device.');
                return $this->redirectToRoute('operating_systems');
            }
        }
        else {
                $this->addFlash('error', 'Invalid security token. Please try again.');
            }
        return $this->redirectToRoute('operating_systems');
    }

    /**
     * Upload image file via API
     */
    #[Route('/api/images/upload', name: 'app_images_upload', methods: ['POST'])]
    public function upload(Request $request, SluggerInterface $slugger): Response
    {
        $this->logger->info('Uploading image file requested by user '.$this->getUser()->getName());
        
        $file = $request->files->get('file');
        
        if ($file && $file->isValid()) {
            $this->logger->debug('[OperatingSystemController:upload]::The file to upload will be '.$file.' of size '.$file->getSize());

            $maxUploadSize = min(
                $this->convertPHPSizeToBytes(ini_get('upload_max_filesize')),$this->convertPHPSizeToBytes(ini_get('post_max_size'))
            );

            if ($file->getSize() > $maxUploadSize) {
                return $this->json(['error' => 'File too large'], 413);
            }

            $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

            try {
                $uploadDir = $this->getParameter('image_directory');
                $this->logger->debug('[OperatingSystemController:upload]::Try to move '.$file.' file to '.$newFilename);        
                $file->move($uploadDir, $newFilename);
        
                $newFile = $uploadDir . '/' . $newFilename;
                $fileSize = file_exists($newFile) ? filesize($newFile) : null;

                $this->logger->debug('[OperatingSystemController:upload]::Move '.$file.' file to '.$newFile.' done.');        

            } catch (FileException $e) {
                return $this->json(['error' => 'Upload failed'], 500);
            }
            catch (\Exception $e) {
                $this->logger->error('[OperatingSystemController:upload]::Error during file upload: ' . $e->getMessage());
                return $this->json(['error' => 'Upload failed due to an unexpected error'], 500);
            }

            return $this->json([
                'success' => true, 
                'filename' => $newFilename,
                'originalName' => $file->getClientOriginalName(),
                'size' => $fileSize
            ]);
        }
        else return $this->json(['error' => 'No file uploaded'], 400);
    }

    #[Route('/api/images/delete-temp-file', name: 'app_images_delete_temp_file', methods: ['POST','DELETE'])]
    public function deleteTempFile(Request $request): Response
    {
        $filename = $request->request->get('filename');
        $this->logger->info('Deleting temporary images file '.$filename.' by user '.$this->getUser()->getName().' requested');

        if (!$filename) {
            return $this->json(['error' => 'No filename provided'], 400);
        }

        $filePath = $this->getParameter('image_directory') . '/' . $filename;
        if (file_exists($filePath)) {
            unlink($filePath);
            $this->logger->info('Deleting temporary images file '.$filename.' by user '.$this->getUser()->getName().' done');
            return $this->json(['success' => true]);
        }

        return $this->json(['error' => 'File not found'], 404);
    }

    #[Route('/api/images/validate-url', name: 'app_images_validate_url', methods: ['POST'])]
    public function validateUrl(Request $request): Response
    {
        $this->logger->debug('[IsoController:validateUrl]::Validating image URL by user :'.$this->getUser()->getName());
        $url = $request->request->get('url');
        if (!$url) {
            return $this->json(['error' => 'No URL provided'], 400);
        }

        $validation = $this->validateImageUrl($url);
        
        if ($validation['valid']) {
            return $this->json([
                'success' => true,
                'valid' => true,
                'fileSize' => $validation['fileSize'],
                'contentType' => $validation['contentType'],
                'fileName' => $validation['fileName']
            ]);
        } else {
            return $this->json([
                'success' => true,
                'valid' => false,
                'error' => $validation['error']
            ]);
        }
    }

    private function validateImageUrl(string $url): array
    {
        // Vérification basique de l'URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'error' => 'Invalid URL format'];
        }

        // Vérifier que l'URL contient .img ou .qcow2
        if (!preg_match('/\.(img|qcow2)(\?|$|#)/i', $url)) {
            return ['valid' => false, 'error' => 'URL does not appear to be an img or qcow2 file'];
        }

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_NOBODY, true); // Requête HEAD
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; ISO-Validator/1.0)');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true); // Récupérer les headers
            curl_exec($ch);

            if (curl_errno($ch)) {
                $this->logger->error('Image URL validation error: ' . curl_error($ch) . ' for URL: ' . $url);
                return ['valid' => false, 'error' => 'Unable to reach the URL'];
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $fileSize = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

            if ($httpCode !== 200) {
                return ['valid' => false, 'error' => "HTTP error: $httpCode"];
            }

            // Vérifier que la taille est raisonnable pour un ISO (entre 1MB et 40GB)
            if ($fileSize !== -1) { // -1 signifie que la taille n'est pas disponible
                if ($fileSize < 1024 * 1024 || $fileSize > 40 * 1024 * 1024 * 1024) {
                    return ['valid' => false, 'error' => 'File size is not reasonable (must be between 1MB and 40GB)'];
                }
            }

            // Vérifier les types MIME acceptables
            $validMimeTypes = [
                'application/octet-stream',
                'application/x-qemu-disk',
            ];

            $isValidMime = false;
            if ($contentType) {
                foreach ($validMimeTypes as $validType) {
                    if (strpos($contentType, $validType) !== false) {
                        $isValidMime = true;
                        break;
                    }
                }
                if (!$isValidMime) {
                    $this->logger->warning('Image URL validation: Unexpected content type: ' . $contentType . ' for URL: ' . $url);
                }
            }

            // Nom du fichier depuis l'URL
            $fileName = basename(parse_url($finalUrl, PHP_URL_PATH));
            if (empty($fileName) || !preg_match('/\.(img|qcow2)$/i', $fileName)) {
                $fileName = 'downloaded.img';
            }

            curl_close($ch);

            return [
                'valid' => true,
                'fileSize' => $fileSize !== -1 ? $fileSize : null,
                'contentType' => $contentType,
                'fileName' => $fileName
            ];
        } catch (\Exception $e) {
            $this->logger->error('Image URL validation error: ' . $e->getMessage() . ' for URL: ' . $url);
            return ['valid' => false, 'error' => 'Network error or timeout'];
        }
    }

    /**
     * Build search criteria
     */
    private function buildSearchCriteria(
        string $search = '', 
        string $architecture = '', 
        string $imageType = '', 
        string $hypervisorId = ''
    ): Criteria {
        $criteria = Criteria::create();

        if ($search) {
            $criteria->andWhere(
                Criteria::expr()->orX(
                    Criteria::expr()->contains('name', $search),
                    Criteria::expr()->contains('description', $search)
                )
            );
        }

        if ($architecture) {
            $criteria->andWhere(Criteria::expr()->eq('arch', $architecture));
        }

        $criteria->orderBy(['name' => Criteria::ASC]);

        return $criteria;
    }

    /**
     * Get operating system statistics for badges
     */
    private function getOperatingSystemStats(): array
    {
        $qb = $this->operatingSystemRepository->createQueryBuilder('os');
        
        return [
            'total' => $qb->select('COUNT(os.id)')->getQuery()->getSingleScalarResult(),
            'with_files' => $qb->select('COUNT(os.id)')
                ->where('os.imageFilename IS NOT NULL')
                ->getQuery()->getSingleScalarResult(),
            'with_urls' => $qb->select('COUNT(os.id)')
                ->where('os.imageUrl IS NOT NULL')
                ->getQuery()->getSingleScalarResult(),
            'without_images' => $qb->select('COUNT(os.id)')
                ->where('os.imageFilename IS NULL AND os.imageUrl IS NULL')
                ->getQuery()->getSingleScalarResult(),
        ];
    }

    /**
     * Get list of hypervisors for filter dropdown
     */
    private function getHypervisorsList(): array
    {
        /*return $this->getDoctrine()
            ->getRepository(\App\Entity\Hypervisor::class)
            ->findBy([], ['name' => 'ASC']);*/
        return $this->operatingSystemRepository->findBy([], ['name' => 'ASC']);
    }

    /**
     * Get available architectures
     */
    private function getAvailableArchitectures(): array
    {
        $archs = $this->entityManager->getRepository(\App\Entity\Arch::class)->findAll();
        return array_map(fn($arch) => $arch->getName(), $archs);

    }

    // Fonction utilitaire pour convertir la taille PHP en octets
    private function convertPHPSizeToBytes($size)
    {
        $unit = strtolower(substr($size, -1));
        $bytes = (int) $size;

        switch($unit) {
            case 'g':
                $bytes *= 1024;
            case 'm':
                $bytes *= 1024;
            case 'k':
                $bytes *= 1024;
        }

        return $bytes;
    }

}
