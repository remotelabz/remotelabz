<?php

namespace App\DataFixtures;

use App\Entity\Flavor;
use Faker\Factory as RandomDataFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class FlavorFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $faker = RandomDataFactory::create();

        for ($i = 0; $i < 10; $i++)
        {
            $flavor = new Flavor();

            $flavor
                ->setName($faker->word)
                ->setMemory($faker->numberBetween(1000000000, 4000000000))
                ->setDisk($faker->numberBetween(1000000000,8000000000))
            ;

            $manager->persist($flavor);
        }

        $manager->flush();
    }
}
