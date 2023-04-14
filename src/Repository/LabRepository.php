<?php

namespace App\Repository;

use App\Entity\Lab;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Lab|null find($id, $lockMode = null, $lockVersion = null)
 * @method Lab|null findOneBy(array $criteria, array $orderBy = null)
 * @method Lab[]    findAll()
 * @method Lab[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LabRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lab::class);
    }

    public function findByNameLike($value)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.name LIKE :val')
            ->setParameter('val', '%'.$value.'%')
            ->orderBy('l.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findById($id)
    {
        $lab =  $this->createQueryBuilder('l')
            ->andWhere('l.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult()
        ;

        return $lab[0];
    }

    public function findLabDetailsById($id)
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT L.id, L.name, L.shortDescription as description, L.tasks, CONCAT(A.firstName,\' \',A.lastName) as author
            FROM App\Entity\Lab L
            LEFT JOIN L.author A
            WHERE L.id = :id'
        )
        ->setParameter('id', $id);

        $lab = $query->getResult();

        // returns an array of Product objects
        return $lab[0];
    }

    public function findLabInfoById($id)
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT L.id as filename, L.uuid as id, L.name, L.shortDescription as description, 
            L.tasks as body, CONCAT(A.firstName,\' \',A.lastName) as author, L.version,
            L.scripttimeout, L.locked
            FROM App\Entity\Lab L
            LEFT JOIN L.author A
            WHERE L.id = :id'
        )
        ->setParameter('id', $id);

        $lab = $query->getResult();

        // returns an array of Product objects
        return $lab[0];
    }

    // /**
    //  * @return Lab[] Returns an array of Lab objects
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
    public function findOneBySomeField($value): ?Lab
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
