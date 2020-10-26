<?php

namespace App\Controller;

use App\Entity\OperatingSystem;

use App\Form\OperatingSystemType;
use App\Service\ImageFileUploader;
use FOS\RestBundle\Context\Context;
use JMS\Serializer\SerializerInterface;
use Doctrine\Common\Collections\Criteria;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Component\Filesystem\Filesystem;
use App\Repository\OperatingSystemRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OperatingSystemController extends Controller
{
    /**
     * @var OperatingSystemRepository
     */
    private $operatingSystemRepository;

    public function __construct(OperatingSystemRepository $operatingSystemRepository)
    {
        $this->operatingSystemRepository = $operatingSystemRepository;
    }

    /**
     * @Route("/admin/operating-systems", name="operating_systems")
     * 
     * @Rest\Get("/api/operating-systems", name="api_operating_systems")
     */
    public function indexAction(Request $request)
    {
        $search = $request->query->get('search', '');

        $criteria = Criteria::create()
            ->where(Criteria::expr()->contains('name', $search))
            ->orderBy([
                'id' => Criteria::DESC
            ]);

        $operatingSystems = $this->operatingSystemRepository->matching($criteria)->getValues();

        if ('json' === $request->getRequestFormat()) {
            return $this->json($operatingSystems);
        }

        return $this->render('operating_system/index.html.twig', [
            'operatingSystems' => $operatingSystems,
            'search' => $search
        ]);
    }

    /**
     * @Route("/admin/operating-systems/{id<\d+>}", name="show_operating_system")
     */
    public function showAction(Request $request, int $id)
    {
        if (!$operatingSystem = $this->operatingSystemRepository->find($id)) {
            throw new NotFoundHttpException();
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($operatingSystem);
        }

        return $this->render('operating_system/view.html.twig', [
            'operatingSystem' => $operatingSystem
        ]);
    }

    /**
     * @Route("/admin/operating-systems/new", name="new_operating_system")
     */
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
            } else {
                /** @var UploadedFile|null $imageFile */
                $imageFile = $operatingSystemForm['imageFilename']->getData();
                if ($imageFile && strtolower($imageFile->getClientOriginalExtension()) != 'img') {
                    $this->addFlash('danger', "Only .img files are accepted.");
                } else {
                    if ($imageFile) {
                        $imageFileName = $imageFileUploader->upload($imageFile);
                        $operatingSystem->setImageFilename($imageFileName);
                    }

                    $entityManager = $this->getDoctrine()->getManager();
                    $entityManager->persist($operatingSystem);
                    $entityManager->flush();

                    $this->addFlash('success', 'Operating system has been created.');

                    return $this->redirectToRoute('operating_systems');
                }
            }
        }

        return $this->render('operating_system/new.html.twig', [
            'operatingSystemForm' => $operatingSystemForm->createView(),
        ]);
    }

    /**
     * @Route("/admin/operating-systems/{id<\d+>}/edit", name="edit_operating_system", methods={"GET", "POST"})
     */
    public function editAction(Request $request, int $id, ImageFileUploader $imageFileUploader)
    {
        $operatingSystem = $this->operatingSystemRepository->find($id);

        if (null === $operatingSystem) {
            throw new NotFoundHttpException();
        }

        $operatingSystemFilename = $operatingSystem->getImageFilename();
        $operatingSystemEdited = $operatingSystem;

        if ($operatingSystemFilename !== null) {
            $operatingSystemEdited->setImageFilename(
                $this->getParameter('image_directory') . '/' . $operatingSystem->getImageFilename()
            );
        }

        $operatingSystemForm = $this->createForm(OperatingSystemType::class, $operatingSystemEdited);
        $operatingSystemForm->handleRequest($request);

        if ($operatingSystemForm->isSubmitted() && $operatingSystemForm->isValid()) {
            /** @var OperatingSystem $operatingSystemEdited */
            $operatingSystemEdited = $operatingSystemForm->getData();

            /** @var UploadedFile|null $imageFile */
            $imageFile = $operatingSystemForm['imageFilename']->getData();

            if ($operatingSystemEdited->getImageUrl() !== null && $imageFile !== null) {
                $this->addFlash('danger', "You can't provide an image URL and an image file. Please provide only one.");
            } else {
                if ($imageFile && strtolower($imageFile->getClientOriginalExtension()) != 'img') {
                    $this->addFlash('danger', "Only .img files are accepted.");
                } else {
                    if ($imageFile) {
                        $imageFileName = $imageFileUploader->upload($imageFile);
                        $operatingSystemEdited->setImageFilename($imageFileName);
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

                    $entityManager = $this->getDoctrine()->getManager();
                    $entityManager->persist($operatingSystemEdited);
                    $entityManager->flush();

                    $this->addFlash('success', 'Operating system has been edited.');

                    return $this->redirectToRoute('show_operating_system', [
                        'id' => $id
                    ]);
                }
            }
        }

        return $this->render('operating_system/new.html.twig', [
            'operatingSystem' => $operatingSystem,
            'operatingSystemForm' => $operatingSystemForm->createView()
        ]);
    }

    /**
     * @Route("/admin/operating-systems/{id<\d+>}/delete", name="delete_operating_system", methods="GET")
     */
    public function deleteAction($id, ImageFileUploader $imageFileUploader)
    {
        $operatingSystem = $this->operatingSystemRepository->find($id);
        $filesystem = new Filesystem();

        if (null === $operatingSystem) {
            throw new NotFoundHttpException();
        }

        if (null !== $operatingSystem->getImageFilename()) {
            try {
                $filesystem->remove($imageFileUploader->getTargetDirectory() . '/' . $operatingSystem->getImageFilename());
            } catch (IOExceptionInterface $exception) {
                throw $exception;
            }
        }

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($operatingSystem);

        try {
            $entityManager->flush();

            $this->addFlash('success', $operatingSystem->getName() . ' has been deleted.');

            return $this->redirectToRoute('operating_systems');
        } catch (ForeignKeyConstraintViolationException $e) {
            $this->addFlash('danger', 'This operating system is still used in some device templates or device instances. Please delete them first.');

            return $this->redirectToRoute('show_operating_system', [
                'id' => $id
            ]);
        }
    }
}
