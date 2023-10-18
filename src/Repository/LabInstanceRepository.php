<?php

namespace App\Repository;

use App\Entity\Lab;
use App\Entity\User;
use App\Entity\InvitationCode;
use App\Entity\LabInstance;
use App\Entity\Group;
use App\Entity\GroupUser;
use Doctrine\Persistence\ManagerRegistry;
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

    public function findByGuestAndLab(InvitationCode $guest, Lab $lab)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.guest = :guest')
            ->andWhere('l.lab = :lab')
            ->setParameter('guest', $guest)
            ->setParameter('lab', $lab)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }


    // Return all instances started by the $user
    public function findByUser(User $user)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult()
        ;
    }

    /* Return all instances started by the $user and groups for which
    the $user is the owner or the admin
    */
    public function findByUserAndGroups(User $user)
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
    
    /* Return all instances started by the $user and groups for which
    the $user is the owner or the admin
    */
    public function findByUserAndAllMembersGroups(User $user)
    {
        $result=$this->findByUser($user);
        foreach ($user->getGroups() as $groupuser) {
            $group=$groupuser->getGroup();
            if ($group->isElevatedUser($user)) {
                foreach ($group->getUsers() as $user_member)
                    if ($user_member != $user)
                        foreach ($this->findByUser($user_member) as $labinstance)
                            array_push($result,$labinstance);
                foreach ($group->getLabInstances() as $labinstance)
                    array_push($result,$labinstance);
            }
        }
        return $result;
    }

    // Return all instances started by the $user
    public function findByLab(Lab $lab)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.lab = :lab')
            ->setParameter('lab', $lab)
            ->getQuery()
            ->getResult()
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
