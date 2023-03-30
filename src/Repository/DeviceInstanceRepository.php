<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Device;
use App\Entity\DeviceInstance;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @method DeviceInstance|null find($id, $lockMode = null, $lockVersion = null)
 * @method DeviceInstance|null findOneBy(array $criteria, array $orderBy = null)
 * @method DeviceInstance[]    findAll()
 * @method DeviceInstance[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 * @method DeviceInstance[]    findByUserAndDevice(User $user, Device $device)
 */
class DeviceInstanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeviceInstance::class);
    }

    public function findByUserAndDevice(User $user, Device $device)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.user = :user')
            ->andWhere('l.device = :device')
            ->setParameter('user', $user)
            ->setParameter('device', $device)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findAllStartingOrStarted()
    {
        return $this->createQueryBuilder('l')
            ->where('l.state = :a')
            ->orWhere('l.state = :b')
            ->setParameter('a', 'starting')
            ->setParameter('b', 'started')
            ->getQuery()
            ->getResult()
        ;
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
