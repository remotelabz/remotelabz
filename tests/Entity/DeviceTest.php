<?php

namespace App\Tests\Entity;

use App\Entity\Device;
use App\Entity\Hypervisor;
use App\Entity\OperatingSystem;
use PHPUnit\Framework\TestCase;

class DeviceTest extends TestCase
{
    public function testInternetSwitchIsNative(): void
    {
        $device = (new Device())
            ->setType('switch')
            ->setHypervisor((new Hypervisor())->setName('natif'))
            ->setOperatingSystem((new OperatingSystem())->setName('Natif'));

        $this->assertTrue($device->isNative());
    }

    /**
     * Imported EVE-NG switches (viosl2, ...) also use the "switch" type but run
     * on qemu: they are real VMs and must stay controllable.
     */
    public function testImportedQemuSwitchIsNotNative(): void
    {
        $device = (new Device())
            ->setType('switch')
            ->setHypervisor((new Hypervisor())->setName('qemu'))
            ->setOperatingSystem((new OperatingSystem())->setName('Cisco Switch L2'));

        $this->assertFalse($device->isNative());
    }

    public function testDeviceWithoutHypervisorOrOperatingSystemIsNotNative(): void
    {
        $this->assertFalse((new Device())->isNative());
    }
}
