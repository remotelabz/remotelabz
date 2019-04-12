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

        foreach (range(1, self::COUNT) as $number) {
            $operatingSystem = new OperatingSystem();

            $operatingSystem
                ->setName($faker->company . ' ' . $faker->numberBetween(10, 30))
                ->setImage('/dev/null')
            ;

            $manager->persist($operatingSystem);
        }

        $operatingSystem = new OperatingSystem();

        $operatingSystem
            ->setName('Debian Squeeze')
            ->setImage('https://people.debian.org/~aurel32/qemu/amd64/debian_squeeze_amd64_standard.qcow2')
        ;

        $manager->persist($operatingSystem);

        $this->setReference('operating-system-debian', $operatingSystem);
        
        $manager->flush();
    }
}
