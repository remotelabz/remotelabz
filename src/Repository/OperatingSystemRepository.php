<?php

namespace App\Repository;

use App\Entity\OperatingSystem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method OperatingSystem|null find($id, $lockMode = null, $lockVersion = null)
 * @method OperatingSystem|null findOneBy(array $criteria, array $orderBy = null)
 * @method OperatingSystem[]    findAll()
 * @method OperatingSystem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OperatingSystemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OperatingSystem::class);
    }

    public function findByName($name)
    {
       $operatingSystem = $this->createQueryBuilder('o')
            ->andWhere('o.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getResult()
        ;

        if ($operatingSystem == NULL) {
            return null;
        }
        return $operatingSystem[0];
    }

    public function findByVirtuality($virtuality)
    {
        if ($virtuality == 1) {
            $operatingSystems = $this->createQueryBuilder('o')
            ->join('o.hypervisor', 'h')
            ->where('h.name != :name')
            ->setParameter('name', 'physical')
            ->getQuery()
            ->getResult()
        ;
        }
        else {
            $operatingSystems = $this->createQueryBuilder('o')
            ->join('o.hypervisor', 'h')
            ->where('h.name = :name')
            ->setParameter('name', 'physical')
            ->getQuery()
            ->getResult()
        ;
        }

        return $operatingSystems;
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
