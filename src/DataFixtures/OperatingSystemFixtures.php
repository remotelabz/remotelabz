<?php

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\OperatingSystem;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class OperatingSystemFixtures extends Fixture implements DependentFixtureInterface
{
    public const COUNT = 5;

    public function load(ObjectManager $manager)
    {
        $faker = Factory::create();

        foreach (range(1, self::COUNT) as $number) {
            $operatingSystem = new OperatingSystem();

            $operatingSystem
                ->setName($faker->company . ' ' . $faker->numberBetween(10, 30))
                ->setPath('/dev/null')
                ->setHypervisor(
                    $this->getReference(
                        'hypervisor' . $faker->numberBetween(
                            1,
                            HypervisorFixtures::COUNT
                        )
                    )
                )
                ->setFlavor(
                    $this->getReference(
                        'flavor' . $faker->numberBetween(
                            1,
                            FlavorFixtures::COUNT
                        )
                    )
                )
            ;

            $manager->persist($operatingSystem);
        }
        
        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            HypervisorFixtures::class,
            FlavorFixtures::class
        ];
    }
}
