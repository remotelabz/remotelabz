<?php

namespace App\Repository;

use App\Entity\Device;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @method Device|null find($id, $lockMode = null, $lockVersion = null)
 * @method Device|null findOneBy(array $criteria, array $orderBy = null)
 * @method Device[]    findAll()
 * @method Device[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Device::class);
    }

    public function findByNameLike($value)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.name LIKE :val')
            ->setParameter('val', '%'.$value.'%')
            ->orderBy('l.id', SortDirection::Descending)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findByTemplate($template = true)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.isTemplate = :val')
            ->setParameter('val', $template ? 1 : 0)
            ->orderBy('l.id', SortDirection::Descending)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findByTemplateBeginning($template)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.template LIKE :val')
            ->setParameter('val', $template . "%")
            ->getQuery()
            ->getResult()
        ;
    }

    public function findByNameByTemplate($name, $template = true)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.isTemplate = :val')
            ->andWhere('l.name = :name')
            ->setParameter('val', $template ? 1 : 0)
            ->setParameter('name', $name)
            ->orderBy('l.id', SortDirection::Descending)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Devices of a lab.
     *
     * @param int  $id            lab id
     * @param bool $includeHidden also return hidden devices (lab author /
     *                            system admins only)
     */
    public function findByLab($id, bool $includeHidden = false)
    {
        $entityManager = $this->getEntityManager();

        $dql = 'SELECT d
            FROM App\Entity\Device d
            LEFT JOIN d.labs l
            WHERE l.id = :id';

        if (!$includeHidden) {
            $dql .= ' AND d.hidden = 0';
        }

        $query = $entityManager->createQuery($dql)->setParameter('id', $id);

        return $query->getResult();
    }

    /**
     * Devices instantiated by a lab instance.
     *
     * @param object $lab           the lab instance
     * @param bool   $includeHidden also return hidden devices (lab author /
     *                              system admins only)
     */
    public function findByLabInstance($lab, bool $includeHidden = false)
    {
        $entityManager = $this->getEntityManager();

        $dql = 'SELECT d
            FROM App\Entity\Device d
            WHERE d IN (
                SELECT IDENTITY(di.device)
            FROM App\Entity\DeviceInstance di
            WHERE di.labInstance = :lab
            )';

        if (!$includeHidden) {
            $dql .= ' AND d.hidden = 0';
        }

        $query = $entityManager->createQuery($dql)->setParameter('lab', $lab);

        return $query->getResult();
    }

    public function findByOperatingSystem($operatingSystemId)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.operatingSystem = :id')
            ->setParameter('id', $operatingSystemId)
            ->orderBy('d.name', SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }
}
