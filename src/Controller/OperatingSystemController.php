<?php

namespace App\Controller;

use App\Entity\OperatingSystem;
use App\Entity\Device;
use App\Repository\HypervisorRepository;
use App\Repository\OperatingSystemRepository;
use App\Repository\ArchRepository;
use App\Repository\ConfigWorkerRepository;
use Psr\Log\LoggerInterface;
use App\Form\OperatingSystemType;
use App\Form\BlankOperatingSystemType;
use App\Service\ImageFileUploader;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Process\Process;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Remotelabz\Message\Message\InstanceActionMessage;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use App\Service\Files2WorkerManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use App\Service\OvaManager;



#[IsGranted("ROLE_TEACHER_EDITOR", message: "Access denied.")]
class OperatingSystemController extends Controller
{
    /**
     * @var OperatingSystemRepository
     */
    private $workerRepository;
    private $operatingSystemRepository;
    private $hypervisorRepository;
    private $logger;
    private $serializer;
    protected $bus;
    private $configWorkerRepository;
    private $entityManager;
    private $paginator;
    private Files2WorkerManager $Files2WorkerManager;
    private OvaManager $ovaManager;
    private $archRepository;


    public function __construct(LoggerInterface $logger,
        OperatingSystemRepository $operatingSystemRepository,
        SerializerInterface $serializerInterface,
        MessageBusInterface $bus,
        ConfigWorkerRepository $configWorkerRepository,
        EntityManagerInterface $entityManager,
        PaginatorInterface $paginator,
        Files2WorkerManager $Files2WorkerManager,
        HypervisorRepository $hypervisorRepository,
        OvaManager $ovaManager,
        ArchRepository $archRepository
        )
    {
        $this->logger = $logger;
        $this->operatingSystemRepository = $operatingSystemRepository;
        $this->archRepository = $archRepository;
        $this->serializer = $serializerInterface;
        $this->bus = $bus;
        $this->configWorkerRepository = $configWorkerRepository;
        $this->entityManager = $entityManager;
        $this->paginator = $paginator;
        $this->Files2WorkerManager = $Files2WorkerManager;
        $this->hypervisorRepository = $hypervisorRepository;
        $this->ovaManager=$ovaManager;
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
            $queryBuilder
		->leftJoin('os.arch','a')
		->andWhere($queryBuilder->expr()->orX(
                $queryBuilder->expr()->like('LOWER(os.name)', ':search'),
                $queryBuilder->expr()->like('LOWER(os.description)', ':search'),
                $queryBuilder->expr()->like('LOWER(a.name)', ':search')
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
        $this->ensureDefaultArchitecture($operatingSystem);
        $operatingSystemForm = $this->createForm(OperatingSystemType::class, $operatingSystem);
        $operatingSystemForm->handleRequest($request);
        $maxUploadSize = min(ini_get('upload_max_filesize'), ini_get('post_max_size'));

        if ($operatingSystemForm->isSubmitted() && $operatingSystemForm->isValid()) {
            /** @var OperatingSystem $operatingSystem */
            $operatingSystemEdited = $operatingSystemForm->getData();

            $name=trim($operatingSystemEdited->getName());
            if ( $name == null || $name == "" || $this->operatingSystemRepository->findByName($name)) {
                if ($name == null || $name == "" )
                    $this->addFlash('danger', 'Operating system name is required.');                
                if ($this->operatingSystemRepository->findByName($name))
                    $this->addFlash('danger', 'Operating system name already exists.');
            
                return $this->render('operating_system/new.html.twig', [
                    'operatingSystemForm' => $operatingSystemForm->createView(),
                    'sizeLimit' => $maxUploadSize
                ]);
            }

            // Récupération des différents champs
            $uploadedFilename = $operatingSystemForm->get('uploaded_filename')->getData();
            $imageFile = $operatingSystemForm->get('imageFilename')->getData();
            $imageUrl = $operatingSystemForm->get('imageUrl')->getData();
            $filenameOnly = $operatingSystemForm->get('image_Filename')->getData();
            $hypervisor = $operatingSystemForm->get('hypervisor')->getData();

            $this->logger->debug('{OperatingSystemController:newAction]::New OS form submission values:', [
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
             
                if ($uploadedFilename && trim($uploadedFilename) !== '') {
                    $localFilePath = $this->getParameter('image_directory') . '/' . $uploadedFilename;
                    $remoteFilePath = '/images/'.$uploadedFilename;
                    $this->Files2WorkerManager->CopyFileToAllWorkers($localFilePath, $remoteFilePath);
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

    #[Route('/operating-system/new-blank', name: 'new_blank_operating_system')]
    public function newBlank(Request $request, HypervisorRepository $hypervisorRepository): Response
    {
        $operatingSystem = new OperatingSystem();
        $this->ensureDefaultArchitecture($operatingSystem);
        
        $qemuHypervisor = $hypervisorRepository->findByName('qemu');
            if (!$qemuHypervisor) {
                $this->addFlash('danger', 'QEMU hypervisor not found in database.');
                return $this->redirectToRoute('operating_systems');
            }
        $operatingSystem->setHypervisor($qemuHypervisor);
        
        $form = $this->createForm(BlankOperatingSystemType::class, $operatingSystem);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->logger->debug('[OperatingSystemController:newBlank]::Form submit and valid');
            
            // Vérifier le nom
            $name = trim($operatingSystem->getName());
            if (empty($name) || $this->operatingSystemRepository->findByName($name)) {
                if (empty($name)) {
                    $this->addFlash('danger', 'Operating system name is required.');
                }
                if ($this->operatingSystemRepository->findByName($name)) {
                    $this->addFlash('danger', 'Operating system name already exists.');
                }
                
                return $this->render('operating_system/new_blank.html.twig', [
                    'form' => $form->createView(),
                ]);
            }
            
            $currentDescription = $operatingSystem->getDescription();
            if (empty($currentDescription)) {
                $operatingSystem->setDescription('This is a blank OS image');
            } else {
                $operatingSystem->setDescription($currentDescription . ' - This is a blank OS image');
            }
            

            // Vérifier que le FlavorDisk est obligatoire pour un Blank OS
            if (!$operatingSystem->getFlavorDisk()) {
                $this->addFlash('danger', 'A disk flavor is required for a blank operating system.');
                return $this->render('operating_system/new_blank.html.twig', [
                    'form' => $form->createView(),
                ]);
            }
            
            // Pas d'image URL ou fichier pour un Blank OS
            $operatingSystem->setImageUrl(null);
            
            
            // Construire le nom de l'image au format "osname_flavor"
            $flavorName = $operatingSystem->getFlavorDisk()->getName();
            
            $imageName = preg_replace('/[ .]/','_',trim($name)). '_' . $flavorName;
            //$operatingSystem->setImage($imageName);
            $operatingSystem->setImageFilename($imageName);
            
            $this->logger->debug('[OperatingSystemController:newBlank]::Image name set from '.$name.' to '.$imageName);
            
            $entityManager = $this->entityManager;
            $entityManager->persist($operatingSystem);
            $entityManager->flush();

            $this->addFlash('success', 'Blank Operating System created successfully!');
            $this->logger->info('New Blank OS - ' . $operatingSystem->getName() . ' created by user ' . $this->getUser()->getName());

            return $this->redirectToRoute('operating_systems');
        } elseif ($form->isSubmitted()) {
            $this->logger->debug('[OperatingSystemController:newBlank]::Form submit and not valid');
            
            // Logger les erreurs de validation
            foreach ($form->getErrors(true) as $error) {
                $this->logger->error('Blank OS Form error: ' . $error->getMessage());
            }
        }

        return $this->render('operating_system/new_blank.html.twig', [
            'form' => $form->createView(),
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
        $old_filename=$operatingSystem->getImageFilename();
        $this->logger->debug('[OperatingSystemController:editAction]::Filename before submit is '.$old_filename);

        if ($operatingSystemForm->isSubmitted() && $operatingSystemForm->isValid()) {
            /** @var OperatingSystem $operatingSystemEdited */
            $operatingSystemEdited = $operatingSystemForm->getData();

            // Récupération des différents champs
            $uploadedFilename = $operatingSystemForm->get('uploaded_filename')->getData();
            $imageFile = $operatingSystemForm->get('imageFilename')->getData();
            $imageUrl = $operatingSystemForm->get('imageUrl')->getData();
            $filenameOnly = $operatingSystemForm->get('image_Filename')->getData();
            $hypervisor = $operatingSystemForm->get('hypervisor')->getData();

            $this->logger->debug('[OperatingSystemController:editAction]::Form submission values:', [
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
                    
                    $this->logger->debug('[OperatingSystemController:editAction]::Filename is modified from '.$old_filename.' to '.$filenameOnly);
                    if (strcmp($old_filename,$filenameOnly)) {
                        $this->logger->debug('[OperatingSystemController:editAction]::Filename is modifed to '.$filenameOnly);
                        $oldfilenamepath=$this->getParameter('image_directory') . '/' . $operatingSystemEdited->getImageFilename();
                        $this->Files2WorkerManager->deleteFileFromAllWorkers($oldfilenamepath);
                    }
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

                if ($uploadedFilename && trim($uploadedFilename) !== '') {
                    $localFilePath = $this->getParameter('image_directory') . '/' . $uploadedFilename;
                    $remoteFilePath = '/images/'.$uploadedFilename;
                    
                    if ($this->Files2WorkerManager->AvailableWorkerExist()) {
                        $this->Files2WorkerManager->deleteFileFromAllWorkers($old_filename);
                        $results=$this->Files2WorkerManager->CopyFileToAllWorkers($localFilePath, $remoteFilePath);
                        $failures = array_filter($results, function($result) { 
                        });
                        if (!empty($failures)) {
                            $this->addFlash('warning', 'Image created but some workers failed to send the file.');
                        } else {
                            unlink($this->getParameter('image_directory') . '/' . $old_filename);
                            $this->addFlash('success', 'Image created and file copied to all workers successfully.');
                        }
                    }                   
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

            $this->addFlash('success', 'Operating system has indeed been edited.');
            $this->logger->info("Edit OS - Successfully edited: " . $operatingSystemEdited->getName());

            return $this->redirectToRoute('show_operating_system', [
                'id' => $id
            ]);
        }
        elseif ($operatingSystemForm->isSubmitted()) {
            $this->logger->error('Form submitted but invalid during edit');
            $this->logger->debug('[OperatingSystemController:editAction]::Form errors: ' . (string) $form->getErrors(true));

            $uploadedFileName = $operatingSystemForm->get('uploaded_filename')->getData();
            if ($uploadedFileName && trim($uploadedFileName) !== '') {
                if ($this->deleteLocalTempFile($uploadedFileName)) {
                    $this->logger->debug('[OperatingSystemController:editAction]::Deleted temporary file: ' . $uploadedFileName);
                } else {
                    $this->logger->debug('[OperatingSystemController:editAction]::No temporary file to delete: ' . $uploadedFileName);
                }
            }

            // Afficher les erreurs pour debug
            foreach ($form->getErrors(true) as $error) {
                $this->logger->error('Form error: ' . $error->getMessage());
            }
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

    private function deleteLocalTempFile(string $filename): bool
    {
        $filePath = $this->getParameter('image_directory') . '/' . $filename;
        if (file_exists($filePath)) {
            unlink($filePath);
            return true;
        }
        return false;
    }
    /**
     * Delete operating system
     */
    #[Route(path: '/admin/operating-systems/{id}/delete', name: 'delete_operating_system', methods: ['DELETE'])]
    #[IsGranted("ROLE_TEACHER_EDITOR", message: "Access denied.")]
    public function delete(Request $request, OperatingSystem $operatingSystem): Response
    {
        // Vérifier le token CSRF
        $id=$operatingSystem->getId();
        if ($this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $operatingSystem = $this->operatingSystemRepository->find($id);
            $operatingSystemName=$operatingSystem->getImageFilename();
            $operatingSystemHypervisor=strtolower($operatingSystem->getHypervisor()->getName());

            if (null === $operatingSystem) {
                throw new NotFoundHttpException("Operating system " . $id . " does not exist.");
            }
            $entityManager = $this->entityManager;
            $device_repository=$entityManager->getRepository(Device::class)->findOneBy(["operatingSystem"=>$id]);
            if ($device_repository) {
                $this->logger->debug("[OperatingSystemController:delete]::OS used by at least one device ".$device_repository->getName(). " in a lab");
                $this->addFlash('danger', 'This operating system is still used by, at least, the device "'.$device_repository->getName().'". Please delete them first.');

                return $this->redirectToRoute('show_operating_system', [
                    'id' => $id
                ]);
            }
            else  {              
                $this->logger->debug("[OperatingSystemController:delete]::OS not used");
                $entityManager->remove($operatingSystem);
            
                try {
                    $entityManager->flush();
                    $this->addFlash('success', $operatingSystem->getName() . ' has been deleted.');
                    $this->logger->info("Delete OS - Operating system " . $operatingSystem->getName() . " has been deleted by user ".$this->getUser()->getName());
                    if (null !== $operatingSystemName) {
                        $workers = $this->configWorkerRepository->findAll();
            
                        foreach ($workers as $otherWorker) {
                            $otherWorkerIP=$otherWorker->getIPv4();
                            $tmp=array();
                            $tmp['Worker_Dest_IP'] = $otherWorkerIP;
                            $tmp['hypervisor'] = $operatingSystemHypervisor;
                            $tmp['os_imagename'] = $operatingSystemName;
                            $deviceJsonToCopy = json_encode($tmp, 0, 4096);
                            // the case of qemu image with link.
                            $this->logger->debug("OS to delete on worker ".$otherWorkerIP,$tmp);
                            $this->bus->dispatch(
                                new InstanceActionMessage($deviceJsonToCopy, "", InstanceActionMessage::ACTION_DELETEOS), [
                                    new AmqpStamp($otherWorkerIP, AMQP_NOPARAM, [])
                                    ]
                                );            
                        }
                    }
                    
                    return $this->redirectToRoute('operating_systems');
                } catch (ForeignKeyConstraintViolationException $e) {
                    $this->logger->error("ForeignKeyConstraintViolationException".$e->getMessage());
                    $this->addFlash('danger', 'This operating system is still used in some device templates or lab. Please delete them first.');

                    return $this->redirectToRoute('show_operating_system', [
                        'id' => $id
                    ]);
                }
            }
        }
    }

    #[Post('/api/operating-systems/lxc_params', name: 'api_new_lxc_device_params')]
	#[Post('/api/operating-systems/lxc', name: 'api_new_lxc_device')]
	#[Security("is_granted('ROLE_TEACHER_EDITOR')", message: "Access denied.")]
    #[Route(path: '/admin/operating-systems/new_lxc', name: 'new_lxc_device')]
    public function newLxcAction(Request $request, UrlGeneratorInterface $router)
    {
        $file=file_get_contents("https://images.linuxcontainers.org/images");
        $dom = new \DOMDocument();
        $dom->loadHtml($file);
        $links = $dom->getElementsByTagName('a');
        $os = [];
        foreach($links as $link){
            if($link->nodeValue !== "../") {
                array_push($os, ucfirst(substr($link->nodeValue, 0, -1)));
            }
        }

        $os_json = json_encode($os);

        if ('json' === $request->getRequestFormat()) {
            if ($request->get("_route") == "api_new_lxc_device_params") {
                $data = json_decode($request->getContent(), true);
                if (!isset($data['version']) && isset($data['os'])) {
                    $fileVersion = file_get_contents("https://images.linuxcontainers.org/images/". $data['os']);
                    $dom = new \DOMDocument();
                    $dom->loadHtml($fileVersion);
                    $links = $dom->getElementsByTagName('a');
                    $versions = [];
                    foreach($links as $link){
                        if($link->nodeValue !== "../") {
                            array_push($versions, substr($link->nodeValue, 0, -1));
                        }
                    }
                    return $this->json($versions, 200, [], []);
                }
                if (!isset($data['date']) && isset($data['version'])) {
                    $fileVersion = file_get_contents("https://images.linuxcontainers.org/images/". $data['os'].$data['version']."amd64/default/");
                    $dom = new \DOMDocument();
                    $dom->loadHtml($fileVersion);
                    $links = $dom->getElementsByTagName('a');
                    $updates = [];
                    foreach($links as $link){
                        if($link->nodeValue !== "../") {
                            array_push($updates, $link->nodeValue);
                        }
                    }
                    $update = end($updates);
                    return $this->json($update, 200, [], []);
                }
            }
            //return $this->json($deviceForm, 200, [], ['api_get_device']);
        }

        if ($request->get("_route") == "api_new_lxc_device") {
            $data = json_decode($request->getContent(), true);
            $this->logger->debug("[OperatingSystemController:newLxcAction]::data in request ",$data);
            $hypervisor = $this->hypervisorRepository->findByName('lxc');
            $entityManager = $this->entityManager;
            $osName = ucfirst($data['os'])."-".$data['version'];
            $newOs_id=null;
            $operatingSystem = $this->operatingSystemRepository->findByName($osName);
            if(!$operatingSystem) {
                $newOs = new OperatingSystem();
                $this->ensureDefaultArchitecture($newOs);
                $newOs->setName($osName);
                $newOs->setHypervisor($hypervisor);
                $newOs->setImageFilename($osName);
                $newOs->setArch($this->archRepository->findByName("x86_64"));
                $newOs->setRelease($data['os']);
                $newOs->setVersion($data['version']);
                $entityManager->persist($newOs);
                $entityManager->flush();
                $newOs_id=$newOs->getId();
                $this->logger->info("New LXC OS - Operating system " . $newOs->getName() . " has been created by user ".$this->getUser()->getName());
            }
            $values = ['os'=> ucfirst($data['os']), 'model'=> $data['version'],'os_id'=>$newOs_id];
            return $this->json($values, 200, [], []);
        }

        return $this->render('operating_system/newLxc.html.twig', [
            'os' => $os,
            'props' => $os_json,
        ]);
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

        $maxUploadSize = $this->convertPHPSizeToBytes(min(ini_get('upload_max_filesize'),ini_get('post_max_size')));

        if ($file->getSize() > $maxUploadSize) {
            return $this->json(['error' => 'File too large'], 413);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
        $file_extension = $file->guessExtension();
        $this->logger->debug('[OperatingSystemController:upload]::The '.$file.' is a '.$file_extension.' file');
        
        if ($file_extension === "tar" || $file_extension === "img" || $file_extension === "qcow2") {
            $filesystem = new Filesystem();
            $temp_path = sys_get_temp_dir()."/Remotelabz-image-convert-".uniqid();
            $filesystem->mkdir($temp_path);
            
            try {
                $uploadDir = $this->getParameter('image_directory');
                
                if ($file_extension === "tar") {
                    // Conversion OVA vers QCOW2
                    $newFilename = basename($newFilename, ".tar") . ".qcow2";
                    $converted_file=$this->convertOva2qcow2($temp_path, $file, $newFilename);

                    if ($converted_file) {
                        $this->logger->debug('[OperatingSystemController:upload]::Convert OVA file to '.$converted_file.' done.');
                    } else {
                        throw new \Exception('Error during OVA to QCOW2 conversion');
                    }
                    
                    // Déplacement du fichier converti vers le répertoire final
                    
                    $destinationFile = $uploadDir . "/" . $newFilename;
                    
                    $this->logger->debug('[OperatingSystemController:upload]::Try to move '.$converted_file.' file to '.$destinationFile);

                    if (!file_exists($converted_file)) {
                        throw new \Exception('Converted file not found: ' . $converted_file);
                    }

                    if (!rename($converted_file, $destinationFile)) {
                        // Si rename échoue (par exemple entre partitions différentes), utiliser copy + unlink
                        if (!copy($converted_file, $destinationFile)) {
                            throw new \Exception('Failed to copy file to destination');
                        }
                        unlink($converted_file);
                    }
                    
                } else {
                    // Pour IMG et QCOW2, déplacement direct
                    $this->logger->debug('[OperatingSystemController:upload]::Try to move file to '.$uploadDir);
                    $file->move($uploadDir, $newFilename);
                    $destinationFile = $uploadDir . "/" . $newFilename;
                }
                
                $fileSize = file_exists($destinationFile) ? filesize($destinationFile) : null;
                
                $this->logger->debug('[OperatingSystemController:upload]::Move file to '.$uploadDir.' done');
                $this->logger->info('File successfully uploaded to '.$uploadDir);
                
                return $this->json([
                    'success' => true, 
                    'filename' => $newFilename,
                    'originalName' => $file->getClientOriginalName(),
                    'size' => $fileSize
                ]);
            }
            catch (FileException $e) {
                $this->logger->error('[OperatingSystemController:upload]::FileException occurs: ' . $e->getMessage());
                
                if (str_contains($e->getMessage(), "No space left"))
                    return $this->json(['error' => 'No space left on the device'], 500);
                else
                    return $this->json(['error' => 'Upload failed'], 500);
            }
            catch (\Exception $e) {
                $this->logger->error('[OperatingSystemController:upload]::Error during file upload: ' . $e->getMessage());
                if (str_contains($e->getMessage(), "No space left"))
                    return $this->json(['error' => 'No space left on the device'], 500);
                else
                    return $this->json(['error' => 'Upload failed due to an unexpected error'], 500);
            }
            finally {
                // Nettoyage du répertoire temporaire
                if ($filesystem->exists($temp_path)) {
                    $filesystem->remove($temp_path);
                    $this->logger->debug('[OperatingSystemController:upload]::Temporary directory '.$temp_path.' cleaned');
                }
            }
        } else {
            $this->logger->debug('[OperatingSystemController:upload]::The upload file is not supported: '.$file_extension);
            return $this->json(['error' => 'Only TAR (OVA), IMG or QCOW2 files are supported for upload.'], 400);
        }
    }
    else {
        return $this->json(['error' => 'No file uploaded'], 400);
    }
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

        // Vérifier que l'URL contient .qcow2
        if (!preg_match('/\.(qcow2)(\?|$|#)/i', $url)) {
            return ['valid' => false, 'error' => 'URL does not appear to be a qcow2 file'];
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
            if (empty($fileName) || !preg_match('/\.(qcow2)$/i', $fileName)) {
                $fileName = 'downloaded.qcow2';
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

    private function convertOva2qcow2(string $tempDir,string $filename, string $newfilename): string
    {
        $filesystem = new Filesystem();
        
        try {
            $this->logger->debug('Creation directory ok');
            
            try {
                // --- Étape 1 : Extraction de l'OVA (TAR) ---
                $this->logger->info("OVA extraction of file ".$filename);
                $tarCommand = ['tar', 'xf', $filename, '-C', $tempDir];
                $process = new Process($tarCommand);
                $process->setTimeout(3600);
                $process->mustRun();
                $this->logger->debug('[OperatingSystemController:convertOva2qcow2]::Tar executed');
                
                // Convert all vmdk in one qcow2
                $newfilename=$this->ovaManager->processVmdkFiles($tempDir);
                //newfilename with path
                $this->logger->debug('[OperatingSystemController:convertOva2qcow2]::tempDir:'.$tempDir.' filename:'.$filename.' newfilename:'.$newfilename);
                $this->logger->debug('[OperatingSystemController:convertOva2qcow2]::VMDK processed');                
                return $newfilename;
                
            } catch (ProcessFailedException $exception) {
                $this->logger->error('Error during OVA to QCOW2 conversion: ' . $exception->getMessage());
                throw new \RuntimeException('Conversion failed: ' . $exception->getMessage());
            } 
        } catch (IOExceptionInterface $exception) {
            $this->logger->error('Error creating temporary directory at ' . $exception->getPath());
            throw new \RuntimeException('Could not create temporary directory for conversion.');
        }
    }

    private function ensureDefaultArchitecture(OperatingSystem $operatingSystem): void
    {
        if (!$operatingSystem->getArch()) {
            $defaultArch = $this->archRepository->findOneBy(['name' => 'x86_64']);
            if ($defaultArch) {
                $operatingSystem->setArch($defaultArch);
            }
        }
    }


}
