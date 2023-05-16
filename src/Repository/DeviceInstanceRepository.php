<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Device;
use App\Entity\Lab;
use App\Entity\DeviceInstance;
use App\Entity\LabInstance;
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

    public function findByUserDeviceAndLab(User $user, Device $device, Lab $lab)
    {

        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT di 
            FROM App\Entity\DeviceInstance di
            LEFT JOIN di.labInstance li
            WHERE di.user = :user
            AND di.device = :device
            AND li.lab = :lab'
        )
        ->setParameter('user', $user)
        ->setParameter('device', $device)
        ->setParameter('lab', $lab);

        $deviceInstance = $query->getResult();
        //return $query->getResult();

        // returns an array of Product objects
        return $deviceInstance[0];
    }

    public function findByDeviceAndLabInstance(Device $device, LabInstance $lab)
    {

        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT di 
            FROM App\Entity\DeviceInstance di
            WHERE di.device = :device
            AND di.labInstance = :lab'
        )
        ->setParameter('device', $device)
        ->setParameter('lab', $lab);

        $deviceInstance = $query->getResult();
        //return $query->getResult();

        // returns an array of Product objects
        return $deviceInstance[0];
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
    public function findByLabInstance($lab)
    {

        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT di.device
            FROM App\Entity\DeviceInstance di
            WHERE di.labInstance = :lab'
        )->setParameter('lab', $lab);

        return $query->getResult();
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
