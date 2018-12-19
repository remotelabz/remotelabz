<?php

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\POD;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class PODFixtures extends Fixture
{
    public const COUNT = 10;

    public function load(ObjectManager $manager)
    {
        $faker = Factory::create();

        foreach (range(1, self::COUNT) as $number) {
            $pod = new POD();

            $pod
                ->setName($faker->firstName)
            ;

            $manager->persist($pod);

            $this->addReference('pod' . $number, $pod);
        }

        $manager->flush();
    }
}
