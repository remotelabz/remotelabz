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
use Symfony\Component\Routing\Annotation\Route;
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

    public function __construct(LoggerInterface $logger,
        OperatingSystemRepository $operatingSystemRepository,
        SerializerInterface $serializerInterface,
        MessageBusInterface $bus,
        ConfigWorkerRepository $configWorkerRepository,
        EntityManagerInterface $entityManager
        )
    {
        $this->logger = $logger;
        $this->operatingSystemRepository = $operatingSystemRepository;
        $this->serializer = $serializerInterface;
        $this->bus = $bus;
        $this->configWorkerRepository = $configWorkerRepository;
        $this->entityManager = $entityManager;
    }

    
	#[Get('/api/operating-systems', name: 'api_operating_systems')]
	#[IsGranted("ROLE_TEACHER_EDITOR", message: "Access denied.")]
    #[Route(path: '/admin/operating-systems', name: 'operating_systems')]
    public function indexAction(Request $request)
    {
        $search = $request->query->get('search', '');

        $criteria = Criteria::create()
            ->where(Criteria::expr()->contains('name', $search))
            ->orderBy([
                'name' => Criteria::ASC
            ]);

        $operatingSystems = $this->operatingSystemRepository->matching($criteria)->getValues();

        if ('json' === $request->getRequestFormat()) {
            return $this->json($operatingSystems, 200, [], ['api_get_operating_system']);
        }

        return $this->render('operating_system/index.html.twig', [
            'operatingSystems' => $operatingSystems,
            'search' => $search
        ]);
    }

    
	#[Get('/api/operating-systems/{id<\d+>}', name: 'api_get_operating_system')]
	#[IsGranted("ROLE_TEACHER_EDITOR", message: "Access denied.")]
    #[Route(path: '/admin/operating-systems/{id<\d+>}', name: 'show_operating_system')]
    public function showAction(Request $request, int $id)
    {
        if (!$operatingSystem = $this->operatingSystemRepository->find($id)) {
            throw new NotFoundHttpException("Operating system " . $id . " does not exist.");
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($operatingSystem, 200, [], [$request->get('_route')]);
        }

        $filename=$operatingSystem->getImageFilename();

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
        $operatingSystemEdited = $operatingSystem;

        $operatingSystemForm = $this->createForm(OperatingSystemType::class, $operatingSystemEdited);
        $operatingSystemForm->handleRequest($request);

        if ($operatingSystemForm->isSubmitted() && $operatingSystemForm->isValid()) {
            $operatingSystemEdited = $operatingSystemForm->getData();
            $image_filename_upload = $operatingSystemForm->get('imageFilename')->getData();
            $image_filename_modified=$operatingSystemForm->get('image_Filename')->getData();
//            $hypervisor=$operatingSystemForm->get('hypervisor')->getData();
            if ($image_filename_upload) {
                //Upload function return a modified image filename for security reason
                $new_ImageFileName = $imageFileUploader->upload($image_filename_upload);

                if (is_null($operatingSystemForm->get('image_Filename')->getData()) )
                //No custom filename is given
                    $operatingSystemEdited->setImageFilename($new_ImageFileName);
                else
                //Custom filename is given
                    $operatingSystemEdited->setImageFilename($image_filename_modified);
            }
            else {
                $operatingSystemEdited->setImageFilename($image_filename_modified);
            }

            $entityManager = $this->entityManager;
            $entityManager->persist($operatingSystemEdited);
            $entityManager->flush();

            //Send a message to change the name of the image on the worker filesystem

            $new_name_os=array(
                "old_name" => $operatingSystemFilename,
                "new_name" => $operatingSystemEdited->getImageFilename(),
                "hypervisor" => $operatingSystemEdited->getHypervisor()->getName()
            );

            $this->bus->dispatch(
                new InstanceActionMessage(json_encode($new_name_os), $operatingSystemEdited->getId(), InstanceActionMessage::ACTION_RENAMEOS)
            );
            $this->addFlash('success', 'Operating system has been edited.');
            return $this->redirectToRoute('show_operating_system', [
                        'id' => $id
            ]);

            /** @var OperatingSystem $operatingSystemEdited */
/*            $operatingSystemEdited = $operatingSystemForm->getData();
            $this->logger->debug("operatingSystemEdited Url:".$operatingSystemEdited->getImageUrl());
            $this->logger->debug("operatingSystemEdited filename:".$operatingSystemEdited->getName());
            $this->logger->debug("operatingSystemEdited upload_filename:".$operatingSystemEdited->getImageFilename());
            $upload_image_filename = $operatingSystemForm['upload_image_filename']->getData();
            $image_filename = $operatingSystemForm['image_filename']->getData();
            $imageUrl = $operatingSystemForm['imageUrl']->getData();
            $this->logger->debug("upload:".$upload_image_filename);
            $this->logger->debug("image_filename:".$image_filename);
            $this->logger->debug("imageUrl".$imageUrl);
*/
            /** @var UploadedFile|null $imageFile */
            //$imageFile = $operatingSystemForm['upload_image_filename']->getData();

          /*  if (!is_null($imageUrl) && !is_null($upload_image_filename)) {
                $this->logger->debug("url and file empty");
                $this->addFlash('danger', "You can't provide an image URL and an image file. Please provide only one.");
            } else {
                $this->logger->debug("url or file not empty");
                if (is_null($upload_image_filename))
                    $this->logger->debug("imagefile null");
                    else
                    $this->logger->debug("imagefile not null");
                if (is_null($imageUrl))
                    $this->logger->debug("imageUrl null");
                    else
                    $this->logger->debug("imageUrl not null");

                if ($upload_image_filename && strtolower($upload_image_filename->getClientOriginalExtension()) != 'img') {
                    $this->logger->debug("imagefile not empty and not img");
                    $this->addFlash('danger', "Only .img files are accepted.");
                } else {
                    if ($upload_image_filename) { */
                        //$imageFileName = $imageFileUploader->upload($upload_image_filename);
                /*        $operatingSystemEdited->setImageFilename($upload_image_filename);
                    } else {
                        if ($operatingSystemEdited->getImageUrl()) {
                            if ($operatingSystemFilename) {
                                try {
                                    $filesystem = new Filesystem();
                                    $filesystem->remove(
                                        $this->getParameter('image_directory') . '/' . $operatingSystemFilename
                                    );
                                } catch (IOExceptionInterface $exception) {
                                }
                            }

                            $operatingSystemEdited->setImageFilename(null);
                        } else {
                            $operatingSystemEdited->setImageFilename($operatingSystemFilename);
                            $operatingSystemEdited->setImageUrl(null);
                        }
                    }

                    $entityManager = $this->entityManager;
                    $entityManager->persist($operatingSystemEdited);
                    $entityManager->flush();

                    $this->addFlash('success', 'Operating system has been edited.');

                    return $this->redirectToRoute('show_operating_system', [
                        'id' => $id
                    ]);
                }
            }*/
        }

        return $this->render('operating_system/new.html.twig', [
            'operatingSystem' => $operatingSystem,
            'operatingSystemForm' => $operatingSystemForm->createView()
        ]);
    }

    #[Route(path: '/admin/operating-systems/{id<\d+>}/delete', name: 'delete_operating_system', methods: 'GET')]
    public function deleteAction($id)
    {
        $operatingSystem = $this->operatingSystemRepository->find($id);
        $operatingSystemName=$operatingSystem->getImageFilename();
        $operatingSystemHypervisor=$operatingSystem->getHypervisor()->getName();

        if (null === $operatingSystem) {
            throw new NotFoundHttpException("Operating system " . $id . " does not exist.");
        }

        $entityManager = $this->entityManager;
        $entityManager->remove($operatingSystem);

        try {
            $entityManager->flush();

            $this->addFlash('success', $operatingSystem->getName() . ' has been deleted.');

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

    public function cancel_renameos($names) {

    }

}
