<?php

namespace App\Repository;

use App\Entity\Lab;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Lab|null find($id, $lockMode = null, $lockVersion = null)
 * @method Lab|null findOneBy(array $criteria, array $orderBy = null)
 * @method Lab[]    findAll()
 * @method Lab[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LabRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lab::class);
    }

    public function findByNameLike($value)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.name LIKE :val')
            ->setParameter('val', '%'.$value.'%')
            ->orderBy('l.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    // /**
    //  * @return Lab[] Returns an array of Lab objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('l.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Lab
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
