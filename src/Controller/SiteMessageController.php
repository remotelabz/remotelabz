<?php

namespace App\Controller;

use App\Entity\SiteMessage;
use App\Form\SiteMessageType;
use App\Repository\GroupRepository;
use App\Repository\SiteMessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use SortDirection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/messages')]
#[IsGranted("ROLE_TEACHER_EDITOR", message: "Access denied.")]
class SiteMessageController extends AbstractController
{
    use InstanceErrorHandlerTrait;

    private SiteMessageRepository $siteMessageRepository;
    private UserRepository $userRepository;
    private GroupRepository $groupRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        SiteMessageRepository $siteMessageRepository,
        UserRepository $userRepository,
        GroupRepository $groupRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->siteMessageRepository = $siteMessageRepository;
        $this->userRepository = $userRepository;
        $this->groupRepository = $groupRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * The general message is only editable by administrators.
     */
    private function canManage(SiteMessage $message): bool
    {
        if ($message->isGeneral()) {
            return $this->isGranted('ROLE_ADMINISTRATOR');
        }
        return true;
    }

    #[Route('/', name: 'app_site_message_index', methods: ['GET'])]
    public function index(): Response
    {
        $messages = $this->siteMessageRepository
            ->createQueryBuilder('m')
            ->orderBy('m.type', SortDirection::Ascending)
            ->addOrderBy('m.createdAt', SortDirection::Descending)
            ->getQuery()
            ->getResult();

        return $this->render('site_message/index.html.twig', [
            'messages' => $messages,
            'is_admin' => $this->isGranted('ROLE_ADMINISTRATOR'),
        ]);
    }

    #[Route('/new', name: 'app_site_message_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $isAdmin = $this->isGranted('ROLE_ADMINISTRATOR');
        $message = new SiteMessage();
        $form = $this->createForm(SiteMessageType::class, $message, ['admin' => $isAdmin]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->sanitize($message, $isAdmin);
            $this->applyExpiryOffset($message);
            $message->setCreatedBy($this->getUser() ? $this->getUser()->getEmail() : null);
            $message->setCreatedAt(new \DateTime());
            $message->setUpdatedAt(new \DateTime());

            $this->entityManager->persist($message);
            $this->entityManager->flush();

            $this->addFlashMsgSuccess($request->getSession(), 'Message created successfully.');
            return $this->redirectToRoute('app_site_message_index');
        }

        return $this->render('site_message/new.html.twig', [
            'form' => $form,
            'is_admin' => $isAdmin,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_site_message_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SiteMessage $message): Response
    {
        if (!$this->canManage($message)) {
            throw $this->createAccessDeniedException('Only administrators can edit the general message.');
        }

        $this->hydrateTargets($message);

        $isAdmin = $this->isGranted('ROLE_ADMINISTRATOR');
        $form = $this->createForm(SiteMessageType::class, $message, ['admin' => $isAdmin]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->sanitize($message, $isAdmin);
            $this->applyExpiryOffset($message);
            $message->setUpdatedAt(new \DateTime());

            $this->entityManager->flush();

            $this->addFlashMsgSuccess($request->getSession(), 'Message updated successfully.');
            return $this->redirectToRoute('app_site_message_index');
        }

        return $this->render('site_message/edit.html.twig', [
            'form' => $form,
            'message' => $message,
            'is_admin' => $isAdmin,
        ]);
    }

    #[Route('/{id}/toggle', name: 'app_site_message_toggle', methods: ['POST'])]
    public function toggle(Request $request, SiteMessage $message): Response
    {
        if (!$this->canManage($message)) {
            throw $this->createAccessDeniedException('Only administrators can modify the general message.');
        }
        if (!$this->isCsrfTokenValid('toggle'.$message->getId(), $request->request->get('_token'))) {
            $this->addFlashMsgError($request->getSession(), 'Invalid CSRF token.');
            return $this->redirectToRoute('app_site_message_index');
        }

        $message->setActive(!$message->isActive());
        $message->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        $this->addFlashMsgSuccess($request->getSession(), 'Message ' . ($message->isActive() ? 'activated' : 'deactivated') . '.');
        return $this->redirectToRoute('app_site_message_index');
    }

    #[Route('/{id}/delete', name: 'app_site_message_delete', methods: ['POST'])]
    public function delete(Request $request, SiteMessage $message): Response
    {
        if (!$this->canManage($message)) {
            throw $this->createAccessDeniedException('Only administrators can delete the general message.');
        }
        if (!$this->isCsrfTokenValid('delete'.$message->getId(), $request->request->get('_token'))) {
            $this->addFlashMsgError($request->getSession(), 'Invalid CSRF token.');
            return $this->redirectToRoute('app_site_message_index');
        }

        $this->entityManager->remove($message);
        $this->entityManager->flush();

        $this->addFlashMsgSuccess($request->getSession(), 'Message deleted successfully.');
        return $this->redirectToRoute('app_site_message_index');
    }

    /**
     * Load the targeted users and groups so the form can display them.
     */
    private function hydrateTargets(SiteMessage $message): void
    {
        if (!empty($message->getTargetUserIds())) {
            $message->setTargetUsers($this->userRepository->findBy(['id' => $message->getTargetUserIds()]));
        }
        if (!empty($message->getTargetGroupIds())) {
            $message->setTargetGroups($this->groupRepository->findBy(['id' => $message->getTargetGroupIds()]));
        }
    }

    /**
     * The expiry date is submitted in the browser's local timezone together
     * with its UTC offset (in minutes). Convert it to UTC before storing,
     * as the application logic runs in UTC.
     */
    private function applyExpiryOffset(SiteMessage $message): void
    {
        $offsetMinutes = $message->getExpiresAtOffset();
        $message->setExpiresAtOffset(null);

        $expiresAt = $message->getExpiresAt();
        if (null === $expiresAt || null === $offsetMinutes) {
            return;
        }

        $utc = clone $expiresAt;
        $utc->modify(($offsetMinutes >= 0 ? '-' : '+').abs($offsetMinutes).' minutes');
        $message->setExpiresAt($utc);
    }

    /**
     * General messages are shown to everyone: clear targeting and title.
     */
    private function sanitize(SiteMessage $message, bool $isAdmin): void
    {
        if (!$isAdmin) {
            // Editors can never manage the general message
            $message->setType(SiteMessage::TYPE_INFORMATION);
        }
        if ($message->isGeneral()) {
            $message->setTitle(null);
            $message->setTargetUsers(null);
            $message->setTargetGroups(null);
        }
    }
}
