<?php

namespace App\Repository;

use App\Entity\IP;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method IP|null find($id, $lockMode = null, $lockVersion = null)
 * @method IP|null findOneBy(array $criteria, array $orderBy = null)
 * @method IP[]    findAll()
 * @method IP[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class IPRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IP::class);
    }

    // /**
    //  * @return IP[] Returns an array of IP objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('i.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?IP
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
