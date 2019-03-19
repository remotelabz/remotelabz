<?php

namespace App\Repository;

use App\Entity\HypervisorSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method HypervisorSettings|null find($id, $lockMode = null, $lockVersion = null)
 * @method HypervisorSettings|null findOneBy(array $criteria, array $orderBy = null)
 * @method HypervisorSettings[]    findAll()
 * @method HypervisorSettings[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HypervisorSettingsRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, HypervisorSettings::class);
    }

    // /**
    //  * @return HypervisorSettings[] Returns an array of HypervisorSettings objects
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
    public function findOneBySomeField($value): ?HypervisorSettings
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
