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

        foreach (range(1, self::COUNT) as $number) {
            $device = new Device();

            $device
                ->setName($faker->firstName)
                ->setBrand($faker->company)
                ->setModel($faker->lastName)
                ->setLaunchOrder($faker->numberBetween(0, 999))
            ;

            $manager->persist($device);

            $this->addReference('device' . $number, $device);
        }

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
        ];
    }
}
