<?php

namespace App\EventListener;

use App\Entity\Lab;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use JMS\Serializer\EventDispatcher\PreSerializeEvent;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface as JMSEventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Hides the hidden devices from the lab payload (group "api_get_lab") for
 * every user who is neither the lab author nor a system admin.
 *
 * The "export_lab", "worker" and "sandbox" serializations always receive the
 * complete device collection.
 */
class LabSerializationListener implements JMSEventSubscriberInterface
{
    /** @var array<int, array{lab: Lab, devices: object}> */
    private array $originalDevices = [];

    public function __construct(
        private TokenStorageInterface $tokenStorage
    ) {
    }

    static public function getSubscribedEvents(): array
    {
        return [
            [
                'event' => 'serializer.pre_serialize',
                'class' => Lab::class,
                'method' => 'onLabPreSerialize'
            ],
            [
                'event' => 'serializer.post_serialize',
                'class' => Lab::class,
                'method' => 'onLabPostSerialize'
            ],
        ];
    }

    public function onLabPreSerialize(PreSerializeEvent $event)
    {
        $lab = $event->getObject();
        $context = $event->getContext();

        // A previous serialization may have been aborted by an exception:
        // restore every pending collection first. Labs are never nested.
        $this->restorePending();

        if (!$this->mustFilter($lab, $context)) {
            return;
        }

        $visibleDevices = new ArrayCollection();
        foreach ($lab->getDevices() as $device) {
            if (!$device->getHidden()) {
                $visibleDevices->add($device);
            }
        }

        if ($visibleDevices->count() !== count($lab->getDevices())) {
            $this->originalDevices[spl_object_id($lab)] = [
                'lab' => $lab,
                'devices' => $lab->getDevices(),
            ];
            $lab->setDevices($visibleDevices);
        }
    }

    public function onLabPostSerialize(ObjectEvent $event)
    {
        $id = spl_object_id($event->getObject());

        if (isset($this->originalDevices[$id])) {
            $this->originalDevices[$id]['lab']->setDevices($this->originalDevices[$id]['devices']);
            unset($this->originalDevices[$id]);
        }
    }

    private function restorePending(): void
    {
        foreach ($this->originalDevices as $pending) {
            $pending['lab']->setDevices($pending['devices']);
        }
        $this->originalDevices = [];
    }

    private function mustFilter(Lab $lab, $context): bool
    {
        if (!method_exists($context, 'hasAttribute') || !$context->hasAttribute('groups')) {
            return false;
        }

        $groups = (array) $context->getAttribute('groups');

        // Full lab exports, worker payloads and the admin sandbox never filter
        if (array_intersect(['export_lab', 'worker', 'sandbox'], $groups)) {
            return false;
        }

        if (!in_array('api_get_lab', $groups, true)) {
            return false;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            return true;
        }

        return !$user->isAdministrator() && $user !== $lab->getAuthor();
    }
}
