<?php

namespace App\DataFixtures;

use App\Entity\Hypervisor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
;

class HypervisorFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $hypervisor = new Hypervisor();

        $hypervisor
            ->setName('qemu')
        ;
        $manager->persist($hypervisor);
        $this->addReference('qemu', $hypervisor);
        $hypervisor = new Hypervisor();
        $hypervisor
            ->setName('lxc')
        ;

        $manager->persist($hypervisor);
        $this->addReference('lxc', $hypervisor);
        $manager->flush();
    }

}
