<?php

namespace App\Repository;

use App\Entity\Lab;
use App\Entity\User;
use App\Entity\LabInstance;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @method LabInstance|null find($id, $lockMode = null, $lockVersion = null)
 * @method LabInstance|null findOneBy(array $criteria, array $orderBy = null)
 * @method LabInstance[]    findAll()
 * @method LabInstance[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 * @method LabInstance|null findByUserAndLab(User $user, Lab $lab)
 */
class LabInstanceRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, LabInstance::class);
    }

    public function findByUserAndLab(User $user, Lab $lab)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.user = :user')
            ->andWhere('l.lab = :lab')
            ->setParameter('user', $user)
            ->setParameter('lab', $lab)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    // /**
    //  * @return LabInstance[] Returns an array of LabInstance objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('l.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?LabInstance
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
