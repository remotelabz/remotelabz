<?php

namespace App\Repository;

use App\Entity\Booking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Booking|null find($id, $lockMode = null, $lockVersion = null)
 * @method Booking|null findOneBy(array $criteria, array $orderBy = null)
 * @method Booking[]    findAll()
 * @method Booking[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    public function findOldBookings($now)
    {

        $entityManager = $this->getEntityManager();

        $result = [];
        $bookings =  $this->createQueryBuilder('b')
            ->andWhere('b.endDate <= :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult()
        ;

        foreach ($bookings as $booking) {
            $labInstance = $entityManager->createQuery(
                'SELECT li.id as lab_instance_id, li.uuid as lab_instance_uuid, l.uuid as lab_uuid,
                l.id as lab_id
                FROM App\Entity\LabInstance li
                JOIN li.lab l
                WHERE li.lab = :lab'
            )
            ->setParameter(':lab', $booking->getLab())
            ->getOneOrNullResult();

            if (!$labInstance == null) {
                array_push($result, [
                    "booking_id" => $booking->getId(),
                    "booking_uuid" => $booking->getUUid(),
                    "lab_id" => $labInstance['lab_id'],
                    "lab_uuid" => $labInstance['lab_uuid'],
                    "lab_instance_id" => $labInstance['lab_instance_id'],
                    "lab_instance_uuid" => $labInstance['lab_instance_uuid'],
                ]);
            }
            else {
                array_push($result, [
                    "booking_id" => $booking->getId(),
                    "booking_uuid" => $booking->getUUid(),
                    "lab_id" => $booking->getLab()->getId(),
                    "lab_uuid" => $booking->getLab()->getUUid(),
                ]);
            }

        }


        return $result;

    }

    // /**
    //  * @return Booking[] Returns an array of Booking objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('b.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Booking
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
