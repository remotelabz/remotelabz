<?php

namespace App\Repository;

use App\Entity\ProxyRedirection;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @method ProxyRedirection|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProxyRedirection|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProxyRedirection[]    findAll()
 * @method ProxyRedirection[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProxyRedirectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProxyRedirection::class);
    }
}
