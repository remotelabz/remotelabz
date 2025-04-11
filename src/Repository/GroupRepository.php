<?php

namespace App\Repository;

use App\Entity\Group;
use Doctrine\Persistence\ManagerRegistry;
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
        $slugs = array_reverse(explode('/', $slug));

        $group = $this->findOneBy(['slug' => array_pop($slugs), 'parent' => null]);

        if ($group && !empty($slugs)) {
            while (!empty($slugs) && $group) {
                $slug = array_pop($slugs);

                $group = $group->getChildren()->filter(function ($child) use ($slug, $group) {
                    return $child->getSlug() == $slug && $child->getParent()->getId() == $group->getId();
                })->first();
            }
        }

        return $group ?: null;
    }


    // @return : Group[] Return an array of group objects for which the $user is owner or admin
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
