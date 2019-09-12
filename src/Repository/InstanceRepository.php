<?php

namespace App\Repository;

use App\Entity\Instance;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @method Instance|null find($id, $lockMode = null, $lockVersion = null)
 * @method Instance|null findOneBy(array $criteria, array $orderBy = null)
 * @method Instance[]    findAll()
 * @method Instance[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InstanceRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Instance::class);
    }

    public function findByIdAndUser($id, $user)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.id = :id')
            ->setParameter('id', $id->getId())
            ->andWhere('i.user = :user')
            ->setParameter('user', $user->getId())
            ->getQuery()
            ->getResult()
        ;
    }

    public function findByUserAndLab($user, $lab)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.user = :user')
            ->setParameter('user', $user)
            ->andWhere('i.lab = :lab')
            ->setParameter('lab', $lab->getId())
            ->getQuery()
            ->getResult()
        ;
    }

    // /**
    //  * @return Instance[] Returns an array of Instance objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('i.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Instance
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
