<?php

namespace App\EventSubscriber;

use CalendarBundle\Entity\Event;
use CalendarBundle\Event\SetDataEvent;
use CalendarBundle\CalendarEvents;
use CalendarBundle\Event\CalendarEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use App\Repository\BookingRepository;
use App\Entity\Group;
use App\Entity\User;

class CalendarSubscriber implements EventSubscriberInterface
{
    private $bookingRepository;
    private $router;
    private $tokenStorageInterface;

    public function __construct(
        BookingRepository $bookingRepository,
        UrlGeneratorInterface $router,
        TokenStorageInterface $tokenStorageInterface
    ) {
        $this->bookingRepository = $bookingRepository;
        $this->router = $router;
        $this->tokenStorageInterface = $tokenStorageInterface;
    }

    public static function getSubscribedEvents(): array
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
        $user = $this->tokenStorageInterface->getToken()->getUser();
        foreach ($bookings as $booking) {
            $color = "#2C3E50";
            $canSee = false;
            if ($user->isAdministrator() || $user == $booking->getAuthor()) {
                $canSee = true;
            }

            if ($booking->getOwner() instanceof Group) {
                if ($user->isMemberOf($booking->getOwner())) {                    
                    $color = "#008060";
                    $canSee = true;
                }
                if($booking->getOwner()->isElevatedUser($user)) {
                    $color = '#cc6600';
                    $canSee = true;
                }
            }
            else {
                foreach ($user->getGroups() as $groupUser) {
                    $group = $groupUser->getGroup();
                    if ($group->isElevatedUser($user) && $booking->getOwner()->isMemberOf($group)) {
                        $color = '#a93d4d';
                        $canSee = true;
                    }
                }
                if ($user == $booking->getOwner()) {
                    $color = '#008080';
                    $canSee = true;
                }
            }
            $bookingEvent = new Event(
                $canSee ? $booking->getName() : "",
                $booking->getStartDate(),
                $booking->getEndDate()
            );

            if ($canSee) {
                $bookingEvent->setOptions([
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'description' => " reserved by ".$booking->getAuthor()->getName()." for ". $booking->getOwner()->getName()
                ]);

                $bookingEvent->addOption(
                    'url',
                    $this->router->generate('show_booking', [
                        'id' => $booking->getId(),
                    ])
                );
            }
            else {
                $bookingEvent->setOptions([
                    'backgroundColor' => $color,
                    'borderColor' => $color
                ]);

            }
           
            
            $setDataEvent->addEvent($bookingEvent);
        }
    }
}