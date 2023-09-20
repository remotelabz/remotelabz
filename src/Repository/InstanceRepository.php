<?php

namespace App\Repository;

use App\Entity\Lab;
use App\Entity\User;
use App\Entity\Instance;
use App\Entity\LabInstance;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @method Instance|null find($id, $lockMode = null, $lockVersion = null)
 * @method Instance|null findOneBy(array $criteria, array $orderBy = null)
 * @method Instance[]    findAll()
 * @method Instance[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InstanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Instance::class);
    }

    public function findByIdAndUser(int $id, User $user)
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.id = :id')
            ->setParameter('id', $id)
            ->andWhere('i.user = :user')
            ->setParameter('user', $user->getId())
            ->getQuery()
            ->getResult();
    }

    public function findByUserAndLab(User $user, Lab $lab): LabInstance
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.user = :user')
            ->setParameter('user', $user)
            ->andWhere('i.lab = :lab')
            ->setParameter('lab', $lab->getId())
            ->getQuery()
            ->getResult();
    }

    public function findByGroup($group)
    {
        $entityManager = $this->getEntityManager();
        
        $query = $entityManager->createQuery(
            'SELECT i
            FROM App\Entity\LabInstance i
            JOIN i.user u
            JOIN u._groups gu
            JOIN gu.group g
            JOIN g.labs l
            WHERE g = :group
            AND i.user = gu.user
            AND i.lab MEMBER OF g.labs'
        )
        ->setParameter('group', $group);

        return  $query->getResult();
    }

    public function findByGroupAndLabUuid($group, $lab)
    {
        $entityManager = $this->getEntityManager();
        
        $query = $entityManager->createQuery(
            'SELECT i
            FROM App\Entity\LabInstance i
            JOIN i.user u
            JOIN u._groups gu
            JOIN gu.group g
            JOIN g.labs l
            WHERE g = :group
            AND i.lab = :lab
            AND i.user = gu.user'
        )
        ->setParameter('group', $group)
        ->setParameter('lab', $lab);

        return  $query->getResult();
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
