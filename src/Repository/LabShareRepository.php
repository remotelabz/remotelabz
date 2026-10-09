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
     * All the share rules of a lab inside one group, whatever its side
     * ((lab, *, group) and (*, lab, group)).
     *
     * @return LabShare[]
     */
    public function findForLabInGroup(Lab $lab, Group $group): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.group = :group')
            ->andWhere('s.lab = :lab OR s.sharedWith = :lab')
            ->setParameter('group', $group)
            ->setParameter('lab', $lab)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * The groups owning at least one share rule of the given lab.
     *
     * @return Group[]
     */
    public function findGroupsWithLab(Lab $lab): array
    {
        $groups = $this->createQueryBuilder('s')
            ->select('DISTINCT g')
            ->join('s.group', 'g')
            ->andWhere('s.lab = :lab OR s.sharedWith = :lab')
            ->setParameter('lab', $lab)
            ->getQuery()
            ->getResult()
        ;

        return $groups;
    }
}
