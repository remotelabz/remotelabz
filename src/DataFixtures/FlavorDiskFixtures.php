<?php

namespace App\DataFixtures;


use App\Entity\FlavorDisk;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
;

class FlavorDiskFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $flavorDisk = new FlavorDisk();
        $flavorDisk
            ->setName("800MB")
            ->setDisk(800)
        ;
        $manager->persist($flavorDisk);
        $this->addReference('flavor-disk-800mb', $flavorDisk);

        $flavorDisk = new FlavorDisk();
        $flavorDisk
            ->setName("1GB")
            ->setDisk(1024)
        ;
        $manager->persist($flavorDisk);
        $this->addReference('flavor-disk-1gb', $flavorDisk);

        $flavorDisk = new FlavorDisk();
        $flavorDisk
            ->setName("3GB")
            ->setDisk(3072)
        ;
        $manager->persist($flavorDisk);
        $this->addReference('flavor-disk-3gb', $flavorDisk);

        $manager->flush();
    }
}
