<?php

namespace App\Repository;

use App\Entity\EditorData;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @method EditorData|null find($id, $lockMode = null, $lockVersion = null)
 * @method EditorData|null findOneBy(array $criteria, array $orderBy = null)
 * @method EditorData[]    findAll()
 * @method EditorData[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EditorDataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EditorData::class);
    }

    public function findByDeviceId(int $id): ?EditorData
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.device = :val')
            ->setParameter('val', $id)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    // /**
    //  * @return EditorData[] Returns an array of EditorData objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('e.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?EditorData
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
