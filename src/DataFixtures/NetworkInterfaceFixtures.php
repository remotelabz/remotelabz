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
            ->setName('eth'.$faker->numberBetween(0, 100))
            ->setType("tap")
            ->setSettings(
                $this->getReference(
                    'network_settings1'
                )
            )
            ->setDevice(
                $this->getReference(
                    'device1'
                )
            )
            ->setMacAddress('00:22:33:44:55:66')
        ;

        $networkInterface
            ->setName('eth'.$faker->numberBetween(0, 100))
            ->setType("tap")
            ->setSettings(
                $this->getReference(
                    'network_settings2'
                )
            )
            ->setDevice(
                $this->getReference(
                    'device2'
                )
            )
            ->setMacAddress('00:22:33:44:55:67')
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
