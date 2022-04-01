<?php

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\Hypervisor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class HypervisorFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $hypervisor = new Hypervisor();

        $hypervisor
            ->setName('qemu')
        ;
        $manager->persist($hypervisor);
        $hypervisor = new Hypervisor();
        $hypervisor
            ->setName('lxc')
        ;
        $manager->persist($hypervisor);
        $manager->flush();
    }

}
