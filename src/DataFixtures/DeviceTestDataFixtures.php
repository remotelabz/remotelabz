<?php

namespace App\DataFixtures;

use App\Entity\ControlProtocolType;
use App\Entity\Device;
use App\Entity\Flavor;
use App\Entity\Hypervisor;
use App\Entity\OperatingSystem;
use App\Entity\User;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;

class DeviceTestDataFixtures extends AbstractFixture
{
    private const DEVICES = [
        ['name' => 'CentOS7-cnt', 'brand' => 'CentOS', 'model' => 'Version 7', 'type' => 'container', 'hypervisor' => 'lxc', 'flavor' => 'xx-small-256', 'operatingSystem' => 'CentOS7', 'protocol' => 'login'],
        ['name' => 'Fedora40-cnt', 'brand' => 'Fedora', 'model' => 'Version 40', 'type' => 'container', 'hypervisor' => 'lxc', 'flavor' => 'xx-small-256', 'operatingSystem' => 'Fedora40', 'protocol' => 'login'],
        ['name' => 'ArchLinux-vm', 'brand' => 'Arch', 'model' => 'Rolling', 'type' => 'vm', 'hypervisor' => 'qemu', 'flavor' => 'small-1024', 'operatingSystem' => 'ArchLinux', 'protocol' => 'vnc'],
        ['name' => 'openSUSE-Leap15-cnt', 'brand' => 'openSUSE', 'model' => 'Leap 15', 'type' => 'container', 'hypervisor' => 'lxc', 'flavor' => 'xx-small-256', 'operatingSystem' => 'openSUSE-Leap15', 'protocol' => 'login'],
        ['name' => 'RockyLinux9-vm', 'brand' => 'Rocky', 'model' => 'Version 9', 'type' => 'vm', 'hypervisor' => 'qemu', 'flavor' => 'x-small-512', 'operatingSystem' => 'RockyLinux9', 'protocol' => 'vnc'],
        ['name' => 'Ubuntu22-Desktop-vm', 'brand' => 'Ubuntu', 'model' => 'Version 22.04 Desktop', 'type' => 'vm', 'hypervisor' => 'qemu', 'flavor' => 'large-2048', 'operatingSystem' => 'Ubuntu22.04-Desktop', 'protocol' => 'vnc'],
        ['name' => 'FreeBSD14-vm', 'brand' => 'FreeBSD', 'model' => 'Version 14', 'type' => 'vm', 'hypervisor' => 'qemu', 'flavor' => 'small-1024', 'operatingSystem' => 'FreeBSD14', 'protocol' => 'vnc'],
        ['name' => 'KaliLinux2024-vm', 'brand' => 'Kali', 'model' => 'Version 2024', 'type' => 'vm', 'hypervisor' => 'qemu', 'flavor' => 'x-small-512', 'operatingSystem' => 'KaliLinux2024', 'protocol' => 'vnc'],
        ['name' => 'Debian12-cnt', 'brand' => 'Debian', 'model' => 'Version 12 Bookworm', 'type' => 'container', 'hypervisor' => 'lxc', 'flavor' => 'xx-small-256', 'operatingSystem' => 'Debian12', 'protocol' => 'login'],
        ['name' => 'Alpine3.20-cnt', 'brand' => 'Alpine', 'model' => 'Version 3.20', 'type' => 'container', 'hypervisor' => 'lxc', 'flavor' => 'xx-small-256', 'operatingSystem' => 'Alpine3.20', 'protocol' => 'login'],
    ];

    public function load(ObjectManager $manager): void
    {
        $author = $manager->getRepository(User::class)->findOneBy(['email' => 'root@localhost']);
        if ($author === null) {
            throw new \RuntimeException('User "root@localhost" not found. Load the base fixtures first: php bin/console doctrine:fixtures:load -n');
        }

        $cache = [];
        $repository = $manager->getRepository(Device::class);

        foreach (self::DEVICES as $data) {
            if ($repository->findOneBy(['name' => $data['name']]) !== null) {
                continue;
            }

            $device = new Device();
            $device
                ->setName($data['name'])
                ->setBrand($data['brand'])
                ->setModel($data['model'])
                ->setLaunchOrder(0)
                ->setVirtuality(1)
                ->setFlavor($this->resolve($manager, Flavor::class, $data['flavor'], $cache))
                ->setOperatingSystem($this->resolve($manager, OperatingSystem::class, $data['operatingSystem'], $cache))
                ->setType($data['type'])
                ->setHypervisor($this->resolve($manager, Hypervisor::class, $data['hypervisor'], $cache))
                ->setCreatedAt(new \DateTime())
                ->setIsTemplate(true)
                ->setNbCpu(1)
                ->addControlProtocolType($this->resolve($manager, ControlProtocolType::class, $data['protocol'], $cache))
                ->setNetworkInterfaceTemplate('eth')
                ->setIcon('Server_Linux.png')
                ->setAuthor($author)
            ;

            $manager->persist($device);
        }

        $manager->flush();
    }

    private function resolve(ObjectManager $manager, string $class, string $name, array &$cache)
    {
        $key = $class . ':' . $name;

        if (!array_key_exists($key, $cache)) {
            $cache[$key] = $manager->getRepository($class)->findOneBy(['name' => $name]);

            if ($cache[$key] === null) {
                throw new \RuntimeException(sprintf('%s "%s" not found. Load the base fixtures first: php bin/console doctrine:fixtures:load -n', $class, $name));
            }
        }

        return $cache[$key];
    }
}
