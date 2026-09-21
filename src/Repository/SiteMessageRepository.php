<?php

namespace App\Repository;

use App\Entity\SiteMessage;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<SiteMessage>
 */
class SiteMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteMessage::class);
    }

    /**
     * Find the active general message displayed on the login page.
     */
    public function findActiveGeneral(): ?SiteMessage
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.type = :type')
            ->andWhere('m.active = :active')
            ->andWhere('m.expiresAt IS NULL OR m.expiresAt > :now')
            ->setParameter('type', SiteMessage::TYPE_GENERAL)
            ->setParameter('active', true)
            ->setParameter('now', new \DateTime())
            ->orderBy('m.updatedAt', SortDirection::Descending)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all active information messages targeted at the given user
     * (by user id or by group, including parent groups).
     *
     * @return SiteMessage[]
     */
    public function findActiveForUser(User $user): array
    {
        $messages = $this->createQueryBuilder('m')
            ->andWhere('m.type = :type')
            ->andWhere('m.active = :active')
            ->andWhere('m.expiresAt IS NULL OR m.expiresAt > :now')
            ->setParameter('type', SiteMessage::TYPE_INFORMATION)
            ->setParameter('active', true)
            ->setParameter('now', new \DateTime())
            ->orderBy('m.createdAt', SortDirection::Descending)
            ->getQuery()
            ->getResult();

        $groupIds = $this->getUserGroupIdsWithParents($user);

        return array_values(array_filter($messages, function (SiteMessage $message) use ($user, $groupIds) {
            return $message->targetsUser((int) $user->getId(), $groupIds);
        }));
    }

    /**
     * Get the ids of the groups the user belongs to, including all parent groups.
     *
     * @return int[]
     */
    public function getUserGroupIdsWithParents(User $user): array
    {
        $groupIds = [];
        foreach ($user->getGroupsInfo() as $group) {
            $current = $group;
            while (null !== $current) {
                $groupIds[(int) $current->getId()] = (int) $current->getId();
                $current = $current->getParent();
            }
        }
        return array_values($groupIds);
    }
}
