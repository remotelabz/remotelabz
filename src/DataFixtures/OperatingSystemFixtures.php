<?php

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\OperatingSystem;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class OperatingSystemFixtures extends Fixture
{
    public const COUNT = 5;

    public function load(ObjectManager $manager)
    {
        $faker = Factory::create();

        // foreach (range(1, self::COUNT) as $number) {
        //     $operatingSystem = new OperatingSystem();

        //     $operatingSystem
        //         ->setName($faker->company . ' ' . $faker->numberBetween(10, 30))
        //         ->setImage('/dev/null')
        //     ;

        //     $manager->persist($operatingSystem);
        // }

        $operatingSystem = new OperatingSystem();

        $operatingSystem
            ->setName('CirrOS')
            ->setImageUrl('http://download.cirros-cloud.net/0.4.0/cirros-0.4.0-x86_64-disk.img')
        ;

        $manager->persist($operatingSystem);

        $this->setReference('operating-system-CirrOS', $operatingSystem);

        $operatingSystem = new OperatingSystem();

        $operatingSystem
            ->setName('Alpine')
            ->setImageUrl('http://194.57.105.124/~fnolot/alpinelab1.img')
        ;

        $manager->persist($operatingSystem);

        $this->setReference('operating-system-Alpine', $operatingSystem);

        $operatingSystem = new OperatingSystem();
        $operatingSystem
            ->setName('Debian 10')
            ->setImageUrl('http://194.57.105.124/~fnolot/debian10-20190905.img')
        ;
        $manager->persist($operatingSystem);
        $this->setReference('operating-system-Debian', $operatingSystem);

        $operatingSystem = new OperatingSystem();
        $operatingSystem
            ->setName('Ubuntu with X')
            ->setImageUrl('http://194.57.105.124/~fnolot/Ubuntu-server-14-X.img')
        ;
        $manager->persist($operatingSystem);
        $this->setReference('operating-system-Ubuntu', $operatingSystem);

        $operatingSystem = new OperatingSystem();
        $operatingSystem
            ->setName('Ubuntu LXDE')
            ->setImageUrl('http://194.57.105.124/~fnolot/ubuntu-18-SrvLxde.img')
        ;
        $manager->persist($operatingSystem);
        $this->setReference('operating-system-UbuntuLXDE', $operatingSystem);

        $manager->flush();
    }
}
