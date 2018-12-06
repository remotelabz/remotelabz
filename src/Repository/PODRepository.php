<?php

namespace App\Repository;

use App\Entity\POD;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method POD|null find($id, $lockMode = null, $lockVersion = null)
 * @method POD|null findOneBy(array $criteria, array $orderBy = null)
 * @method POD[]    findAll()
 * @method POD[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PODRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, POD::class);
    }

    // /**
    //  * @return POD[] Returns an array of POD objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?POD
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
