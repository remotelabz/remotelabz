<?php

namespace App\DataFixtures;

use App\Entity\ControlProtocolType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ControlProtocolTypeFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {

        $controlProtocolType = new ControlProtocolType();
        $controlProtocolType->setName('vnc');
        $manager->persist($controlProtocolType);
        $this->addReference('vnc', $controlProtocolType);

        $controlProtocolType = new ControlProtocolType();
        $controlProtocolType->setName('login');
        $manager->persist($controlProtocolType);
        $this->addReference('login', $controlProtocolType);

        $controlProtocolType = new ControlProtocolType();
        $controlProtocolType->setName('serial');
        $manager->persist($controlProtocolType);
        $this->addReference('serial', $controlProtocolType);

        $manager->flush();
    }
    
}
