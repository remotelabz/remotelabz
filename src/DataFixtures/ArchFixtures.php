<?php

namespace App\DataFixtures;

use App\Entity\Arch;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ArchFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $architectures = ['x86', 'x86_64', 'arm', 'arm64'];

        foreach ($architectures as $archName) {
            $arch = new Arch();
            $arch->setName($archName);
            $manager->persist($arch);

            if ($archName === 'x86_64') {
               $this->setReference('arch-x86_64', $arch);
            }
        }

        $manager->flush();
    }
}