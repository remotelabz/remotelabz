<?php

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\OperatingSystem;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
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
            ->setImage('http://download.cirros-cloud.net/0.4.0/cirros-0.4.0-x86_64-disk.img')
        ;

        $manager->persist($operatingSystem);

        $this->setReference('operating-system-debian', $operatingSystem);
        
        $manager->flush();
    }
}
