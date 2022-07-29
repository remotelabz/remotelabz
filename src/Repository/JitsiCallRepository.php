<?php

namespace App\Repository;

use App\Entity\JitsiCall;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method JitsiCall|null find($id, $lockMode = null, $lockVersion = null)
 * @method JitsiCall|null findOneBy(array $criteria, array $orderBy = null)
 * @method JitsiCall[]    findAll()
 * @method JitsiCall[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class JitsiCallRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JitsiCall::class);
    }
}