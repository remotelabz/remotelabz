<?php

namespace App\DataFixtures;

use App\Entity\NetworkSettings;
use Faker\Factory as RandomDataFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class NetworkSettingsFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $randomData = RandomDataFactory::create();

        foreach (range(0, 9) as $number) {
            $networkSettings = new NetworkSettings();

            $networkSettings
                ->setName($randomData->lastName)
                ->setIp($randomData->localIpv4)
                ->setIpv6($randomData->ipv6)
                ->setPrefix4($randomData->numberBetween(2,30))
                ->setPrefix6($randomData->numberBetween(8,120))
                ->setGateway($randomData->localIpv4)
                ->setProtocol($randomData->randomElement(['VNC', 'Telnet', 'SSH']))
                ->setPort($randomData->numberBetween(8192, 65536))
            ;

            $manager->persist($networkSettings);
        }

        $manager->flush();
    }
}
