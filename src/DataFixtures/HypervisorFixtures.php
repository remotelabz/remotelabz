<?php

namespace App\DataFixtures;

use App\Entity\Hypervisor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class HypervisorFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $hypervisor = new Hypervisor();

        $hypervisor
            ->setName("LXC")
            ->setCommand("lxc")
            ->setArguments("-v")
        ;

        $manager->persist($hypervisor);

        $manager->flush();
    }
}
