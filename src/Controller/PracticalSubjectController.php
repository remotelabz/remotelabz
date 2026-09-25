<?php

namespace App\Controller;

use App\Entity\PracticalSubject;
use App\Repository\PracticalSubjectRepository;
use App\Service\PracticalSubjectFileUploader;
use Psr\Log\LoggerInterface;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Delete;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Http\Attribute\Security;
use Doctrine\ORM\EntityManagerInterface;

class PracticalSubjectController extends Controller
{
    /** @var LoggerInterface $logger */
    private $logger;

    /** @var PracticalSubjectRepository $practicalSubjectRepository */
    private $practicalSubjectRepository;

    private $entityManager;

    public function __construct(
        LoggerInterface $logger,
        PracticalSubjectRepository $practicalSubjectRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->logger = $logger;
        $this->practicalSubjectRepository = $practicalSubjectRepository;
        $this->entityManager = $entityManager;
    }

    private function canEdit(PracticalSubject $subject, UserInterface $user): bool
    {
        return $user->isAdministrator() || $subject->getAuthor() == $user;
    }

    #[Get('/api/practical-subjects', name: 'api_get_practical_subjects')]
    public function indexAction(Request $request)
    {
        $practicalSubjects = $this->practicalSubjectRepository->findAll();

        if ('json' === $request->getRequestFormat()) {
            return $this->json($practicalSubjects, 200, [], ['api_get_practical_subjects']);
        }

        throw new NotFoundHttpException();
    }

    #[Get('/api/practical-subjects/{id<\d+>}', name: 'api_get_practical_subject')]
    public function showAction(int $id, Request $request)
    {
        $practicalSubject = $this->practicalSubjectRepository->find($id);
        if (!$practicalSubject) {
            throw new NotFoundHttpException("Practical subject " . $id . " does not exist.");
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($practicalSubject, 200, [], ['api_get_practical_subject']);
        }

        throw new NotFoundHttpException();
    }

    #[Post('/api/practical-subjects', name: 'api_new_practical_subject')]
    #[Security("is_granted('ROLE_TEACHER') or is_granted('ROLE_ADMINISTRATOR')", message: "Access denied.")]
    public function newAction(Request $request, PracticalSubjectFileUploader $fileUploader)
    {
        $practicalSubject = new PracticalSubject();
        $practicalSubject->setAuthor($this->getUser());

        $uploadedFile = $request->files->get('file');
        if (null !== $uploadedFile) {
            $this->applyFile($practicalSubject, $uploadedFile, $request, $fileUploader);
        } else {
            $data = json_decode($request->getContent(), true) ?: [];
            if (empty($data['name'])) {
                throw new BadRequestHttpException("Field 'name' is required.");
            }
            $practicalSubject->setName($data['name']);
            if (isset($data['description'])) {
                $practicalSubject->setDescription($data['description']);
            }
        }

        $entityManager = $this->entityManager;
        $entityManager->persist($practicalSubject);
        $entityManager->flush();

        $this->logger->info("Practical subject " . $practicalSubject->getName() . " (" . $practicalSubject->getId() . ") created by " . $this->getUser()->getUserIdentifier());

        return new JsonResponse(['id' => $practicalSubject->getId(), 'uuid' => $practicalSubject->getUuid()], 201);
    }

    #[Put('/api/practical-subjects/{id<\d+>}', name: 'api_edit_practical_subject')]
    public function updateAction(int $id, Request $request, PracticalSubjectFileUploader $fileUploader)
    {
        $practicalSubject = $this->practicalSubjectRepository->find($id);
        if (!$practicalSubject) {
            throw new NotFoundHttpException("Practical subject " . $id . " does not exist.");
        }
        if (!$this->canEdit($practicalSubject, $this->getUser())) {
            throw new BadRequestHttpException("You are not allowed to edit this practical subject.");
        }

        $uploadedFile = $request->files->get('file');
        if (null !== $uploadedFile) {
            $this->applyFile($practicalSubject, $uploadedFile, $request, $fileUploader);
        } else {
            $data = json_decode($request->getContent(), true) ?: [];
            if (isset($data['name'])) {
                $practicalSubject->setName($data['name']);
            }
            if (isset($data['description'])) {
                $practicalSubject->setDescription($data['description']);
            }
        }
        $practicalSubject->setLastUpdated(new \DateTime());

        $entityManager = $this->entityManager;
        $entityManager->persist($practicalSubject);
        $entityManager->flush();

        $this->logger->info("Practical subject " . $practicalSubject->getName() . " (" . $practicalSubject->getId() . ") modified by " . $this->getUser()->getUserIdentifier());

        return new JsonResponse(['id' => $practicalSubject->getId(), 'uuid' => $practicalSubject->getUuid()], 200);
    }

    #[Delete('/api/practical-subjects/{id<\d+>}', name: 'api_delete_practical_subject')]
    public function deleteAction(int $id, PracticalSubjectFileUploader $fileUploader)
    {
        $practicalSubject = $this->practicalSubjectRepository->find($id);
        if (!$practicalSubject) {
            throw new NotFoundHttpException("Practical subject " . $id . " does not exist.");
        }
        if (!$this->canEdit($practicalSubject, $this->getUser())) {
            throw new BadRequestHttpException("You are not allowed to delete this practical subject.");
        }

        $fileUploader->setPracticalSubject($practicalSubject);
        $this->deleteFile($practicalSubject, $fileUploader);

        $entityManager = $this->entityManager;
        $entityManager->remove($practicalSubject);
        $entityManager->flush();

        $this->logger->info("Practical subject " . $practicalSubject->getName() . " (" . $practicalSubject->getId() . ") deleted by " . $this->getUser()->getUserIdentifier());

        return new JsonResponse(['code' => 200, 'status' => 'success'], 200);
    }

    #[Get('/api/practical-subjects/{id<\d+>}/pdf', name: 'api_get_practical_subject_pdf')]
    public function getSubjectPdfAction(int $id, PracticalSubjectFileUploader $fileUploader)
    {
        $practicalSubject = $this->practicalSubjectRepository->find($id);
        if (!$practicalSubject || !$practicalSubject->isPdf()) {
            throw new NotFoundHttpException("Practical subject PDF does not exist.");
        }
        if (!$this->canEdit($practicalSubject, $this->getUser()) && !$this->subjectIsVisible($practicalSubject)) {
            throw new NotFoundHttpException("Practical subject PDF does not exist.");
        }

        $fileUploader->setPracticalSubject($practicalSubject);
        $file = $fileUploader->getTargetDirectory().'/'.$practicalSubject->getPdfFilename();
        if (!is_file($file)) {
            throw new NotFoundHttpException("Practical subject PDF file does not exist.");
        }

        return $this->file($file, $practicalSubject->getId(), ResponseHeaderBag::DISPOSITION_INLINE);
    }

    /**
     * A subject is visible to a user if it is linked to a lab the user can see.
     */
    private function subjectIsVisible(PracticalSubject $practicalSubject): bool
    {
        foreach ($practicalSubject->getLabs() as $lab) {
            if ($this->isGranted('see', $lab)) {
                return true;
            }
        }

        return false;
    }

    private function applyFile(PracticalSubject $practicalSubject, $file, Request $request, PracticalSubjectFileUploader $fileUploader): void
    {
        $name = $request->request->get('name') ?? $request->query->get('name');
        $practicalSubject->setName($name ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        $extension = PracticalSubjectFileUploader::getFileExtension($file);
        if ('pdf' === $extension) {
            $practicalSubject->setContentType(PracticalSubject::CONTENT_TYPE_PDF);
        } else {
            $practicalSubject->setContentType(PracticalSubject::CONTENT_TYPE_MARKDOWN);
            $practicalSubject->setDescription(file_get_contents($file->getPathname()));
        }

        // Upload after the entity has an id if possible, so the file lives in its own directory
        $this->entityManager->persist($practicalSubject);
        $this->entityManager->flush();

        $fileUploader->setPracticalSubject($practicalSubject);
        $fileName = $fileUploader->upload($file);
        if ($practicalSubject->isPdf()) {
            $this->deleteFile($practicalSubject, $fileUploader);
            $practicalSubject->setPdfFilename($fileName);
        }
        $this->entityManager->flush();
    }

    private function deleteFile(PracticalSubject $practicalSubject, PracticalSubjectFileUploader $fileUploader): void
    {
        if (null === $practicalSubject->getPdfFilename()) {
            return;
        }
        $file = $fileUploader->getTargetDirectory().'/'.$practicalSubject->getPdfFilename();
        if (is_file($file)) {
            (new Filesystem())->remove($file);
        }
        $practicalSubject->setPdfFilename(null);
    }
}
