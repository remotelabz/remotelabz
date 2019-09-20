<?php

namespace App\Repository;

use App\Entity\DeviceInstance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method DeviceInstance|null find($id, $lockMode = null, $lockVersion = null)
 * @method DeviceInstance|null findOneBy(array $criteria, array $orderBy = null)
 * @method DeviceInstance[]    findAll()
 * @method DeviceInstance[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DeviceInstanceRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, DeviceInstance::class);
    }

    // /**
    //  * @return DeviceInstance[] Returns an array of DeviceInstance objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?DeviceInstance
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
