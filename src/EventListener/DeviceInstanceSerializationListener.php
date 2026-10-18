<?php

namespace App\EventListener;

use App\Entity\DeviceInstance;
use App\Service\Instance\DeviceInfoCache;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface as JMSEventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\Metadata\StaticPropertyMetadata;

/**
 * Ajoute l'IP (mise en cache Redis) aux DeviceInstance serialisees pour le
 * front (groupes api_get_lab_instance / api_get_device_instance).
 *
 * Cela evite de dupliquer l'injection dans chaque methode de InstanceController
 * qui serialise avec ces groupes. Le groupe "worker" et "sandbox" ne sont pas
 * touches : le worker n'a pas besoin de connaitre l'IP et le payload reste leger.
 *
 * Les deux proprietes exposees :
 *   - ip  : premiere IPv4 connue (ou null)
 *   - ips : liste complete des adresses (IPv4 puis IPv6)
 */
class DeviceInstanceSerializationListener implements JMSEventSubscriberInterface
{
    public function __construct(private DeviceInfoCache $cache)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [[
            'event' => 'serializer.post_serialize',
            'class' => DeviceInstance::class,
            'method' => 'onPostSerialize',
        ]];
    }

    public function onPostSerialize(ObjectEvent $event): void
    {
        $context = $event->getContext();
        if (!method_exists($context, 'hasAttribute') || !$context->hasAttribute('groups')) {
            return;
        }
        $groups = (array) $context->getAttribute('groups');

        // Le worker et le sandbox n'ont pas besoin de l'IP : ne pas alourdir leur payload.
        if (in_array('worker', $groups, true) || in_array('sandbox', $groups, true)) {
            return;
        }
        if (!in_array('api_get_lab_instance', $groups, true)
            && !in_array('api_get_device_instance', $groups, true)) {
            return;
        }

        /** @var DeviceInstance $deviceInstance */
        $deviceInstance = $event->getObject();
        $cached = $this->cache->get($deviceInstance->getUuid());
        $ips = $cached['ips'] ?? [];

        $firstIp = null;
        foreach ($ips as $interface) {
            if (!empty($interface['ipv4'][0])) {
                $firstIp = $interface['ipv4'][0];
                break;
            }
        }
        if (null === $firstIp) {
            foreach ($ips as $interface) {
                if (!empty($interface['ipv6'][0])) {
                    $firstIp = $interface['ipv6'][0];
                    break;
                }
            }
        }

        // visitProperty() prend la valeur en 2eme argument (le StaticPropertyMetadata
        // ne sert qu'a nommer la propriete et a la rattacher aux groupes).
        $visitor = $event->getVisitor();
        $visitor->visitProperty(new StaticPropertyMetadata(
            DeviceInstance::class,
            'ips',
            $ips,
            ['api_get_lab_instance', 'api_get_device_instance']
        ), $ips);
        $visitor->visitProperty(new StaticPropertyMetadata(
            DeviceInstance::class,
            'ip',
            $firstIp,
            ['api_get_lab_instance', 'api_get_device_instance']
        ), $firstIp);
    }
}
