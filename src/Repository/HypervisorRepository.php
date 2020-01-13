<?php

namespace App\Repository;

use App\Entity\Hypervisor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Hypervisor|null find($id, $lockMode = null, $lockVersion = null)
 * @method Hypervisor|null findOneBy(array $criteria, array $orderBy = null)
 * @method Hypervisor[]    findAll()
 * @method Hypervisor[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HypervisorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Hypervisor::class);
    }

    // /**
    //  * @return Hypervisor[] Returns an array of Hypervisor objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('h.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Hypervisor
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
