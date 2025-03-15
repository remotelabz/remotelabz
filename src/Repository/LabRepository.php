<?php

namespace App\Repository;

use App\Entity\Lab;
use App\Entity\User;
use App\Entity\Group;
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
            'SELECT L.id, L.name, L.shortDescription as description, L.description as tasks, CONCAT(A.firstName,\' \',A.lastName) as author
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
            L.description as body, CONCAT(A.firstName,\' \',A.lastName) as author, L.version,
            L.scripttimeout, L.locked, L.banner, L.timer
            FROM App\Entity\Lab L
            LEFT JOIN L.author A
            WHERE L.id = :id'
        )
        ->setParameter('id', $id);

        $lab = $query->getResult();

        // returns an array of Product objects
        return $lab[0];
    }

    /* Return all labs created by the $user and labs used by groups for which
    the $user is the owner or the admin
    */
    public function findByAuthorAndGroups(User $user)
    {
        $result=$this->findByAuthor($user);
        foreach ($user->getGroups() as $groupuser) {
            $group=$groupuser->getGroup();
            if ($group->isElevatedUser($user)) {
                $labs = $this->findByGroup($group);
                foreach ($labs as $lab) {
                    if ($lab->getAuthor() != $user){
                        array_push($result, $lab);
                    }
                } 
            }
        }
        return $result;
    }

    /* Return all labs created by the $user
    */
    public function findByAuthor(User $user)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.author = :user')
            ->andWhere('l.isTemplate = :val')
            ->setParameter('user', $user)
            ->setParameter('val', false)
            ->getQuery()
            ->getResult()
        ;
    }

    /* Return all labs which includes $group
    */
    public function findByGroup(Group $group)
    {
        return $this->createQueryBuilder('l')
            ->andWhere(':group MEMBER OF l.groups')
            ->andWhere('l.isTemplate = :val')
            ->setParameter('group', $group)
            ->setParameter('val', false)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findByBookings($search)
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT DISTINCT l.id, l.name, count(b.id) as numberOfBookings
            FROM App\Entity\Lab l
            LEFT JOIN l.bookings b
            WHERE l.name LIKE :name
            AND l.virtuality = 0
            GROUP BY l.id, l.name
            ORDER BY numberOfBookings DESC'
        )
        ->setParameter(':name', '%'.$search.'%');

        return $query->getResult();
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
