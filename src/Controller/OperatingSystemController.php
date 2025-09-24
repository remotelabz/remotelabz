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
use Symfony\Component\HttpFoundation\Response; // ✅ Correct

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
            $operatingSystem = $operatingSystemForm->getData();

            if ($operatingSystem->getImageUrl() !== null && $operatingSystem->getImageFilename() !== null) {
                $this->addFlash('danger', "You can't provide an image url and an image file. Please provide only one.");
                $this->logger->error("New OS - You can't provide an image url and an image file. Please provide only one.");
            } else {
                /** @var UploadedFile|null $imageFile */
                $imageFile = $operatingSystemForm->get('imageFilename')->getData();
                    if ($imageFile && strtolower($imageFile->getClientOriginalExtension()) != 'img' && strtolower($imageFile->getClientOriginalExtension()) != 'qcow2') {
                        $this->addFlash('danger', "Only .img or qcow2 files are accepted.");
                        $this->logger->error("New OS - Only .img or qcow2 files are accepted");

                    } else {
                        if ($imageFile) {
                            $imageFileName = $imageFileUploader->upload($imageFile);
                            $operatingSystem->setImageFilename($imageFileName);
                        }
                        $entityManager = $this->entityManager;
                        $entityManager->persist($operatingSystem);
                        $entityManager->flush();

                        $this->addFlash('success', 'Operating system has been created.');
                        $this->logger->info("New OS - Operating system ".$operatingSystem->getName()." has been created with image ".$operatingSystem->getImageFilename());

                    }
                return $this->redirectToRoute('operating_systems');
            }
        }

        return $this->render('operating_system/new.html.twig', [
            'operatingSystemForm' => $operatingSystemForm->createView(),
        ]);
    }

    #[Route(path: '/admin/operating-systems/{id<\d+>}/edit', name: 'edit_operating_system', methods: ['GET', 'POST'])]
    public function editAction(Request $request, int $id, ImageFileUploader $imageFileUploader)
    {
        $operatingSystem = $this->operatingSystemRepository->find($id);
    if (null === $operatingSystem) {
        throw new NotFoundHttpException("Operating system " . $id . " does not exist.");
    }

    $operatingSystemFilename = $operatingSystem->getImageFilename();
    $operatingSystemArch = $operatingSystem->getArch(); // objet Arch
    $operatingSystemEdited = $operatingSystem;

    $operatingSystemForm = $this->createForm(OperatingSystemType::class, $operatingSystemEdited);
    $operatingSystemForm->handleRequest($request);

    if ($operatingSystemForm->isSubmitted() && $operatingSystemForm->isValid()) {
        $operatingSystemEdited = $operatingSystemForm->getData();
        $image_filename_upload = $operatingSystemForm->get('imageFilename')->getData();
        $image_filename_modified = $operatingSystemForm->get('imageFilename')->getData();
        $arch_modified = $operatingSystemForm->get('arch')->getData(); // objet Arch

        if ($image_filename_upload) {
            $new_ImageFileName = $imageFileUploader->upload($image_filename_upload);

            if (is_null($image_filename_modified)) {
                $operatingSystemEdited->setImageFilename($new_ImageFileName);
            } else {
                $operatingSystemEdited->setImageFilename($image_filename_modified);
            }
        } else {
            $operatingSystemEdited->setImageFilename($image_filename_modified);
        }

        // Update architecture if changed (compare by id)
        if ($arch_modified && (!$operatingSystemArch || $arch_modified->getId() !== $operatingSystemArch->getId())) {
            $operatingSystemEdited->setArch($arch_modified);
        }

        $entityManager = $this->entityManager;
        $entityManager->persist($operatingSystemEdited);
        $entityManager->flush();

        $new_name_os = [
            "old_name" => $operatingSystemFilename,
            "new_name" => $operatingSystemEdited->getImageFilename(),
            "hypervisor" => $operatingSystemEdited->getHypervisor()->getName(),
            "old_arch" => $operatingSystemArch ? $operatingSystemArch->getName() : null,
            "new_arch" => $arch_modified ? $arch_modified->getName() : null
        ];

        $this->bus->dispatch(
            new InstanceActionMessage(json_encode($new_name_os), $operatingSystemEdited->getId(), InstanceActionMessage::ACTION_RENAMEOS)
        );
        $this->addFlash('success', 'Operating system has been edited.');
        return $this->redirectToRoute('show_operating_system', [
            'id' => $id
        ]);
    }

    return $this->render('operating_system/new.html.twig', [
        'operatingSystem' => $operatingSystem,
        'operatingSystemForm' => $operatingSystemForm->createView()
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
                $this->addFlash('danger', 'Invalid security token. Please try again.');
            }
        return $this->redirectToRoute('operating_systems');
    }

    public function cancel_renameos($names) {

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
}
