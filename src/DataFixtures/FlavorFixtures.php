<?php

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\Flavor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class FlavorFixtures extends Fixture
{
    public const COUNT = 10;

    public function load(ObjectManager $manager)
    {
        $faker = Factory::create();

        foreach (range(1, self::COUNT) as $number) {
            $flavor = new Flavor();

            $flavor
                ->setName($faker->firstName)
                ->setMemory($faker->numberBetween(1000000000, 4000000000))
                ->setDisk($faker->numberBetween(1000000000, 8000000000))
            ;

            $manager->persist($flavor);

            $this->addReference('flavor' . $number, $flavor);
        }

        // Basic flavor
        $flavor = new Flavor();

        $flavor
            ->setName("x-small")
            ->setMemory(512)
            ->setDisk(8)
        ;

        $manager->persist($flavor);

        $this->addReference('flavor-x-small', $flavor);

        $manager->flush();
    }
}
