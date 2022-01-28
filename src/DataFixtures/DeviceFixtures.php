<?php

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\Device;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class DeviceFixtures extends Fixture implements DependentFixtureInterface
{
    public const COUNT = 10;

    public function load(ObjectManager $manager)
    {
        $faker = Factory::create();

        // foreach (range(1, self::COUNT) as $number) {
        //     $device = new Device();

        //     $device
        //         ->setName($faker->firstName)
        //         ->setBrand($faker->company)
        //         ->setModel($faker->lastName)
        //         ->setLaunchOrder($faker->numberBetween(0, 999))
        //         ->setVirtuality($faker->numberBetween(0, 2))
        //         ->setFlavor($this->getReference('flavor-x-small'))
        //         ->setOperatingSystem($this->getReference('operating-system-debian'))
        //         ->setType($faker->randomElement(['switch', 'vm']))
        //         ->setHypervisor('qemu')
        //     ;

        //     $manager->persist($device);

        //     $this->addReference('device' . $number, $device);
        // }

        $device = new Device();

        $device
            ->setName('Linux Alpine')
            ->setBrand('Test')
            ->setModel('Test model')
            ->setLaunchOrder($faker->numberBetween(0, 999))
            ->setVirtuality($faker->numberBetween(0, 2))
            ->setFlavor($this->getReference('flavor-x-small'))
            ->setOperatingSystem($this->getReference('operating-system-Alpine'))
            ->setType($faker->randomElement(['vm']))
            ->setHypervisor('qemu')
            ->setCreatedAt(new \DateTime())
            ->setIsTemplate(true)
        ;

        $manager->persist($device);
        $this->addReference('device-alpine', $device);

        $device = new Device();
        $device
            ->setName('Linux Debian')
            ->setBrand('Test')
            ->setModel('Test model')
            ->setLaunchOrder($faker->numberBetween(0, 999))
            ->setVirtuality($faker->numberBetween(0, 2))
            ->setFlavor($this->getReference('flavor-x-small'))
            ->setOperatingSystem($this->getReference('operating-system-Debian'))
            ->setType($faker->randomElement(['vm']))
            ->setHypervisor('qemu')
            ->setCreatedAt(new \DateTime())
            ->setIsTemplate(true)
        ;
        $manager->persist($device);
        $this->addReference('device-debian', $device);

        $device = new Device();
        $device
            ->setName('Linux Ubuntu with X')
            ->setBrand('Test')
            ->setModel('Test model')
            ->setLaunchOrder($faker->numberBetween(0, 999))
            ->setVirtuality($faker->numberBetween(0, 2))
            ->setFlavor($this->getReference('flavor-x-large'))
            ->setOperatingSystem($this->getReference('operating-system-Ubuntu'))
            ->setType($faker->randomElement(['vm']))
            ->setHypervisor('qemu')
            ->setCreatedAt(new \DateTime())
            ->setIsTemplate(true)
        ;
        $manager->persist($device);
        $this->addReference('device-ubuntu', $device);

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            FlavorFixtures::class,
            OperatingSystemFixtures::class
        ];
    }
}
