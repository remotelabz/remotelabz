<?php

namespace App\DataFixtures;


use App\Entity\Flavor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class FlavorFixtures extends Fixture
{
    public const COUNT = 10;

    public function load(ObjectManager $manager)
    {

        // Basic flavor
        $flavor = new Flavor();
        $flavor
            ->setName("xx-small")
            ->setMemory(256)
            ->setDisk(4)
        ;
        $manager->persist($flavor);
        $this->addReference('flavor-xx-small', $flavor);
        
        $flavor = new Flavor();
        $flavor
            ->setName("x-small")
            ->setMemory(512)
            ->setDisk(8)
        ;
        $manager->persist($flavor);
        $this->addReference('flavor-x-small', $flavor);

        $flavor = new Flavor();
        $flavor
            ->setName("x-large")
            ->setMemory(4096)
            ->setDisk(30)
        ;
        $manager->persist($flavor);
        $this->addReference('flavor-x-large', $flavor);

  

        $manager->flush();
    }
}
