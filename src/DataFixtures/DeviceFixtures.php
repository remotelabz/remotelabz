<?php

namespace App\DataFixtures;

use App\Entity\Device;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class DeviceFixtures extends Fixture implements DependentFixtureInterface
{
    
    public function load(ObjectManager $manager)
    {

        $device = new Device();

        $device
            ->setName('Linux Alpine')
            ->setBrand('Test')
            ->setModel('Test model')
            ->setLaunchOrder(0)
            ->setVirtuality(1)
            ->setFlavor($this->getReference('flavor-x-small'))
            ->setOperatingSystem($this->getReference('operating-system-Alpine'))
            ->setType('vm')
            ->setHypervisor($this->getReference('qemu'))
            ->setCreatedAt(new \DateTime())
            ->setIsTemplate(true)
            ->setNbCpu(1)
            ->addControlProtocolType($this->getReference('vnc'))
            ->setNetworkInterfaceTemplate("eth")
            ->setIcon("Server_Linux.png")
            
        ;

        $manager->persist($device);
        $this->addReference('device-alpine', $device);

        $device = new Device();
        $device
            ->setName('Migration')
            ->setBrand('Debian')
            ->setModel('Version Bulleye')
            ->setLaunchOrder(0)
            ->setVirtuality(1)
            ->setFlavor($this->getReference('flavor-xx-small'))
            ->setOperatingSystem($this->getReference('MigrationOS'))
            ->setType('container')
            ->setHypervisor($this->getReference('lxc'))
            ->setCreatedAt(new \DateTime())
            ->setIsTemplate(true)
            ->setNbCpu(1)
            ->addControlProtocolType($this->getReference('login'))
            ->setNetworkInterfaceTemplate("eth")
            ->setIcon("Server_Linux.png")
            
        ;
        $manager->persist($device);
        $this->addReference('Migration', $device);

        $device = new Device();
        $device
            ->setName('Ubuntu24LTS-cnt')
            ->setBrand('Ubuntu')
            ->setModel('Version 24 LTS')
            ->setLaunchOrder(0)
            ->setVirtuality(1)
            ->setFlavor($this->getReference('flavor-xx-small'))
            ->setOperatingSystem($this->getReference('Ubuntu24LTSOS'))
            ->setType('container')
            ->setHypervisor($this->getReference('lxc'))
            ->setCreatedAt(new \DateTime())
            ->setIsTemplate(true)
            ->setNbCpu(1)
            ->addControlProtocolType($this->getReference('login'))
            ->setNetworkInterfaceTemplate("eth")
            ->setIcon("Server_Linux.png")
            
        ;
        $manager->persist($device);
        $this->addReference('Ubuntu24LTS-cnt', $device);

        $device = new Device();
        $device
            ->setName('Alpine-stable-cnt')
            ->setBrand('Alpine')
            ->setModel('Version stable')
            ->setLaunchOrder(0)
            ->setVirtuality(1)
            ->setFlavor($this->getReference('flavor-xx-small'))
            ->setOperatingSystem($this->getReference('Alpine-stableOS'))
            ->setType('container')
            ->setHypervisor($this->getReference('lxc'))
            ->setCreatedAt(new \DateTime())
            ->setIsTemplate(true)
            ->setNbCpu(1)
            ->addControlProtocolType($this->getReference('login'))
            ->setNetworkInterfaceTemplate("eth")
            ->setIcon("Server_Linux.png")
            
        ;
        $manager->persist($device);
        $this->addReference('Alpine-stable-cnt', $device);

        $device = new Device();
        $device
            ->setName('Debian-cnt')
            ->setBrand('Debian')
            ->setModel('Stable')
            ->setLaunchOrder(0)
            ->setVirtuality(1)
            ->setFlavor($this->getReference('flavor-xx-small'))
            ->setOperatingSystem($this->getReference('DebianOS'))
            ->setType('container')
            ->setHypervisor($this->getReference('lxc'))
            ->setCreatedAt(new \DateTime())
            ->setIsTemplate(true)
            ->setNbCpu(1)
            ->addControlProtocolType($this->getReference('login'))
            ->setNetworkInterfaceTemplate("eth")
            ->setIcon("Server_Linux.png")

        ;
        $manager->persist($device);
        $this->addReference('Debian-cnt', $device);

        $device = new Device();
        $device
            ->setName('Switch interne')
            ->setBrand('Natif')
            ->setLaunchOrder(0)
            ->setVirtuality(1)
            ->setFlavor($this->getReference('flavor-xx-small'))
            ->setOperatingSystem($this->getReference('Natif'))
            ->setType('switch')
            ->setHypervisor($this->getReference('natif'))
            ->setCreatedAt(new \DateTime())
            ->setIsTemplate(true)
            ->setNbCpu(1)
            ->setIcon("Switch.png")
            ->addControlProtocolType($this->getReference('login'))
            ->setNetworkInterfaceTemplate("eth")
            ->setIcon("Switch.png")
            
        ;
        $manager->persist($device);
        $this->addReference('dev_natif', $device);

        
        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            FlavorFixtures::class,
            OperatingSystemFixtures::class,
            HypervisorFixtures::class,
            ControlProtocolTypeFixtures::class,
        ];
        
    }
}
