<?php

namespace App\EventListener;

use App\Entity\LabInstance;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use JMS\Serializer\EventDispatcher\PreSerializeEvent;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface as JMSEventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Hides the device instances of hidden devices from every serialization
 * targeted at the lab users (group "api_get_lab_instance"), unless the
 * current user is the lab author or a system admin.
 *
 * The "worker" serialization group is never filtered: the worker must always
 * receive the complete lab instance.
 */
class LabInstanceSerializationListener implements JMSEventSubscriberInterface
{
    /** @var array<int, array{instance: LabInstance, deviceInstances: object}> */
    private array $originalDeviceInstances = [];

    public function __construct(
        private TokenStorageInterface $tokenStorage
    ) {
    }

    static public function getSubscribedEvents(): array
    {
        return [
            [
                'event' => 'serializer.pre_serialize',
                'class' => LabInstance::class,
                'method' => 'onLabInstancePreSerialize'
            ],
            [
                'event' => 'serializer.post_serialize',
                'class' => LabInstance::class,
                'method' => 'onLabInstancePostSerialize'
            ],
        ];
    }

    public function onLabInstancePreSerialize(PreSerializeEvent $event)
    {
        $labInstance = $event->getObject();
        $context = $event->getContext();

        // A previous serialization may have been aborted by an exception:
        // restore every pending collection first.
        $this->restorePending();

        if (!$this->mustFilter($labInstance, $context)) {
            return;
        }

        $visibleDeviceInstances = new ArrayCollection();
        foreach ($labInstance->getDeviceInstances() as $deviceInstance) {
            if (!$deviceInstance->getDevice() || !$deviceInstance->getDevice()->getHidden()) {
                $visibleDeviceInstances->add($deviceInstance);
            }
        }

        if ($visibleDeviceInstances->count() !== count($labInstance->getDeviceInstances())) {
            $this->originalDeviceInstances[spl_object_id($labInstance)] = [
                'instance' => $labInstance,
                'deviceInstances' => $labInstance->getDeviceInstances(),
            ];
            $labInstance->setDeviceInstances($visibleDeviceInstances);
        }
    }

    public function onLabInstancePostSerialize(ObjectEvent $event)
    {
        $id = spl_object_id($event->getObject());

        if (isset($this->originalDeviceInstances[$id])) {
            $this->originalDeviceInstances[$id]['instance']->setDeviceInstances($this->originalDeviceInstances[$id]['deviceInstances']);
            unset($this->originalDeviceInstances[$id]);
        }
    }

    private function restorePending(): void
    {
        foreach ($this->originalDeviceInstances as $pending) {
            $pending['instance']->setDeviceInstances($pending['deviceInstances']);
        }
        $this->originalDeviceInstances = [];
    }

    private function mustFilter(LabInstance $labInstance, $context): bool
    {
        if (!method_exists($context, 'hasAttribute') || !$context->hasAttribute('groups')) {
            return false;
        }

        $groups = (array) $context->getAttribute('groups');

        // The worker and the sandbox always get the full picture
        if (in_array('worker', $groups, true) || in_array('sandbox', $groups, true)) {
            return false;
        }

        if (!in_array('api_get_lab_instance', $groups, true)) {
            return false;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            return true;
        }

        $lab = $labInstance->getLab();
        if (null === $lab) {
            return true;
        }

        return !$user->isAdministrator() && $user !== $lab->getAuthor();
    }
}
