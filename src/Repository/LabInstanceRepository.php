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
            ->setMaxResults(1)
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
            ->setMaxResults(1)
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
    the $user is a member*/
    public function findByUserAndGroups(User $user)
    {
        //$result=$this->findByUser($user);
        $result=$this->findByUser($user);
        foreach ($user->getGroups() as $groupuser) {
            $group=$groupuser->getGroup();

            foreach ($group->getLabInstances() as $labinstance) {
                array_push($result,$labinstance);
            }
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
                foreach ($group->getUsers() as $user_member) {
                    if ($user_member != $user) {
                        foreach ($this->findByUser($user_member) as $labinstance) {
                            if ($labinstance->getLab()->getGroups()->contains($group)) {
                                array_push($result,$labinstance);
                            }
                        }
                    }
                }
                foreach ($group->getLabs() as $lab) {
                    foreach($this->findBy(["lab" => $lab, "user" => NULL, "_group" => NULL]) as $labinstance) {
                        array_push($result,$labinstance);
                    }
                }
                foreach ($group->getLabInstances() as $labinstance) {
                    array_push($result,$labinstance);
                }
            }
        }
        return $result;
    }

    /* Return all instances started by the $user and groups for which
    the $user is the owner or the admin
    */
    public function findByUserMembersAndGroups(User $user)
    {
        $result=$this->findByUser($user);
        foreach ($user->getGroups() as $groupuser) {
            $group=$groupuser->getGroup();
            if ($group->isElevatedUser($user)) {
                foreach ($group->getUsers() as $user_member) {
                    if ($user_member != $user) {
                        foreach ($this->findByUser($user_member) as $labinstance) {
                            if ($labinstance->getLab()->getGroups()->contains($group)) {
                                array_push($result,$labinstance);
                            }
                        }
                    }
                }
                foreach ($group->getLabs() as $lab) {
                    foreach($this->findBy(["lab" => $lab, "user" => NULL, "_group" => NULL]) as $labinstance) {
                        array_push($result,$labinstance);
                    }
                }
            }
            foreach ($group->getLabInstances() as $labinstance) {
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

    public function findByWorkerIP(string $workerIP) {
        return $this->createQueryBuilder('l')
            ->where("l.workerIp = :workerIp")
            ->setParameter("workerIp", $workerIP)
            ->getQuery()
            ->getResult();
    }

    public function findByLabAuthor(User $user)
    {
        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT li
            FROM App\Entity\LabInstance li
            LEFT JOIN li.lab l
            WHERE l.author = :user'
        )->setParameter('user', $user);

        return $query->getResult();
    }

    public function findByLabAuthorAndGroups(User $user)
    {
        $result = $this->findByLabAuthor($user);
        foreach ($user->getGroups() as $groupuser) {
            $group=$groupuser->getGroup();
            if ($group->isElevatedUser($user)) {
                foreach ($group->getUsers() as $user_member){
                    if ($user_member != $user) {
                        foreach ($this->findByUser($user_member) as $labinstance) {
                            if ($labinstance->getLab()->getAuthor() !== $user && $labinstance->getLab()->getGroups()->contains($group)){
                                array_push($result, $labinstance);
                            }
                        }
                    }
                }
                foreach ($group->getLabInstances() as $labinstance){
                    if ($labinstance->getLab()->getAuthor() !== $user){
                        array_push($result, $labinstance);
                    }
                }
            }
        }
        return $result;
    }

    public function findByLabAndUserGroup(Lab $lab, User $user)
    {
        $labinstances = $this->findBy(['lab'=>$lab]);
        $result = [];
        foreach($labinstances as $labinstance) {
            foreach ($user->getGroups() as $groupuser) {
                $group = $groupuser->getGroup();
                if ($labinstance->getLab()->getGroups()->contains($group)) {
                    array_push($result, $labinstance);
                }
            }
        }
        return $result;
    }

    public function findByUserAndGroupStudents(User $user)
    {
        $instances = $this->findBy(['ownedBy' => 'user']);
        $result = [];
        foreach($instances as $instance) {
            if ($instance->getOwner()->getHighestRole() == "ROLE_USER") {
                foreach ($user->getGroups() as $groupuser) {
                    $group = $groupuser->getGroup();
                    if ($instance->getOwner()->isMemberOf($group) && $instance->getlab()->getGroups()->contains($group)) {
                        array_push($result, $instance);
                    }
                }
            }
        }
        return $result;
    }

    public function findByUserOfOwnerGroup(User $user, User $owner)
    {
        $instances = $this->findBy(['user'=> $user]);
        $result = [];
        foreach ($instances as $instance) {
            foreach($owner->getGroups() as $groupuser){
                $group = $groupuser->getGroup();
                if($group->isElevatedUser($owner)) {
                    if ($instance->getLab()->getGroups()->contains($group)) {
                        array_push($result, $instance);
                    }
                }
            }
        }
        return $result;
    }

    public function findByUserGroups(User $user)
    {
        $instances = $this->findAll();
        $result = [];
        foreach ($instances as $instance) {
            foreach ($user->getGroups() as $groupuser){
                $group = $groupuser->getGroup();
                if ($instance->getLab()->getGroups()->contains($group)) {
                    if (!in_array($instance, $result)) {
                        if ($user->isAdministrator()) {
                            array_push($result, $instance);
                        }
                        else {
                            if ($instance->getOwnedBy() !== "group") {
                                array_push($result, $instance);
                            }
                            else if ($instance->getOwnedBy() == "group" && $instance->getOwner()->isElevatedUser($user)) {
                                array_push($result, $instance);
                            }
                        }
                    }

                }
            }
        }
        return $result;
    }

    public function findByGroup(Group $group, User $user)
    {
        $instances = $this->findAll();
        $result = [];
        foreach ($instances as $instance) {
            if ($instance->getLab()->getGroups()->contains($group)) {
                if ($user->isAdministrator() || $group->isElevatedUser($user)) {
                    array_push($result, $instance);
                }
                else {
                    if ($instance->getOwner() == $user) {
                        array_push($result, $instance);
                    }
                }
            }
        }
        return $result;
    }

    public function findByGroupAndLabUuid($group, $lab)
    {
        $instances = $this->findBy(['lab'=> $lab]);
        $result = [];
        foreach ($instances as $instance) {
            if ($instance->getLab()->getGroups()->contains($group)) {
                array_push($result, $instance);
            }
        }
        return $result;
    }

    public function findByDefaultGroup()
    {
        $instances = $this->findAll();
        $result = [];
        foreach ($instances as $instance) {
            if (!is_null($instance->getGroup()))
                if ( $instance->getGroup()->getName() == "Default group")
                {
                    array_push($result, $instance);
                }
            }
        return $result;
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
