<?php

namespace App\Tests\Service;

use App\Entity\DeviceInstance;
use App\Service\Instance\DeviceInfoCache;
use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Verifie bout en bout le listener DeviceInstanceSerializationListener : les
 * champs ip/ips sont ajoutes aux groupes front et absents du groupe worker.
 */
class IpSerializationTest extends KernelTestCase
{
    public function testIpsExposedForFrontGroupsOnly(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var DeviceInfoCache $cache */
        $cache = $container->get(DeviceInfoCache::class);
        $em = $container->get('doctrine')->getManager();
        $serializer = $container->get('jms_serializer');

        $di = $em->getRepository(DeviceInstance::class)->findOneBy([]);
        if (null === $di) {
            $this->markTestSkipped('No device instance in the test database.');
        }

        $uuid = $di->getUuid();
        $cache->store($uuid, [
            'ips' => [['name' => 'eth0', 'mac' => '52:54:00:aa:bb:01', 'ipv4' => ['10.11.0.42'], 'ipv6' => []]],
        ], '192.168.11.132');

        $front = json_decode(
            $serializer->serialize($di, 'json', SerializationContext::create()->setGroups(['api_get_device_instance'])),
            true
        );
        $this->assertSame('10.11.0.42', $front['ip'] ?? null);
        $this->assertSame(
            [['name' => 'eth0', 'mac' => '52:54:00:aa:bb:01', 'ipv4' => ['10.11.0.42'], 'ipv6' => []]],
            $front['ips'] ?? null
        );

        $worker = json_decode(
            $serializer->serialize($di, 'json', SerializationContext::create()->setGroups(['worker'])),
            true
        );
        $this->assertArrayNotHasKey('ip', $worker);
        $this->assertArrayNotHasKey('ips', $worker);

        $cache->forget($uuid);
        $after = json_decode(
            $serializer->serialize($di, 'json', SerializationContext::create()->setGroups(['api_get_device_instance'])),
            true
        );
        $this->assertNull($after['ip'] ?? null);
        $this->assertSame([], $after['ips'] ?? null);
    }
}
