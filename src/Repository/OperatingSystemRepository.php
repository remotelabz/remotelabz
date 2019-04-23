<?php

namespace App\Repository;

use App\Entity\OperatingSystem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method OperatingSystem|null find($id, $lockMode = null, $lockVersion = null)
 * @method OperatingSystem|null findOneBy(array $criteria, array $orderBy = null)
 * @method OperatingSystem[]    findAll()
 * @method OperatingSystem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OperatingSystemRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, OperatingSystem::class);
    }

    // /**
    //  * @return OperatingSystem[] Returns an array of OperatingSystem objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?OperatingSystem
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
