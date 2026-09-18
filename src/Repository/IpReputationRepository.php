<?php

namespace App\Repository;

use App\Entity\IpReputation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IpReputation>
 */
class IpReputationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IpReputation::class);
    }

    /**
     * @param array<string> $ips
     * @return array<string, IpReputation>
     */
    public function findByIps(array $ips): array
    {
        if (empty($ips)) {
            return [];
        }

        $results = $this->findBy(['ip' => array_values(array_unique($ips))]);

        $map = [];
        foreach ($results as $reputation) {
            $map[$reputation->getIp()] = $reputation;
        }

        return $map;
    }
}
