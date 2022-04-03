<?php

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\Device;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
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
            ->setHypervisor($this->getReference('qemu'))
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
            ->setHypervisor($this->getReference('qemu'))
            ->setCreatedAt(new \DateTime())
            ->setIsTemplate(true)
        ;
        $manager->persist($device);
        $this->addReference('device-debian', $device);

        $device = new Device();
        $device
            ->setName('Linux Ubuntu 14 with X')
            ->setBrand('Test')
            ->setModel('Test model')
            ->setLaunchOrder($faker->numberBetween(0, 999))
            ->setVirtuality($faker->numberBetween(0, 2))
            ->setFlavor($this->getReference('flavor-x-large'))
            ->setOperatingSystem($this->getReference('operating-system-Ubuntu14X'))
            ->setType($faker->randomElement(['vm']))
            ->setHypervisor($this->getReference('qemu'))
            ->setCreatedAt(new \DateTime())
            ->setIsTemplate(true)
        ;
        $manager->persist($device);
        $this->addReference('device-ubuntu14X', $device);

        $device = new Device();
        $device
            ->setName('Migration')
            ->setBrand('Debian')
            ->setModel('Version Bulleye')
            ->setLaunchOrder(0)
            ->setVirtuality(0)
            ->setFlavor($this->getReference('flavor-xx-small'))
            ->setOperatingSystem($this->getReference('MigrationOS'))
            ->setType($faker->randomElement(['container']))
            ->setHypervisor($this->getReference('lxc'))
            ->setCreatedAt(new \DateTime())
            ->setIsTemplate(true)
            ->setVnc(true)
        ;
        $manager->persist($device);
        $this->addReference('Migration', $device);

        $device = new Device();
        $device
            ->setName('Ubuntu20LTS-cnt')
            ->setBrand('Ubuntu')
            ->setModel('Version Focal 20 LTS')
            ->setLaunchOrder(0)
            ->setVirtuality(0)
            ->setFlavor($this->getReference('flavor-xx-small'))
            ->setOperatingSystem($this->getReference('Ubuntu20LTSOS'))
            ->setType($faker->randomElement(['container']))
            ->setHypervisor($this->getReference('lxc'))
            ->setCreatedAt(new \DateTime())
            ->setIsTemplate(true)
            ->setVnc(true)
        ;
        $manager->persist($device);
        $this->addReference('Ubuntu20LTS-cnt', $device);

        $device = new Device();
        $device
            ->setName('Alpine3.15-cnt')
            ->setBrand('Alpine')
            ->setModel('Version 3.15')
            ->setLaunchOrder(0)
            ->setVirtuality(0)
            ->setFlavor($this->getReference('flavor-xx-small'))
            ->setOperatingSystem($this->getReference('Alpine3.15OS'))
            ->setType($faker->randomElement(['container']))
            ->setHypervisor($this->getReference('lxc'))
            ->setCreatedAt(new \DateTime())
            ->setIsTemplate(true)
            ->setVnc(true)
        ;
        $manager->persist($device);
        $this->addReference('Alpine3.15-cnt', $device);
        
        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            FlavorFixtures::class,
            OperatingSystemFixtures::class,
            HypervisorFixtures::class

        ];
    }
}
