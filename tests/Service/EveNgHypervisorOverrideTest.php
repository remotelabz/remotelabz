<?php

namespace App\Tests\Service;

use App\Repository\HypervisorRepository;
use App\Repository\OperatingSystemRepository;
use App\Service\Lab\Unl\EveNgMapper;
use App\Service\Lab\Unl\UnlImporter;
use App\Service\Lab\Unl\UnlParser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The EVE-NG review window lets the user choose the hypervisor used by an
 * imported image (qemu VM or lxc container). The mapper must therefore honour
 * the per-image decision instead of always copying the EVE-NG node type.
 */
class EveNgHypervisorOverrideTest extends WebTestCase
{
    public function testPerImageHypervisorOverrideTurnsQemuNodeIntoContainer(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $hypervisors = $container->get(HypervisorRepository::class);
        $lxc = $hypervisors->findByName('lxc');
        $qemu = $hypervisors->findByName('qemu');

        if (null === $lxc || null === $qemu) {
            self::markTestSkipped('The qemu/lxc hypervisors are not available in this database.');
        }

        $image = 'hypervisor-override-regression.img';
        $tmp = tempnam(sys_get_temp_dir(), 'eve-hypervisor-') . '.unl';
        file_put_contents($tmp, <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<lab name="Hypervisor override regression" version="1">
  <topology>
    <nodes>
      <node id="1" type="qemu" template="linux" image="{$image}" name="Linux1" icon="Desktop_linux.png" left="10" top="20" ram="512" cpu="1"/>
    </nodes>
    <networks/>
  </topology>
  <objects><textobjects/></objects>
</lab>
XML);

        try {
            $mapper = $container->get(EveNgMapper::class);
            $parsed = $container->get(UnlParser::class)->parseFile($tmp);

            $default = $mapper->map($parsed);
            self::assertSame(
                (int) $qemu->getId(),
                $default['labJson']['devices'][0]['hypervisor']['id'],
                'Without an override, a qemu EVE-NG node stays a qemu VM.'
            );

            $overridden = $mapper->map($parsed, [
                $image => [
                    'os_id' => 0,
                    'os_name' => 'Ubuntu container',
                    'os_image' => 'ubuntu-container-regression',
                    'hypervisor' => 'lxc',
                ],
            ]);

            $device = $overridden['labJson']['devices'][0];
            self::assertSame(
                (int) $lxc->getId(),
                $device['hypervisor']['id'],
                'The hypervisor chosen for the image must be applied to the device.'
            );
            self::assertSame('container', $device['type']);
            self::assertSame(
                (int) $lxc->getId(),
                $device['operatingSystem']['hypervisor']['id'],
                'The operating system JSON must carry the selected hypervisor too.'
            );
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * An exact/auto match on an lxc OperatingSystem must be exposed as such by
     * the analysis, so the review window proposes the container by default.
     */
    public function testAnalysisUsesTheMatchedOperatingSystemHypervisor(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $operatingSystem = $container->get(OperatingSystemRepository::class)->find(7);
        if (null === $operatingSystem || null === $operatingSystem->getHypervisor()) {
            self::markTestSkipped('The Ubuntu24.04SrvLTS (lxc) operating system is not available in this database.');
        }

        $image = $operatingSystem->getImageFilename() ?: $operatingSystem->getName();
        $tmp = tempnam(sys_get_temp_dir(), 'eve-analyze-') . '.unl';
        file_put_contents($tmp, <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<lab name="Hypervisor analysis regression" version="1">
  <topology>
    <nodes>
      <node id="1" type="qemu" template="linux" image="{$image}" name="Linux1" icon="Desktop_linux.png" left="10" top="20" ram="512" cpu="1"/>
    </nodes>
    <networks/>
  </topology>
  <objects><textobjects/></objects>
</lab>
XML);

        try {
            $preview = $container->get(UnlImporter::class)->analyze($tmp, 'hypervisor-analysis.unl')->toArray();
        } finally {
            @unlink($tmp);
        }

        self::assertNotEmpty($preview['hypervisors'], 'The review window must receive the hypervisor choices.');
        self::assertNotEmpty($preview['images']);
        self::assertSame($operatingSystem->getId(), $preview['images'][0]['os_id']);
        self::assertSame(
            $operatingSystem->getHypervisor()->getName(),
            $preview['images'][0]['os_hypervisor']['name']
        );
        self::assertSame(
            $operatingSystem->getHypervisor()->getName(),
            $preview['images'][0]['hypervisor'],
            'The matched OS hypervisor must drive the previewed device.'
        );

        $device = $preview['devices'][0];
        self::assertSame((int) $operatingSystem->getHypervisor()->getId(), $device['hypervisor']);
        self::assertSame('container', $device['type']);
    }
}
