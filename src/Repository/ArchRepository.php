<?php

namespace App\Repository;

use App\Entity\Arch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Arch>
 */
class ArchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Arch::class);
    }

    public function findByName($name)
    {
       $arch = $this->createQueryBuilder('o')
            ->andWhere('o.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getResult()
        ;

        if ($arch == NULL) {
            return null;
        }
        return $arch[0];
    }
}
