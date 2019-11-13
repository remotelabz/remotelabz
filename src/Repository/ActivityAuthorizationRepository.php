<?php

namespace App\Repository;

use App\Entity\ActivityAuthorization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method ActivityAuthorization|null find($id, $lockMode = null, $lockVersion = null)
 * @method ActivityAuthorization|null findOneBy(array $criteria, array $orderBy = null)
 * @method ActivityAuthorization[]    findAll()
 * @method ActivityAuthorization[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActivityAuthorizationRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, ActivityAuthorization::class);
    }

    // /**
    //  * @return ActivityAuthorization[] Returns an array of ActivityAuthorization objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ActivityAuthorization
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
