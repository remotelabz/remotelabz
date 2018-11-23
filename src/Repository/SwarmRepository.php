<?php

namespace App\Repository;

use App\Entity\Swarm;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method Swarm|null find($id, $lockMode = null, $lockVersion = null)
 * @method Swarm|null findOneBy(array $criteria, array $orderBy = null)
 * @method Swarm[]    findAll()
 * @method Swarm[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SwarmRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Swarm::class);
    }

    // /**
    //  * @return Swarm[] Returns an array of Swarm objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Swarm
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
