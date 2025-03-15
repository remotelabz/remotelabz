<?php

namespace App\Repository;

use App\Entity\PduOutletDevice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PduOutletDevice>
 *
 * @method PduOutletDevice|null find($id, $lockMode = null, $lockVersion = null)
 * @method PduOutletDevice|null findOneBy(array $criteria, array $orderBy = null)
 * @method PduOutletDevice[]    findAll()
 * @method PduOutletDevice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PduOutletDeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PduOutletDevice::class);
    }

    public function add(PduOutletDevice $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PduOutletDevice $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return PduOutletDevice[] Returns an array of PduOutletDevice objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?PduOutletDevice
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
