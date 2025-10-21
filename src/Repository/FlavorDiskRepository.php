<?php

namespace App\Repository;

use App\Entity\FlavorDisk;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FlavorDisk>
 *
 * @method FlavorDisk|null find($id, $lockMode = null, $lockVersion = null)
 * @method FlavorDisk|null findOneBy(array $criteria, array $orderBy = null)
 * @method FlavorDisk[]    findAll()
 * @method FlavorDisk[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FlavorDiskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FlavorDisk::class);
    }

    /**
     * Save a FlavorDisk entity
     */
    public function save(FlavorDisk $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Remove a FlavorDisk entity
     */
    public function remove(FlavorDisk $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all FlavorDisk ordered by name
     *
     * @return FlavorDisk[]
     */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all FlavorDisk ordered by disk size
     *
     * @return FlavorDisk[]
     */
    public function findAllOrderedBySize(): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.disk', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find FlavorDisk by name (case insensitive)
     */
    public function findByNameInsensitive(string $name): ?FlavorDisk
    {
        return $this->createQueryBuilder('f')
            ->where('LOWER(f.name) = LOWER(:name)')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find FlavorDisk by minimum disk size
     *
     * @return FlavorDisk[]
     */
    public function findByMinimumSize(int $minSize): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.disk >= :minSize')
            ->setParameter('minSize', $minSize)
            ->orderBy('f.disk', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find FlavorDisk by disk size range
     *
     * @return FlavorDisk[]
     */
    public function findByDiskSizeRange(int $minSize, int $maxSize): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.disk >= :minSize')
            ->andWhere('f.disk <= :maxSize')
            ->setParameter('minSize', $minSize)
            ->setParameter('maxSize', $maxSize)
            ->orderBy('f.disk', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count operating systems using this FlavorDisk
     */
    public function countOperatingSystemsUsing(FlavorDisk $flavorDisk): int
    {
        return $this->createQueryBuilder('f')
            ->select('COUNT(os.id)')
            ->leftJoin('App\Entity\OperatingSystem', 'os', 'WITH', 'os.flavorDisk = f.id')
            ->where('f.id = :flavorDiskId')
            ->setParameter('flavorDiskId', $flavorDisk->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Check if a FlavorDisk name already exists (useful for validation)
     */
    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('LOWER(f.name) = LOWER(:name)')
            ->setParameter('name', $name);

        if ($excludeId !== null) {
            $qb->andWhere('f.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Get statistics about FlavorDisk usage
     *
     * @return array
     */
    public function getUsageStatistics(): array
    {
        return $this->createQueryBuilder('f')
            ->select('f.id', 'f.name', 'f.disk', 'COUNT(os.id) as osCount')
            ->leftJoin('App\Entity\OperatingSystem', 'os', 'WITH', 'os.flavorDisk = f.id')
            ->groupBy('f.id')
            ->orderBy('osCount', 'DESC')
            ->getQuery()
            ->getResult();
    }
}