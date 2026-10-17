<?php

namespace App\Repository;

use App\Entity\LoginLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

class LoginLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginLog::class);
    }

    public function createQueryBuilderForRange(\DateTimeInterface $start, \DateTimeInterface $end, string $userFilter = '', ?int $minScore = null)
    {
        $queryBuilder = $this->createQueryBuilder('l')
            ->where('l.created_at >= :start')
            ->andWhere('l.created_at <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($userFilter !== '') {
            $queryBuilder
                ->andWhere($queryBuilder->expr()->orX(
                    $queryBuilder->expr()->like('LOWER(l.email)', ':userFilter'),
                    $queryBuilder->expr()->like('LOWER(l.firstName)', ':userFilter'),
                    $queryBuilder->expr()->like('LOWER(l.lastName)', ':userFilter')
                ))
                ->setParameter('userFilter', '%' . mb_strtolower($userFilter) . '%');
        }

        if ($minScore !== null) {
            $queryBuilder
                ->innerJoin('App\Entity\IpReputation', 'r', 'WITH', 'r.ip = l.ip')
                ->andWhere('r.abuse_score >= :minScore')
                ->setParameter('minScore', $minScore);
        }

        return $queryBuilder->orderBy('l.created_at', SortDirection::Descending);
    }

    public function findRecentByUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.email = :email')
            ->setParameter('email', $user->getEmail())
            ->orderBy('l.created_at', SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findRecentByIp(string $ip, int $limit = 10): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.ip = :ip')
            ->setParameter('ip', $ip)
            ->orderBy('l.created_at', SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findLogsSince(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.created_at > :date')
            ->setParameter('date', $date)
            ->orderBy('l.created_at', SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    public function deleteBefore(\DateTimeInterface $date): int
    {
        return $this->createQueryBuilder('l')
            ->delete()
            ->where('l.created_at < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}
