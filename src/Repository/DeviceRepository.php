<?php

namespace App\Repository;

use App\Entity\Device;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

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
            ->orderBy('l.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findByTemplate($template = true)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.isTemplate = :val')
            ->setParameter('val', $template ? 1 : 0)
            ->orderBy('l.id', 'DESC')
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
            ->orderBy('l.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findByLab($id)
    {
        /*return $this->createQueryBuilder('d')
            ->leftjoin('App\Entity\lab l', 'l.id IN d.labs')
            ->andWhere('l.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult()
        ;*/

        $entityManager = $this->getEntityManager();

        $query = $entityManager->createQuery(
            'SELECT d
            FROM App\Entity\Device d
            LEFT JOIN d.labs l
            WHERE l.id = :id'
        )->setParameter('id', $id);

        return $query->getResult();
    }

    public function findByLabInstance($lab)
    {

        $entityManager = $this->getEntityManager();

        /*$query = $entityManager->createQuery(
            'SELECT IDENTITY(di.device)
            FROM App\Entity\DeviceInstance di
            WHERE di.labInstance = :lab'
        )->setParameter('lab', $lab);*/

        $query = $entityManager->createQuery(
            'SELECT d
            FROM App\Entity\Device d
            WHERE d IN (
                SELECT IDENTITY(di.device)
            FROM App\Entity\DeviceInstance di
            WHERE di.labInstance = :lab
            )'
        )->setParameter('lab', $lab);

        return $query->getResult();
    }

    // /**
    //  * @return Device[] Returns an array of Device objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Device
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
