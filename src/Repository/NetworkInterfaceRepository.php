<?php

namespace App\Repository;

use App\Entity\NetworkInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method NetworkInterface|null find($id, $lockMode = null, $lockVersion = null)
 * @method NetworkInterface|null findOneBy(array $criteria, array $orderBy = null)
 * @method NetworkInterface[]    findAll()
 * @method NetworkInterface[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NetworkInterfaceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NetworkInterface::class);
    }

    public function findByDeviceId($id)
    {
        return  $this->createQueryBuilder('n')
            ->andWhere('n.device = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult()
        ;
    }

    // /**
    //  * @return NetworkInterface[] Returns an array of NetworkInterface objects
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
    public function findOneBySomeField($value): ?NetworkInterface
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
