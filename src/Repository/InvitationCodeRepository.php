<?php

namespace App\Repository;

use App\Entity\InvitationCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Flavor|null find($id, $lockMode = null, $lockVersion = null)
 * @method Flavor|null findOneBy(array $criteria, array $orderBy = null)
 * @method Flavor[]    findAll()
 * @method Flavor[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InvitationCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvitationCode::class);
    }

    public function findByName($name)
    {
       $invitationCode = $this->createQueryBuilder('i')
            ->andWhere('i.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getResult()
        ;

        return $invitationCode[0];
    }

    public function findExpiredCodeInstances($now)
    {

        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT i.id as guest_id, i.uuid as guest_uuid, i.code, li.id as lab_instance_id, li.uuid as lab_instance_uuid, di.uuid as device_instance_uuid,
            di.id as device_instance_id, h.id as hypervisor_id
            FROM App\Entity\DeviceInstance di
            JOIN di.device d
            JOIN d.hypervisor h
            JOIN di.labInstance li
            JOIN di.guest i
            WHERE i.expiryDate < :now'
        )
        ->setParameter(':now', $now);

        return $query->getResult();

    }

    public function findExpiredCodes($now)
    {

        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT i.id as guest_id, i.uuid as guest_uuid
            FROM App\Entity\InvitationCode i
            WHERE i.expiryDate < :now'
        )
        ->setParameter(':now', $now);

        return $query->getResult();

    }

    public function findCodesGroupByLab($now, $name)
    {

        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT DISTINCT l.name as labName, l.id as labId, count(i.id) as numberOfCodes
            FROM App\Entity\InvitationCode i
            LEFT JOIN i.lab l
            WHERE l.name LIKE :name
            AND i.expiryDate > :now
            GROUP BY l.id, l.name
            ORDER BY numberOfCodes DESC'
        )
        ->setParameter(':name', '%'.$name.'%')
        ->setParameter(':now', $now);

        return $query->getResult();

    }

    public function findCodesByAuthorGroupAndLab($now, $name, $author)
    {

        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT DISTINCT l.name as labName, l.id as labId, count(i.id) as numberOfCodes
            FROM App\Entity\InvitationCode i
            LEFT JOIN i.lab l
            WHERE l.name LIKE :name
            AND l.author = :author
            AND i.expiryDate > :now
            GROUP BY l.id, l.name
            ORDER BY numberOfCodes DESC'
        )
        ->setParameter(':name', '%'.$name.'%')
        ->setParameter(':author', $author)
        ->setParameter(':now', $now);

        return $query->getResult();

    }
    // /**
    //  * @return InvitationCode[] Returns an array of InvitationCode objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?InvitationCode
    {
        return $this->createQueryBuilder(''c)
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
