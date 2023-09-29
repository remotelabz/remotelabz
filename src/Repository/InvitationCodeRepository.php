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
       $flavor = $this->createQueryBuilder('f')
            ->andWhere('f.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getResult()
        ;

        return $flavor[0];
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
