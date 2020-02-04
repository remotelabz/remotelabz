<?php

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\Lab;
use App\Entity\Group;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class GroupFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public const COUNT = 5;

    public function load(ObjectManager $manager)
    {
        $faker = Factory::create();

        $group = new Group();
        $group
            ->setName('Default group')
            ->setSlug('default-group')
            ->setDescription('The default group.')
            ->addUser($this->getReference('root'), Group::ROLE_OWNER)
            ->setVisibility(2);
        ;

        $manager->persist($group);

        $this->addReference('default-group', $group);

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            UserFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['groups'];
    }
}
