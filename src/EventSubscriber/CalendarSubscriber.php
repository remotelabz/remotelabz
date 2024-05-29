<?php

namespace App\EventSubscriber;

use CalendarBundle\Entity\Event;
use CalendarBundle\Event\SetDataEvent;
use CalendarBundle\CalendarEvents;
use CalendarBundle\Event\CalendarEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Repository\BookingRepository;

class CalendarSubscriber implements EventSubscriberInterface
{
    private $bookingRepository;
    private $router;

    public function __construct(
        BookingRepository $bookingRepository,
        UrlGeneratorInterface $router
    ) {
        $this->bookingRepository = $bookingRepository;
        $this->router =$router;
    }

    public static function getSubscribedEvents()
    {
        return [
            CalendarEvents::SET_DATA => 'onCalendarSetData',
        ];
    }

    public function onCalendarSetData(CalendarEvent $setDataEvent)
    {
        $start = $setDataEvent->getStart();
        $end = $setDataEvent->getEnd();
        $filters = $setDataEvent->getFilters();

        $query = $this->bookingRepository
            ->createQueryBuilder('booking')
            ->join('booking.lab', 'lab')
            ->where('booking.startDate BETWEEN :start and :end OR booking.endDate BETWEEN :start and :end')
            ->setParameter('start', $start->format('Y-m-d H:i:s'))
            ->setParameter('end', $end->format('Y-m-d H:i:s'))
        ;

        if (isset($filters['labId']) && $filters['labId'] != "") {
            $query->andWhere('lab.id = :labId')
                ->setParameter('labId', $filters["labId"]);
        }
        $bookings = $query->getQuery()->getResult();
        foreach ($bookings as $booking) {

            $bookingEvent = new Event(
                $booking->getName(),
                $booking->getStartDate(),
                $booking->getEndDate()
            );

            $bookingEvent->setOptions([
                'backgroundColor' => '#2C3E50',
                'borderColor' => '#2C3E50',
                'description' => " reserved by ".$booking->getAuthor()->getName()." for ". $booking->getOwner()->getName()
            ]);
            $bookingEvent->addOption(
                'url',
                $this->router->generate('get_booking', [
                    'id' => $booking->getId(),
                ])
            );
            $setDataEvent->addEvent($bookingEvent);
        }
    }
}