<?php

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\NetworkInterface;
use App\DataFixtures\DeviceFixtures;
use Doctrine\Bundle\FixturesBundle\Fixture;
use App\DataFixtures\NetworkSettingsFixtures;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class NetworkInterfaceFixtures extends Fixture implements DependentFixtureInterface
{
    public const COUNT = 10;

    public function load(ObjectManager $manager)
    {
        $faker = Factory::create();
        
        // foreach (range(1, self::COUNT) as $number) {
        //     $networkInterface = new NetworkInterface();

        //     $networkInterface
        //         ->setName('eth'.$faker->numberBetween(0, 100))
        //         ->setType($faker->randomElement([
        //             NetworkInterface::TYPE_TAP,
        //             NetworkInterface::TYPE_OVS
        //         ]))
        //         ->setSettings(
        //             $this->getReference(
        //                 'network_settings' . ($number % 10 + 1)
        //             )
        //         )
        //         ->setDevice(
        //             $this->getReference(
        //                 'device' . $faker->numberBetween(
        //                     1,
        //                     DeviceFixtures::COUNT
        //                 )
        //             )
        //         )
        //         ->setMacAddress($faker->macAddress)
        //     ;

        //     $manager->persist($networkInterface);
        // }

        $networkInterface = new NetworkInterface();

        $networkInterface
            ->setName('eth0')
            ->setType("tap")
            ->setSettings(
                $this->getReference(
                    'network_settings1'
                )
            )
            ->setDevice(
                $this->getReference(
                    'device-alpine'
                )
            )
            ->setMacAddress('52:54:00:00:00:01')
        ;

        $manager->persist($networkInterface);

        $networkInterface = new NetworkInterface();

        $networkInterface
            ->setName('eth1')
            ->setType("tap")
            ->setSettings(
                $this->getReference(
                    'network_settings2'
                )
            )
            ->setDevice(
                $this->getReference(
                    'device-debian'
                )
            )
            ->setMacAddress('52:54:00:00:00:02')
        ;

        $manager->persist($networkInterface);

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            NetworkSettingsFixtures::class,
            DeviceFixtures::class
        ];
    }
}
