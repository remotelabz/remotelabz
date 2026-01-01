<?php

namespace App\Repository;

use App\Entity\Directory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for Directory entity
 * Provides specialized queries for hierarchical directory operations
 * 
 * @extends ServiceEntityRepository<Directory>
 * @method Directory|null find($id, $lockMode = null, $lockVersion = null)
 * @method Directory|null findOneBy(array $criteria, array $orderBy = null)
 * @method Directory[]    findAll()
 * @method Directory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DirectoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Directory::class);
    }

    /**
     * Find all root directories (directories without parent)
     * 
     * @return Directory[]
     */
    public function findRoots(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.parent IS NULL')
            ->andWhere('d.deletedAt IS NULL')
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find direct children of a directory
     * 
     * @param Directory|null $parent Parent directory (null for roots)
     * @return Directory[]
     */
    public function findChildren(?Directory $parent = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->andWhere('d.deletedAt IS NULL')
            ->orderBy('d.name', 'ASC');

        if ($parent === null) {
            $qb->andWhere('d.parent IS NULL');
        } else {
            $qb->andWhere('d.parent = :parent')
               ->setParameter('parent', $parent);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find directory by path
     * 
     * @param string $path Full path (e.g., "/parent/child")
     * @return Directory|null
     */
    public function findByPath(string $path): ?Directory
    {
        return $this->createQueryBuilder('d')
            ->where('d.path = :path')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('path', $path)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find directories by path pattern
     * 
     * @param string $pattern Path pattern (e.g., "/parent/%")
     * @return Directory[]
     */
    public function findByPathPattern(string $pattern): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.path LIKE :pattern')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('pattern', $pattern)
            ->orderBy('d.path', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all descendants of a directory (recursive)
     * 
     * @param Directory $directory Parent directory
     * @return Directory[]
     */
    public function findDescendants(Directory $directory): array
    {
        $path = $directory->getPath();
        
        return $this->createQueryBuilder('d')
            ->where('d.path LIKE :path')
            ->andWhere('d.id != :id')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('path', $path . '/%')
            ->setParameter('id', $directory->getId())
            ->orderBy('d.path', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find directories by level (depth in tree)
     * 
     * @param int $level Tree depth level
     * @return Directory[]
     */
    public function findByLevel(int $level): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.level = :level')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('level', $level)
            ->orderBy('d.path', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find directory with all its contents (eager loading)
     * Useful to avoid N+1 queries when displaying directory contents
     * 
     * @param int $id Directory ID
     * @return Directory|null
     */
    public function findWithContents(int $id): ?Directory
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.devices', 'dev')
            ->leftJoin('d.isos', 'iso')
            ->leftJoin('d.operatingSystems', 'os')
            ->leftJoin('d.children', 'child')
            ->addSelect('dev', 'iso', 'os', 'child')
            ->where('d.id = :id')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find empty directories (no items and no children)
     * 
     * @return Directory[]
     */
    public function findEmpty(): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.devices', 'dev')
            ->leftJoin('d.isos', 'iso')
            ->leftJoin('d.operatingSystems', 'os')
            ->leftJoin('d.children', 'child')
            ->where('d.deletedAt IS NULL')
            ->having('COUNT(dev.id) = 0')
            ->andHaving('COUNT(iso.id) = 0')
            ->andHaving('COUNT(os.id) = 0')
            ->andHaving('COUNT(child.id) = 0')
            ->groupBy('d.id')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search directories by name
     * 
     * @param string $name Search term
     * @return Directory[]
     */
    public function searchByName(string $name): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.name LIKE :name')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('name', '%' . $name . '%')
            ->orderBy('d.path', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get directory tree structure (recursive)
     * Returns hierarchical array structure
     * 
     * @param Directory|null $parent Starting point (null for full tree)
     * @param int $maxDepth Maximum depth to traverse (-1 for unlimited)
     * @return array
     */
    public function getTreeStructure(?Directory $parent = null, int $maxDepth = -1): array
    {
        $children = $this->findChildren($parent);
        $tree = [];

        foreach ($children as $child) {
            $node = [
                'directory' => $child,
                'children' => []
            ];

            // Recursively get children if not at max depth
            if ($maxDepth === -1 || ($parent === null ? 0 : $parent->getLevel()) < $maxDepth) {
                $node['children'] = $this->getTreeStructure($child, $maxDepth);
            }

            $tree[] = $node;
        }

        return $tree;
    }

    /**
     * Count items in directory by type
     * 
     * @param Directory $directory
     * @return array ['devices' => int, 'isos' => int, 'operatingSystems' => int]
     */
    public function countItemsByType(Directory $directory): array
    {
        $result = $this->createQueryBuilder('d')
            ->select([
                'COUNT(DISTINCT dev.id) as devicesCount',
                'COUNT(DISTINCT iso.id) as isosCount',
                'COUNT(DISTINCT os.id) as osCount'
            ])
            ->leftJoin('d.devices', 'dev')
            ->leftJoin('d.isos', 'iso')
            ->leftJoin('d.operatingSystems', 'os')
            ->where('d.id = :id')
            ->setParameter('id', $directory->getId())
            ->getQuery()
            ->getSingleResult();

        return [
            'devices' => (int) $result['devicesCount'],
            'isos' => (int) $result['isosCount'],
            'operatingSystems' => (int) $result['osCount']
        ];
    }

    /**
     * Find directories modified after a given date
     * 
     * @param \DateTimeInterface $date
     * @return Directory[]
     */
    public function findModifiedAfter(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.updatedAt > :date')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('date', $date)
            ->orderBy('d.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find directories by name in a specific parent directory
     * 
     * @param string $name Directory name
     * @param Directory|null $parent Parent directory (null for root level)
     * @return Directory|null
     */
    public function findByNameAndParent(string $name, ?Directory $parent = null): ?Directory
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.name = :name')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('name', $name);

        if ($parent === null) {
            $qb->andWhere('d.parent IS NULL');
        } else {
            $qb->andWhere('d.parent = :parent')
               ->setParameter('parent', $parent);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Check if a directory path is unique
     * 
     * @param string $path
     * @param int|null $excludeId Exclude this directory ID from check
     * @return bool
     */
    public function isPathUnique(string $path, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.path = :path')
            ->andWhere('d.deletedAt IS NULL')
            ->setParameter('path', $path);

        if ($excludeId !== null) {
            $qb->andWhere('d.id != :id')
               ->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() === 0;
    }

    /**
     * Find soft-deleted directories
     * 
     * @return Directory[]
     */
    public function findDeleted(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.deletedAt IS NOT NULL')
            ->orderBy('d.deletedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get statistics about directory structure
     * 
     * @return array
     */
    public function getStatistics(): array
    {
        $stats = $this->createQueryBuilder('d')
            ->select([
                'COUNT(d.id) as totalDirectories',
                'MAX(d.level) as maxDepth',
                'AVG(d.level) as avgDepth'
            ])
            ->where('d.deletedAt IS NULL')
            ->getQuery()
            ->getSingleResult();

        $rootCount = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.parent IS NULL')
            ->andWhere('d.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => (int) $stats['totalDirectories'],
            'roots' => (int) $rootCount,
            'maxDepth' => (int) $stats['maxDepth'],
            'avgDepth' => (float) $stats['avgDepth']
        ];
    }
}