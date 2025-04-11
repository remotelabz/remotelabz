<?php

namespace App\Repository;

use App\Entity\NetworkInterface;
use App\Entity\Device;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

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

    public function getVlans($id)
    {

        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT MAX(n.vlan) as vlan
            FROM App\Entity\NetworkInterface n
            LEFT JOIN n.device d
            LEFT JOIN d.labs l
            WHERE l.id = :id'
        )
        ->setParameter('id', $id);

        return $query->getResult();
    }

    public function getConnections($id)
    {

        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT MAX(n.connection) as connection
            FROM App\Entity\NetworkInterface n
            LEFT JOIN n.device d
            LEFT JOIN d.labs l
            WHERE l.id = :id'
        )
        ->setParameter('id', $id);

        return $query->getResult();
    }

    public function getTopology($id)
    {

        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT n.connection, n.vlan, GROUP_CONCAT(n.connectorLabel) as connectorsLabel, GROUP_CONCAT(n.connectorType) as connectors, GROUP_CONCAT(n.name) AS names, GROUP_CONCAT(d.id) AS devices
            FROM App\Entity\NetworkInterface n
            LEFT JOIN n.device d
            LEFT JOIN d.labs l
            WHERE l.id = :id
            GROUP BY n.connection, n.vlan'
        )
        ->setParameter('id', $id);

        return $query->getResult();
    }

    public function findByDeviceAndName($deviceId, $name)
    {
        $query = $this->createQueryBuilder('n')
            ->andWhere('n.device = :id')
            ->andWhere('n.name = :name')
            ->setParameter('id', $deviceId)
            ->setParameter('name', $name)
            ->getQuery()
        ;

        $networkInterface = $query->getResult();

        return $networkInterface[0];
    }

    public function findByLabAndVlan($labId, $vlan)
    {
        $entityManager = $this->getEntityManager();

        return $entityManager->createQuery(
            'SELECT n
            FROM App\Entity\NetworkInterface n
            LEFT JOIN n.device d
            LEFT JOIN d.labs l
            WHERE l.id = :id
            AND n.vlan = :vlan'
        )
        ->setParameter('id', $labId)
        ->setParameter('vlan', $vlan)
        ->getResult();
    }

    public function findByLabAndConnection($labId, $connection)
    {
        $entityManager = $this->getEntityManager();

        return $entityManager->createQuery(
            'SELECT n
            FROM App\Entity\NetworkInterface n
            LEFT JOIN n.device d
            LEFT JOIN d.labs l
            WHERE l.id = :id
            AND n.connection = :connection'
        )
        ->setParameter('id', $labId)
        ->setParameter('connection', $connection)
        ->getResult();
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
