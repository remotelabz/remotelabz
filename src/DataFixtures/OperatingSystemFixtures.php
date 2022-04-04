<?php

namespace App\DataFixtures;

use Faker\Factory;
use App\Entity\OperatingSystem;
use App\Entity\Hypervisor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class OperatingSystemFixtures extends Fixture implements DependentFixtureInterface
{
    public const COUNT = 5;

    public function load(ObjectManager $manager)
    {
        $faker = Factory::create();

        // foreach (range(1, self::COUNT) as $number) {
        //     $operatingSystem = new OperatingSystem();

        //     $operatingSystem
        //         ->setName($faker->company . ' ' . $faker->numberBetween(10, 30))
        //         ->setImage('/dev/null')
        //     ;

        //     $manager->persist($operatingSystem);
        // }

        $operatingSystem = new OperatingSystem();

        $operatingSystem
            ->setName('Alpine')
            ->setImageUrl('http://194.57.105.124/~fnolot/alpinelab1.img')
            ->setHypervisor($this->getReference('qemu'))
        ;

        $manager->persist($operatingSystem);

        $this->setReference('operating-system-Alpine', $operatingSystem);

        $operatingSystem = new OperatingSystem();
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

        $operatingSystem = new OperatingSystem();
        $operatingSystem
            ->setName('Migration')
            ->setImageFilename('Migration')
            ->setHypervisor($this->getReference('lxc'))
        ;
        $manager->persist($operatingSystem);
        $this->setReference('MigrationOS', $operatingSystem);

        $operatingSystem = new OperatingSystem();
        $operatingSystem
            ->setName('Ubuntu20LTS')
            ->setImageFilename('Ubuntu20LTS')
            ->setHypervisor($this->getReference('lxc'))
        ;
        $manager->persist($operatingSystem);
        $this->setReference('Ubuntu20LTSOS', $operatingSystem);

        $operatingSystem = new OperatingSystem();
        $operatingSystem
            ->setName('Debian')
            ->setImageFilename('Debian')
            ->setHypervisor($this->getReference('lxc'))
        ;
        $manager->persist($operatingSystem);
        $this->setReference('DebianOS', $operatingSystem);


        $operatingSystem = new OperatingSystem();
        $operatingSystem
            ->setName('Alpine3.15')
            ->setImageFilename('Alpine3.15')
            ->setHypervisor($this->getReference('lxc'))
        ;
        $manager->persist($operatingSystem);
        $this->setReference('Alpine3.15OS', $operatingSystem);


        $manager->flush();
    }
    public function getDependencies()
    {
        return [
            HypervisorFixtures::class
        ];
    }
}
