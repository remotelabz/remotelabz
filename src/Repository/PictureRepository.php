<?php

namespace App\Repository;

use App\Entity\Picture;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @method TextObject|null find($id, $lockMode = null, $lockVersion = null)
 * @method TextObject|null findOneBy(array $criteria, array $orderBy = null)
 * @method TextObject[]    findAll()
 * @method TextObject[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PictureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Picture::class);
    }

    public function findByLab($id)
    {
       return  $this->createQueryBuilder('p')
            ->andWhere('p.lab = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findByIdAndLab($id, $labId)
    {
       $picture =  $this->createQueryBuilder('p')
            ->andWhere('p.id = :id')
            ->andWhere('p.lab = :labId')
            ->setParameters(['id'=> $id, 'labId' => $labId])
            ->getQuery()
            ->getResult()
        ;

        if($picture == null) {
            return null;
        }
        else {
            return $picture[0];
        }
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
