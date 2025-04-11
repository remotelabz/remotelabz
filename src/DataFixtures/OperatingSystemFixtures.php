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

        $operatingSystem = new OperatingSystem();

        $operatingSystem
            ->setName('Alpine3.10')
            ->setImageUrl('http://194.57.105.124/~fnolot/alpinelab1.img')
            ->setHypervisor($this->getReference('qemu', Hypervisor::class))
        ;

        $manager->persist($operatingSystem);

        $this->setReference('operating-system-Alpine', $operatingSystem);

/*        $operatingSystem = new OperatingSystem();
        $operatingSystem
            ->setName('Debian 10')
            ->setImageUrl('http://194.57.105.124/~fnolot/debian10-20190905.img')
            ->setHypervisor($this->getReference('qemu'))
        ;
        $manager->persist($operatingSystem);
        $this->setReference('operating-system-Debian', $operatingSystem);

        $operatingSystem = new OperatingSystem();
        $operatingSystem
            ->setName('Ubuntu with X')
            ->setImageUrl('http://194.57.105.124/~fnolot/Ubuntu-server-14-X.img')
            ->setHypervisor($this->getReference('qemu'))
        ;
        $manager->persist($operatingSystem);
        $this->setReference('operating-system-Ubuntu14X', $operatingSystem);

        $operatingSystem = new OperatingSystem();
        $operatingSystem
            ->setName('Ubuntu 18 LXDE')
            ->setImageUrl('http://194.57.105.124/~fnolot/ubuntu-18-SrvLxde.img')
            ->setHypervisor($this->getReference('qemu'))
        ;
        $manager->persist($operatingSystem);
        $this->setReference('operating-system-Ubuntu18LXDE', $operatingSystem);
*/
        $operatingSystem = new OperatingSystem();
        $operatingSystem
            ->setName('Migration')
            ->setImageFilename('Migration')
            ->setHypervisor($this->getReference('lxc', Hypervisor::class))
        ;
        $manager->persist($operatingSystem);
        $this->setReference('MigrationOS', $operatingSystem);

        $operatingSystem = new OperatingSystem();
        $operatingSystem
            ->setName('Ubuntu24.04SrvLTS')
            ->setImageFilename('Ubuntu24LTS')
            ->setHypervisor($this->getReference('lxc', Hypervisor::class))
        ;
        $manager->persist($operatingSystem);
        $this->setReference('Ubuntu24LTSOS', $operatingSystem);

        $operatingSystem = new OperatingSystem();
        $operatingSystem
            ->setName('Debian11.4')
            ->setImageFilename('Debian')
            ->setHypervisor($this->getReference('lxc', Hypervisor::class))
        ;
        $manager->persist($operatingSystem);
        $this->setReference('DebianOS', $operatingSystem);


        $operatingSystem = new OperatingSystem();
        $operatingSystem
            ->setName('Alpine-stable')
            ->setImageFilename('Alpine-stable')
            ->setHypervisor($this->getReference('lxc', Hypervisor::class))
        ;
        $manager->persist($operatingSystem);
        $this->setReference('Alpine-stableOS', $operatingSystem);

        $operatingSystem = new OperatingSystem();
        $operatingSystem
            ->setName('Natif')
            ->setImageFilename('Natif')
            ->setHypervisor($this->getReference('natif', Hypervisor::class))
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
