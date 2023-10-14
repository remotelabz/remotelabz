<?php

namespace App\Repository;

use App\Entity\NetworkSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method NetworkSettings|null find($id, $lockMode = null, $lockVersion = null)
 * @method NetworkSettings|null findOneBy(array $criteria, array $orderBy = null)
 * @method NetworkSettings[]    findAll()
 * @method NetworkSettings[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NetworkSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NetworkSettings::class);
    }

    // /**
    //  * @return NetworkSettings[] Returns an array of NetworkSettings objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('n.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?NetworkSettings
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
