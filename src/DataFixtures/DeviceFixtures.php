<?php

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\Device;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class DeviceFixtures extends Fixture
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
                ->setVirtuality($faker->numberBetween(0, 2))
                ->setType($faker->randomElement(['switch', 'vm']))
                ->setHypervisor('qemu')
            ;

            $manager->persist($device);

            $this->addReference('device' . $number, $device);
        }

        $manager->flush();
    }
}
