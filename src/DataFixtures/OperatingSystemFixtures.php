<?php

namespace App\DataFixtures;

use App\Entity\OperatingSystem;
use App\Entity\Hypervisor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class OperatingSystemFixtures extends Fixture implements DependentFixtureInterface
{
    public const COUNT = 5;

    public function load(ObjectManager $manager): void
    {
        $arch = $this->getReference('arch-x86_64', \App\Entity\Arch::class);

        $operatingSystem = new OperatingSystem();

        $operatingSystem
            ->setName('Alpine3.10')
            ->setImageUrl('https://docs.remotelabz.com/rlz-img/alpinelab1.qcow2')
            ->setHypervisor($this->getReference('qemu', Hypervisor::class))
            ->setArch($arch)
        ;

        $manager->persist($operatingSystem);

        $this->setReference('operating-system-Alpine', $operatingSystem);

        $operatingSystem = new OperatingSystem();
		$operatingSystem->setName('Migration');
		$operatingSystem
            ->setImageFilename('Migration')
            ->setHypervisor($this->getReference('lxc', Hypervisor::class))
            ->setArch($arch)
        ;
        $manager->persist($operatingSystem);
        $this->setReference('MigrationOS', $operatingSystem);

        $operatingSystem = new OperatingSystem();
   		$operatingSystem->setName('Ubuntu24.04SrvLTS');
		$operatingSystem
            ->setImageFilename('Ubuntu24LTS')
            ->setHypervisor($this->getReference('lxc', Hypervisor::class))
            ->setArch($arch)
        ;
        $manager->persist($operatingSystem);
        $this->setReference('Ubuntu24LTSOS', $operatingSystem);

        $operatingSystem = new OperatingSystem();
        $operatingSystem->setName('Debian11.4');
		$operatingSystem
            ->setImageFilename('Debian')
            ->setHypervisor($this->getReference('lxc', Hypervisor::class))
            ->setArch($arch)            
        ;
        $manager->persist($operatingSystem);
        $this->setReference('DebianOS', $operatingSystem);


        $operatingSystem = new OperatingSystem();
        $operatingSystem->setName('Alpine-stable');
		$operatingSystem
            ->setImageFilename('Alpine-Stable')
            ->setHypervisor($this->getReference('lxc', Hypervisor::class))
            ->setArch($arch)            
        ;
        $manager->persist($operatingSystem);
        $this->setReference('Alpine-stableOS', $operatingSystem);

        $operatingSystem = new OperatingSystem();
        $operatingSystem->setName('Natif');
		$operatingSystem
            ->setImageFilename('Natif')
            ->setHypervisor($this->getReference('natif', Hypervisor::class))
            ->setArch($arch)            
        ;
        $manager->persist($operatingSystem);
        $this->setReference('Natif', $operatingSystem);


        $manager->flush();
    }
    public function getDependencies(): array
    {
        return [
            HypervisorFixtures::class
        ];
    }
}
