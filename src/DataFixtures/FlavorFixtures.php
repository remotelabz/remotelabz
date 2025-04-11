<?php

namespace App\DataFixtures;


use App\Entity\Flavor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
;

class FlavorFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {

        // Basic flavor
        $flavor = new Flavor();
        $flavor
            ->setName("xx-small-256")
            ->setMemory(256)
            ->setDisk(4)
        ;
        $manager->persist($flavor);
        $this->addReference('flavor-xx-small', $flavor);
        
        $flavor = new Flavor();
        $flavor
            ->setName("x-small-512")
            ->setMemory(512)
            ->setDisk(8)
        ;
        $manager->persist($flavor);
        $this->addReference('flavor-x-small', $flavor);

        $flavor = new Flavor();
        $flavor
            ->setName("small-1024")
            ->setMemory(1024)
            ->setDisk(8)
        ;
        $manager->persist($flavor);
        $this->addReference('flavor-small', $flavor);

        $flavor = new Flavor();
        $flavor
            ->setName("large-2048")
            ->setMemory(2048)
            ->setDisk(8)
        ;
        $manager->persist($flavor);
        $this->addReference('flavor-large', $flavor);


        $flavor = new Flavor();
        $flavor
            ->setName("x-large-4096")
            ->setMemory(4096)
            ->setDisk(30)
        ;
        $manager->persist($flavor);
        $this->addReference('flavor-x-large', $flavor);

        $manager->flush();
    }
}
