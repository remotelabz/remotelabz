<?php

namespace App\Repository;

use App\Entity\Lab;
use App\Entity\User;
use App\Entity\LabInstance;
use App\Entity\Group;
use App\Entity\GroupUser;
use Doctrine\Common\Persistence\ManagerRegistry;
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
    public function __construct(ManagerRegistry $registry)
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

    public function findByUser(User $user)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.user = :user')
             ->setParameter('user', $user)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findByUserWithGroup(User $user)
    {
        //$result=$this->findByUser($user);
        $result=$this->findByUser($user);
        foreach ($user->getGroups() as $groupuser) {
            $group=$groupuser->getGroup();
            
            if ($group->isElevatedUser($user))
                foreach ($group->getLabInstances() as $labinstance)
                    array_push($result,$labinstance);
        }
        return $result;
    }

    public function findGroupByUserElevated(User $user) {
        $ElevatedUserOfGroups=[];
        foreach ($user->getGroups() as $groupuser) {
            $group=$groupuser->getGroup();         
            if ($group->isElevatedUser($user)) {
                array_push($ElevatedUserOfGroups,$group);
            }
        }
        return $ElevatedUserOfGroups;

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
