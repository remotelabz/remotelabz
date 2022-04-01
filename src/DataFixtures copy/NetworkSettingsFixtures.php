<?php

namespace App\DataFixtures;

use App\Entity\NetworkSettings;
use Faker\Factory as RandomDataFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class NetworkSettingsFixtures extends Fixture
{
    public const COUNT = 10;

    public function load(ObjectManager $manager)
    {
        $randomData = RandomDataFactory::create();

        // foreach (range(1, self::COUNT) as $number) {
        //     $networkSettings = new NetworkSettings();

        //     $networkSettings
        //         ->setName($randomData->lastName)
        //         ->setIp($randomData->localIpv4)
        //         ->setIpv6($randomData->ipv6)
        //         ->setPrefix4($randomData->numberBetween(2, 30))
        //         ->setPrefix6($randomData->numberBetween(8, 120))
        //         ->setGateway($randomData->localIpv4)
        //         ->setProtocol($randomData->randomElement([
        //             'VNC',
        //             'Telnet',
        //             'SSH'
        //         ]))
        //         ->setPort($randomData->numberBetween(8192, 65536))
        //     ;

        //     $manager->persist($networkSettings);

        //     $this->addReference('network_settings' . $number, $networkSettings);
        // }

        $networkSettings = new NetworkSettings();
        $networkSettings
            ->setName('ToAlpineDevice')
            ->setIp(NULL)
            ->setIpv6(NULL)
            ->setPrefix4(NULL)
            ->setPrefix6(NULL)
            ->setGateway(NULL)
            ->setProtocol("VNC")
            ->setPort(NULL)
        ;
        $manager->persist($networkSettings);
        $this->addReference('network_settings3', $networkSettings);

        $networkSettings = new NetworkSettings();
        $networkSettings
            ->setName('ToDebianDevice')
            ->setIp(NULL)
            ->setIpv6(NULL)
            ->setPrefix4(NULL)
            ->setPrefix6(NULL)
            ->setGateway(NULL)
            ->setProtocol("VNC")
            ->setPort(NULL)
        ;
        $manager->persist($networkSettings);
        $this->addReference('network_settings4', $networkSettings);

        $networkSettings = new NetworkSettings();
        $networkSettings
            ->setName('ToUbuntuDevice')
            ->setIp(NULL)
            ->setIpv6(NULL)
            ->setPrefix4(NULL)
            ->setPrefix6(NULL)
            ->setGateway(NULL)
            ->setProtocol("VNC")
            ->setPort(NULL)
        ;
        $manager->persist($networkSettings);
        $this->addReference('network_settings5', $networkSettings);


        $manager->flush();
    }
}
