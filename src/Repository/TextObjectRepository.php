<?php

namespace App\Repository;

use App\Entity\TextObject;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @method TextObject|null find($id, $lockMode = null, $lockVersion = null)
 * @method TextObject|null findOneBy(array $criteria, array $orderBy = null)
 * @method TextObject[]    findAll()
 * @method TextObject[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TextObjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TextObject::class);
    }

    public function findByNameLike($value)
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.name LIKE :val')
            ->setParameter('val', '%'.$value.'%')
            ->orderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findByLab($id)
    {
       return  $this->createQueryBuilder('o')
            ->andWhere('o.lab = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findByIdAndLab($id, $labId)
    {
       $textobject =  $this->createQueryBuilder('o')
            ->andWhere('o.id = :id')
            ->andWhere('o.lab = :labId')
            ->setParameters(['id'=> $id, 'labId' => $labId])
            ->getQuery()
            ->getResult()
        ;

        return $textobject[0];
    }

    // /**
    //  * @return TextObject[] Returns an array of TextObject objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('o.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Device
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
