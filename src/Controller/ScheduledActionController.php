<?php

namespace App\Controller;

use App\Entity\ScheduledAction;
use App\Repository\GroupRepository;
use App\Repository\LabRepository;
use App\Repository\ScheduledActionRepository;
use App\Service\Instance\ScheduledActionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\Security;

use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Delete;

/**
 * API REST pour la gestion des actions planifiées.
 *
 * Routes exposées :
 *   GET    /api/scheduled-actions              → liste (filtrée selon le rôle)
 *   POST   /api/scheduled-actions              → création
 *   GET    /api/scheduled-actions/{uuid}       → détail
 *   DELETE /api/scheduled-actions/{uuid}       → annulation
 *
 * Droits :
 *   - ROLE_ADMINISTRATOR : accès complet à toutes les planifications
 *   - ROLE_TEACHER       : accès à ses propres planifications uniquement
 *   - ROLE_USER          : accès refusé (403)
 *
 * Body JSON attendu pour la création :
 * {
 *   "labUuid":       "uuid-du-lab",           // obligatoire
 *   "groupUuid":     "uuid-du-groupe",        // optionnel — null = tous les utilisateurs du lab
 *   "action":        "start|stop|reset|leave",// obligatoire
 *   "scheduledAt":   "2026-04-01 08:00:00"    // obligatoire, format Y-m-d H:i:s ou ISO 8601
 * }
 */
class ScheduledActionController extends Controller
{
    public function __construct(
        private readonly ScheduledActionRepository $scheduledActionRepository,
        private readonly ScheduledActionService    $scheduledActionService,
        private readonly LabRepository             $labRepository,
        private readonly GroupRepository           $groupRepository,
        private readonly LoggerInterface           $logger,
    ) {}

    // =========================================================================
    // LISTE
    // =========================================================================

    #[Get('/api/scheduled-actions', name: 'api_scheduled_actions_list')]
    #[Security("is_granted('ROLE_TEACHER') or is_granted('ROLE_ADMINISTRATOR')", message: "Access denied.")]
    public function listAction(): JsonResponse
    {
        $user    = $this->getUser();
        $actions = $this->scheduledActionRepository->findForUser($user);

        return $this->json(array_map(
            fn(ScheduledAction $sa) => $this->serialize($sa),
            $actions
        ));
    }

    // =========================================================================
    // DÉTAIL
    // =========================================================================

    #[Get('/api/scheduled-actions/{uuid}', name: 'api_scheduled_actions_get')]
    #[Security("is_granted('ROLE_TEACHER') or is_granted('ROLE_ADMINISTRATOR')", message: "Access denied.")]
    public function getAction(string $uuid): JsonResponse
    {
        $sa = $this->findOrFail($uuid);
        $this->denyIfNotOwner($sa);

        return $this->json($this->serialize($sa));
    }

    // =========================================================================
    // CRÉATION
    // =========================================================================

    #[Post('/api/scheduled-actions', name: 'api_scheduled_actions_create')]
    #[Security("is_granted('ROLE_TEACHER') or is_granted('ROLE_ADMINISTRATOR')", message: "Access denied.")]
    public function createAction(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);

        if (!$body) {
            throw new BadRequestHttpException('Corps JSON invalide ou vide.');
        }

        // ── Validation des champs obligatoires ────────────────────────────────
        foreach (['labUuid', 'action', 'scheduledAt'] as $field) {
            if (empty($body[$field])) {
                throw new BadRequestHttpException("Champ obligatoire manquant : '$field'.");
            }
        }

        // ── Résolution du Lab ─────────────────────────────────────────────────
        $lab = $this->labRepository->findOneBy(['uuid' => $body['labUuid']]);
        if (!$lab) {
            throw new NotFoundHttpException("Lab introuvable : {$body['labUuid']}.");
        }

        // ── Vérification des droits sur le lab ────────────────────────────────
        // Un enseignant ne peut planifier que sur ses propres labs
        $user = $this->getUser();
        if (!$user->isAdministrator() && $lab->getAuthor()->getUuid() !== $user->getUuid()) {
            // Vérifier aussi s'il est dans un groupe qui a accès au lab
            $userGroupUuids = array_map(fn($g) => $g->getUuid(), $user->getGroupsInfo());
            $labGroupUuids  = array_map(fn($g) => $g->getUuid(), $lab->getGroups()->toArray());
            $hasAccess      = !empty(array_intersect($userGroupUuids, $labGroupUuids));

            if (!$hasAccess) {
                throw new AccessDeniedHttpException("Vous n'avez pas accès à ce lab.");
            }
        }

        // ── Résolution du Groupe (optionnel) ──────────────────────────────────
        $group = null;
        if (!empty($body['groupUuid'])) {
            $group = $this->groupRepository->findOneBy(['uuid' => $body['groupUuid']]);
            if (!$group) {
                throw new NotFoundHttpException("Groupe introuvable : {$body['groupUuid']}.");
            }
        }

        // ── Parsing de la date ────────────────────────────────────────────────
        try {
            $scheduledAt = new \DateTimeImmutable($body['scheduledAt']);
        } catch (\Exception) {
            throw new BadRequestHttpException(
                "Format de date invalide pour 'scheduledAt' : '{$body['scheduledAt']}'. " .
                "Utilisez le format ISO 8601 ou 'Y-m-d H:i:s'."
            );
        }

        // ── Création via le service ───────────────────────────────────────────
        try {
            $sa = $this->scheduledActionService->schedule(
                lab:         $lab,
                group:       $group,
                action:      $body['action'],
                scheduledAt: $scheduledAt,
                createdBy:   $user
            );
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        } catch (\LogicException $e) {
            // Doublon détecté
            return $this->json(['error' => $e->getMessage()], 409);
        }

        $this->logger->info(sprintf(
            '[ScheduledActionController] Planification créée — uuid=%s par %s',
            $sa->getUuid(), $user->getName()
        ));

        return $this->json($this->serialize($sa), 201);
    }

    // =========================================================================
    // ANNULATION
    // =========================================================================

    #[Delete('/api/scheduled-actions/{uuid}', name: 'api_scheduled_actions_cancel')]
    #[Security("is_granted('ROLE_TEACHER') or is_granted('ROLE_ADMINISTRATOR')", message: "Access denied.")]
    public function cancelAction(string $uuid): JsonResponse
    {
        $sa = $this->findOrFail($uuid);
        $this->denyIfNotOwner($sa);

        try {
            $this->scheduledActionService->cancel($sa);
        } catch (\LogicException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        return $this->json(['message' => "Planification {$uuid} annulée."]);
    }

    // =========================================================================
    // HELPERS PRIVÉS
    // =========================================================================

    private function findOrFail(string $uuid): ScheduledAction
    {
        $sa = $this->scheduledActionRepository->findOneBy(['uuid' => $uuid]);
        if (!$sa) {
            throw new NotFoundHttpException("Aucune planification avec l'UUID : $uuid.");
        }
        return $sa;
    }

    /**
     * Un enseignant ne peut accéder qu'à ses propres planifications.
     * Un administrateur accède à toutes.
     */
    private function denyIfNotOwner(ScheduledAction $sa): void
    {
        $user = $this->getUser();
        if ($user->isAdministrator()) {
            return;
        }
        if ($sa->getCreatedBy()?->getUuid() !== $user->getUuid()) {
            throw new AccessDeniedHttpException('Accès refusé à cette planification.');
        }
    }

    /**
     * Sérialisation manuelle pour éviter une dépendance JMS sur cette entité simple.
     */
    private function serialize(ScheduledAction $sa): array
    {
        return [
            'uuid'            => $sa->getUuid(),
            'lab'             => [
                'uuid' => $sa->getLab()->getUuid(),
                'name' => $sa->getLab()->getName(),
            ],
            'group'           => $sa->getGroup() ? [
                'uuid' => $sa->getGroup()->getUuid(),
                'name' => $sa->getGroup()->getName(),
            ] : null,
            'action'          => $sa->getAction(),
            'scheduledAt'     => $sa->getScheduledAt()->format(\DateTimeInterface::ATOM),
            'executedAt'      => $sa->getExecutedAt()?->format(\DateTimeInterface::ATOM),
            'status'          => $sa->getStatus(),
            'errorMessage'    => $sa->getErrorMessage(),
            'executionReport' => $sa->getExecutionReport(),
            'createdBy'       => $sa->getCreatedBy() ? [
                'uuid' => $sa->getCreatedBy()->getUuid(),
                'name' => $sa->getCreatedBy()->getName(),
            ] : null,
            'createdAt'       => $sa->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}