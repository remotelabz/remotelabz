<?php

namespace App\Repository;

use App\Entity\NetworkInterfaceInstance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method NetworkInterfaceInstance|null find($id, $lockMode = null, $lockVersion = null)
 * @method NetworkInterfaceInstance|null findOneBy(array $criteria, array $orderBy = null)
 * @method NetworkInterfaceInstance[]    findAll()
 * @method NetworkInterfaceInstance[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NetworkInterfaceInstanceRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, NetworkInterfaceInstance::class);
    }

    // /**
    //  * @return NetworkInterfaceInstance[] Returns an array of NetworkInterfaceInstance objects
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
    public function findOneBySomeField($value): ?NetworkInterfaceInstance
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
