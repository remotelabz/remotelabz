<?php

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\Hypervisor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class HypervisorFixtures extends Fixture
{
    public const COUNT = 5;

    public function load(ObjectManager $manager)
    {
        $faker = Factory::create();

        foreach (range(1, self::COUNT) as $number) {
            $hypervisor = new Hypervisor();

            $hypervisor
                ->setName($faker->firstName)
                ->setCommand($faker->word)
                ->setArguments('-v')
            ;

            $manager->persist($hypervisor);

            $this->addReference('hypervisor' . $number, $hypervisor);
        }

        $manager->flush();
    }
}
