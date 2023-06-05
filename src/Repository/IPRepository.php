<?php

namespace App\Repository;

use Remotelabz\NetworkBundle\Entity\IP;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

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

    /**
     * Returns all IP between $from and $to (inclusive)
     *
     * @param IP $from First IP to find.
     * @param IP $to Last IP from range. This IP is included in results if exists.
     * @return IP[]
     */
    public function findAllBetween(IP $from, IP $to)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.long BETWEEN ' . $from->getLong() . ' AND ' . $to->getLong())
            ->orderBy('i.long', 'ASC')
            ->getQuery()
            ->getResult();
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
