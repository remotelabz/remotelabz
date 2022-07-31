<?php

namespace App\Repository;

use App\Entity\Flavor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Flavor|null find($id, $lockMode = null, $lockVersion = null)
 * @method Flavor|null findOneBy(array $criteria, array $orderBy = null)
 * @method Flavor[]    findAll()
 * @method Flavor[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FlavorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Flavor::class);
    }

    // /**
    //  * @return Flavor[] Returns an array of Flavor objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('f.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Flavor
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
