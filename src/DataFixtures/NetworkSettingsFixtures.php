<?php

namespace App\DataFixtures;

use App\Entity\NetworkSettings;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;


class NetworkSettingsFixtures extends Fixture
{

    public function load(ObjectManager $manager): void
    {


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

/*        $networkSettings = new NetworkSettings();
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
*/

        $manager->flush();
    }
}
