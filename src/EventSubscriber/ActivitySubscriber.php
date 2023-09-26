<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class ActivitySubscriber implements EventSubscriberInterface {

    private $em;
    private $security;

    public function __construct(
        EntityManagerInterface $em, Security $security) {
        $this->em = $em;
        $this->security = $security;
    }

    public function onterminate() {
        $user = $this->security->getuser();

        if ($user && !$user->isActiveNow()) {
            $user->setLastActivity(new \DateTime());
            $this->em->persist($user);
            $this->em->flush($user);
        }
    }

    public static function getSubscribedEvents() {
        return [
            // must be registered before (i.e. with a higher priority than) the default locale listener
            KernelEvents::TERMINATE => [['onterminate', 20]],
        ];
    }

}