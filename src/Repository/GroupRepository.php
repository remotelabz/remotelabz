<?php

namespace App\Repository;

use App\Entity\Group;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @method Group|null find($id, $lockMode = null, $lockVersion = null)
 * @method Group|null findOneBy(array $criteria, array $orderBy = null)
 * @method Group|null findOneBySlug(string $slug)
 * @method Group[]    findAll()
 * @method Group[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Group::class);
    }

    public function findOneBySlug(string $slug): ?Group
    {
        $splitedSlug = explode('/', $slug);

        $qb = $this->createQueryBuilder('g')
            ->andWhere('g.slug = :slug')
            ->setParameter('slug', $splitedSlug[sizeof($splitedSlug) - 1])
        ;

        if (sizeof($splitedSlug) > 1) {
            $qb->leftJoin('g.parent', 'p')
                ->andWhere('p.slug = :parentSlug')
                ->setParameter('parentSlug', $splitedSlug[sizeof($splitedSlug) - 2])
            ;
        }

        return $qb->getQuery()
            ->getOneOrNullResult()
        ;
    }

    // /**
    //  * @return Group[] Returns an array of Group objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('g.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Group
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
