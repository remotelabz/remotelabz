<?php

namespace App\DataFixtures;

use App\Entity\NetworkInterface;
use App\DataFixtures\DeviceFixtures;
use App\Entity\NetworkSettings;
use App\Entity\Device;
use Doctrine\Bundle\FixturesBundle\Fixture;
use App\DataFixtures\NetworkSettingsFixtures;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class NetworkInterfaceFixtures extends Fixture implements DependentFixtureInterface
{

    public function load(ObjectManager $manager): void
    {

        $networkInterface = new NetworkInterface();

        $networkInterface
            ->setName('ToAlpine')
            ->setType("tap")
            ->setSettings(
                $this->getReference(
                    'network_settings3', NetworkSettings::class
                )
            )
            ->setDevice(
                $this->getReference(
                    'device-alpine', Device::class
                )
            )
            //->setMacAddress('52:54:00:00:00:01')
            ->setIsTemplate(true)
            ->setConnection(0);

        $manager->persist($networkInterface);

/*        $networkInterface = new NetworkInterface();
        $networkInterface
            ->setName('ToDebian')
            ->setType("tap")
            ->setSettings(
                $this->getReference(
                    'network_settings4'
                )
            )
            ->setDevice(
                $this->getReference(
                    'device-debian'
                )
            )
            //->setMacAddress('52:54:00:00:00:02')
            ->setIsTemplate(true);
        $manager->persist($networkInterface);

        $networkInterface = new NetworkInterface();
        $networkInterface
            ->setName('ToUbuntuX')
            ->setType("tap")
            ->setSettings(
                $this->getReference(
                    'network_settings5'
                )
            )
            ->setDevice(
                $this->getReference(
                    'device-ubuntu14X'
                )
            )
            //->setMacAddress('52:54:00:00:00:03')
            ->setIsTemplate(true);

        $manager->persist($networkInterface);
*/
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            NetworkSettingsFixtures::class,
            DeviceFixtures::class
        ];
    }
}
