<?php

namespace App\Repository;

use Remotelabz\NetworkBundle\Entity\Network;
use App\Entity\NetworkDevice;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @method Network|null find($id, $lockMode = null, $lockVersion = null)
 * @method Network|null findOneBy(array $criteria, array $orderBy = null)
 * @method Network[]    findAll()
 * @method Network[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NetworkDeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NetworkDevice::class);
    }

    public function findByLab($id)
    {
       return  $this->createQueryBuilder('n')
            ->andWhere('n.lab = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findByIdAndLab($id, $labId)
    {
       $network = $this->createQueryBuilder('n')
            ->andWhere('n.id = :id')
            ->andWhere('n.lab = :labId')
            ->setParameters(['id'=> $id, 'labId' => $labId])
            ->getQuery()
            ->getResult()
        ;

        return $network[0];
    }

    // /**
    //  * @return Network[] Returns an array of Network objects
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
    public function findOneBySomeField($value): ?Network
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
