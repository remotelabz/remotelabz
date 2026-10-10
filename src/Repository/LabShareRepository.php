<?php

namespace App\Repository;

use App\Entity\Group;
use App\Entity\Lab;
use App\Entity\LabShare;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method LabShare|null find($id, $lockMode = null, $lockVersion = null)
 * @method LabShare|null findOneBy(array $criteria, array $orderBy = null)
 * @method LabShare[]    findAll()
 * @method LabShare[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LabShareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LabShare::class);
    }

    /**
     * The share rules of a group where the given lab is the target
     * ((*, lab, group)). A source lab is not required to be available in the
     * group it is shared with, so only the target side is purged when a lab
     * leaves a group.
     *
     * @return LabShare[]
     */
    public function findAsTargetInGroup(Lab $lab, Group $group): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.group = :group')
            ->andWhere('s.sharedWith = :lab')
            ->setParameter('group', $group)
            ->setParameter('lab', $lab)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * The groups where the given lab is shared, either as source or as target.
     *
     * @return Group[]
     */
    public function findGroupsWithLab(Lab $lab): array
    {
        $groups = [];

        foreach (array_merge($this->findBy(['lab' => $lab]), $this->findBy(['sharedWith' => $lab])) as $share) {
            $group = $share->getGroup();
            if (is_null($group)) {
                continue;
            }
            $groups[(string) $group->getUuid()] = $group;
        }

        return array_values($groups);
    }
}
