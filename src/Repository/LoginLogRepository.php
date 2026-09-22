<?php

namespace App\Repository;

use App\Entity\LoginLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

class LoginLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginLog::class);
    }

    public function createQueryBuilderForRange(\DateTimeInterface $start, \DateTimeInterface $end, string $userFilter = '')
    {
        $queryBuilder = $this->createQueryBuilder('l')
            ->where('l.created_at >= :start')
            ->andWhere('l.created_at <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($userFilter !== '') {
            $queryBuilder
                ->leftJoin('l.user', 'u')
                ->andWhere($queryBuilder->expr()->orX(
                    $queryBuilder->expr()->like('LOWER(l.email)', ':userFilter'),
                    $queryBuilder->expr()->like('LOWER(u.firstName)', ':userFilter'),
                    $queryBuilder->expr()->like('LOWER(u.lastName)', ':userFilter')
                ))
                ->setParameter('userFilter', '%' . mb_strtolower($userFilter) . '%');
        }

        return $queryBuilder->orderBy('l.created_at', SortDirection::Descending);
    }

    public function findRecentByUser($user, int $limit = 10): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.user = :user')
            ->setParameter('user', $user)
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
