<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use App\Entity\Swarm;

class SwarmFixtures extends Fixture
{
    public const LAST_SWARM = 'last-swarm';

    public function load(ObjectManager $manager)
    {
        for ($i = 0; $i < 5; $i++) {
            $swarm = new Swarm();

            $swarm->setName(sprintf('Swarm %d', $i));

            $manager->persist($swarm);
        }
 
        $manager->flush();

        $this->addReference(self::LAST_SWARM, $swarm);
    }
}
