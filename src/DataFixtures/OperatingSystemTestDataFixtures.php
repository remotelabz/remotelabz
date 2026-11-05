<?php

namespace App\DataFixtures;

use App\Entity\Arch;
use App\Entity\Hypervisor;
use App\Entity\OperatingSystem;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;

class OperatingSystemTestDataFixtures extends AbstractFixture
{
    private const OPERATING_SYSTEMS = [
        ['name' => 'CentOS7', 'imageFilename' => 'CentOS7', 'hypervisor' => 'qemu'],
        ['name' => 'Fedora40', 'imageFilename' => 'Fedora40', 'hypervisor' => 'lxc'],
        ['name' => 'ArchLinux', 'imageFilename' => 'ArchLinux', 'hypervisor' => 'qemu'],
        ['name' => 'openSUSE-Leap15', 'imageFilename' => 'OpenSUSE-Leap15', 'hypervisor' => 'lxc'],
        ['name' => 'RockyLinux9', 'imageFilename' => 'RockyLinux9', 'hypervisor' => 'qemu'],
        ['name' => 'Ubuntu22.04-Desktop', 'imageFilename' => 'Ubuntu22Desktop', 'hypervisor' => 'qemu'],
        ['name' => 'FreeBSD14', 'imageFilename' => 'FreeBSD14', 'hypervisor' => 'qemu'],
        ['name' => 'KaliLinux2024', 'imageFilename' => 'KaliLinux2024', 'hypervisor' => 'qemu'],
        ['name' => 'Debian12', 'imageFilename' => 'Debian12', 'hypervisor' => 'lxc'],
        ['name' => 'Alpine3.20', 'imageFilename' => 'Alpine3.20', 'hypervisor' => 'lxc'],
    ];

    public function load(ObjectManager $manager): void
    {
        $arch = $manager->getRepository(Arch::class)->findOneBy(['name' => 'x86_64']);
        if ($arch === null) {
            throw new \RuntimeException('Architecture "x86_64" not found. Load the base fixtures first: php bin/console doctrine:fixtures:load -n');
        }

        $hypervisors = [];
        $repository = $manager->getRepository(OperatingSystem::class);

        foreach (self::OPERATING_SYSTEMS as $data) {
            if ($repository->findOneBy(['name' => $data['name']]) !== null) {
                continue;
            }

            if (!array_key_exists($data['hypervisor'], $hypervisors)) {
                $hypervisors[$data['hypervisor']] = $manager->getRepository(Hypervisor::class)->findOneBy(['name' => $data['hypervisor']]);
            }

            if ($hypervisors[$data['hypervisor']] === null) {
                throw new \RuntimeException(sprintf('Hypervisor "%s" not found. Load the base fixtures first: php bin/console doctrine:fixtures:load -n', $data['hypervisor']));
            }

            $operatingSystem = new OperatingSystem();
            $operatingSystem->setName($data['name']);
            $operatingSystem
                ->setImageFilename($data['imageFilename'])
                ->setHypervisor($hypervisors[$data['hypervisor']])
                ->setArch($arch)
            ;

            $manager->persist($operatingSystem);
        }

        $manager->flush();
    }
}
