<?php

namespace App\DataFixtures;

use App\Entity\Device;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class DeviceFixtures extends Fixture implements DependentFixtureInterface
{
    public const COUNT = 10;

    public function load(ObjectManager $manager)
    {

        $device = new Device();

        $device
            ->setName('Linux Alpine')
            ->setBrand('Test')
            ->setModel('Test model')
            ->setLaunchOrder(0)
            ->setVirtuality(0)
            ->setFlavor($this->getReference('flavor-x-small'))
            ->setOperatingSystem($this->getReference('operating-system-Alpine'))
            ->setType('vm')
            ->setHypervisor($this->getReference('qemu'))
            ->setCreatedAt(new \DateTime())
            ->setIsTemplate(true)
        ;

        $manager->persist($device);
        $this->addReference('device-alpine', $device);

/*        $device = new Device();
        $device
            ->setName('Linux Debian')
            ->setBrand('Test')
            ->setModel('Test model')
            ->setLaunchOrder(0)
            ->setVirtuality(0)
            ->setFlavor($this->getReference('flavor-x-small'))
            ->setOperatingSystem($this->getReference('operating-system-Debian'))
            ->setType('vm')
            ->setHypervisor($this->getReference('qemu'))
            ->setCreatedAt(new \DateTime())
            ->setIsTemplate(true)
        ;
        $manager->persist($device);
        $this->addReference('device-debian', $device);

        $device = new Device();
        $device
            ->setName('Linux Ubuntu 14 with X')
            ->setBrand('Test')
            ->setModel('Test model')
            ->setLaunchOrder(0)
            ->setVirtuality(0)
            ->setFlavor($this->getReference('flavor-x-large'))
            ->setOperatingSystem($this->getReference('operating-system-Ubuntu14X'))
            ->setType('vm')
            ->setHypervisor($this->getReference('qemu'))
            ->setCreatedAt(new \DateTime())
            ->setIsTemplate(true)
        ;
        $manager->persist($device);
        $this->addReference('device-ubuntu14X', $device);
*/
        $device = new Device();
        $device
            ->setName('Migration')
            ->setBrand('Debian')
            ->setModel('Version Bulleye')
            ->setLaunchOrder(0)
            ->setVirtuality(0)
            ->setFlavor($this->getReference('flavor-xx-small'))
            ->setOperatingSystem($this->getReference('MigrationOS'))
            ->setType('container')
            ->setHypervisor($this->getReference('lxc'))
            ->setCreatedAt(new \DateTime())
            ->setIsTemplate(true)
            ->setVnc(true)
        ;
        $manager->persist($device);
        $this->addReference('Migration', $device);

        $device = new Device();
        $device
            ->setName('Ubuntu20LTS-cnt')
            ->setBrand('Ubuntu')
            ->setModel('Version Focal 20 LTS')
            ->setLaunchOrder(0)
            ->setVirtuality(0)
            ->setFlavor($this->getReference('flavor-xx-small'))
            ->setOperatingSystem($this->getReference('Ubuntu20LTSOS'))
            ->setType('container')
            ->setHypervisor($this->getReference('lxc'))
            ->setCreatedAt(new \DateTime())
            ->setIsTemplate(true)
            ->setVnc(true)
        ;
        $manager->persist($device);
        $this->addReference('Ubuntu20LTS-cnt', $device);

        $device = new Device();
        $device
            ->setName('Alpine3.15-cnt')
            ->setBrand('Alpine')
            ->setModel('Version 3.15')
            ->setLaunchOrder(0)
            ->setVirtuality(0)
            ->setFlavor($this->getReference('flavor-xx-small'))
            ->setOperatingSystem($this->getReference('Alpine3.15OS'))
            ->setType('container')
            ->setHypervisor($this->getReference('lxc'))
            ->setCreatedAt(new \DateTime())
            ->setIsTemplate(true)
            ->setVnc(true)
        ;
        $manager->persist($device);
        $this->addReference('Alpine3.15-cnt', $device);

        $device = new Device();
        $device
            ->setName('Debian-cnt')
            ->setBrand('Debian')
            ->setModel('Stable')
            ->setLaunchOrder(0)
            ->setVirtuality(0)
            ->setFlavor($this->getReference('flavor-xx-small'))
            ->setOperatingSystem($this->getReference('DebianOS'))
            ->setType('container')
            ->setHypervisor($this->getReference('lxc'))
            ->setCreatedAt(new \DateTime())
            ->setIsTemplate(true)
            ->setVnc(true)
        ;
        $manager->persist($device);
        $this->addReference('Debian-cnt', $device);

        
        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            FlavorFixtures::class,
            OperatingSystemFixtures::class,
            HypervisorFixtures::class

        ];
    }
}
