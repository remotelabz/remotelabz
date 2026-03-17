<?php

namespace App\Repository;

use App\Entity\ScheduledAction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ScheduledAction>
 */
class ScheduledActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScheduledAction::class);
    }

    /**
     * Retourne toutes les actions dont la date est dépassée et le statut = pending.
     * Appelé par le runner chaque minute.
     *
     * @return ScheduledAction[]
     */
    public function findDue(\DateTimeInterface $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        return $this->createQueryBuilder('sa')
            ->where('sa.status = :status')
            ->andWhere('sa.scheduledAt <= :now')
            ->setParameter('status', ScheduledAction::STATUS_PENDING)
            ->setParameter('now', $now)
            ->orderBy('sa.scheduledAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les planifications visibles pour un utilisateur donné.
     * - Les admins voient tout.
     * - Les enseignants voient leurs propres planifications.
     *
     * @return ScheduledAction[]
     */
    public function findForUser(\App\Entity\User $user, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('sa')
            ->orderBy('sa.scheduledAt', 'DESC')
            ->setMaxResults($limit);

        if (!$user->isAdministrator()) {
            $qb->where('sa.createdBy = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne les planifications en attente pour un lab et un groupe donnés.
     * Utile pour détecter les doublons avant de persister.
     *
     * @return ScheduledAction[]
     */
    public function findPendingForLabAndGroup(
        \App\Entity\Lab $lab,
        ?\App\Entity\Group $group,
        string $action
    ): array {
        $qb = $this->createQueryBuilder('sa')
            ->where('sa.lab = :lab')
            ->andWhere('sa.action = :action')
            ->andWhere('sa.status = :status')
            ->setParameter('lab', $lab)
            ->setParameter('action', $action)
            ->setParameter('status', ScheduledAction::STATUS_PENDING);

        if ($group !== null) {
            $qb->andWhere('sa.group = :group')->setParameter('group', $group);
        } else {
            $qb->andWhere('sa.group IS NULL');
        }

        return $qb->getQuery()->getResult();
    }
}