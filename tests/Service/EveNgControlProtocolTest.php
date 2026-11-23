<?php

namespace App\Tests\Service;

use App\Entity\OperatingSystem;
use App\Repository\ControlProtocolTypeRepository;
use App\Repository\OperatingSystemRepository;
use App\Service\Lab\Unl\EveNgMapper;
use App\Service\Lab\Unl\UnlImporter;
use App\Service\Lab\Unl\UnlParser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The EVE-NG review window lists every RemoteLabz image and lets the user
 * choose one or several control protocols applied to the devices imported from
 * an image.
 */
class EveNgControlProtocolTest extends WebTestCase
{
    public function testAnalysisExposesAllImagesAndControlProtocols(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $controlProtocols = $container->get(ControlProtocolTypeRepository::class);
        $serial = $controlProtocols->findByName('serial');
        $login = $controlProtocols->findByName('login');
        self::assertNotNull($serial);
        self::assertNotNull($login);

        $image = 'control-protocol-analysis-regression.img';
        $tmp = $this->writeUnl($image, 'telnet');

        try {
            $preview = $container->get(UnlImporter::class)->analyze($tmp, 'control-protocol.unl')->toArray();
        } finally {
            @unlink($tmp);
        }

        self::assertNotEmpty($preview['operating_systems'], 'The manual image picker needs the full image catalogue.');
        self::assertNotEmpty($preview['control_protocols'], 'The review window needs the available control protocols.');

        // Every OperatingSystem must be proposed, not only those matching the
        // hypervisor inferred from the EVE-NG node.
        $expectedIds = array_map(
            static fn (OperatingSystem $os): int => (int) $os->getId(),
            $container->get(OperatingSystemRepository::class)->findAll()
        );
        $previewIds = array_map(
            static fn (array $os): int => (int) $os['id'],
            $preview['operating_systems']
        );
        sort($expectedIds);
        sort($previewIds);
        self::assertSame($expectedIds, $previewIds);

        self::assertNotEmpty(array_filter(array_map(
            static fn (array $os): ?string => $os['hypervisor']['name'] ?? null,
            $preview['operating_systems']
        )), 'The image catalogue must carry its hypervisor so the picker can update it.');

        self::assertNotEmpty($preview['images']);
        self::assertContains(
            (int) $serial->getId(),
            $preview['images'][0]['control_protocols'],
            'A telnet console defaults to the serial protocol.'
        );

        $optionNames = array_column($preview['control_protocols'], 'name');
        self::assertContains('login', $optionNames);
        self::assertContains('serial', $optionNames);
    }

    public function testMapperHonoursTheControlProtocolChosenForAnImage(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $controlProtocols = $container->get(ControlProtocolTypeRepository::class);
        $serial = $controlProtocols->findByName('serial');
        $login = $controlProtocols->findByName('login');
        self::assertNotNull($serial);
        self::assertNotNull($login);

        $image = 'control-protocol-override-regression.img';
        $tmp = $this->writeUnl($image, 'telnet');

        try {
            $mapper = $container->get(EveNgMapper::class);
            $parsed = $container->get(UnlParser::class)->parseFile($tmp);

            $automatic = $mapper->map($parsed);
            self::assertSame(
                [['id' => (int) $serial->getId()]],
                $automatic['labJson']['devices'][0]['controlProtocolTypes'],
                'Without an override, the EVE-NG console drives the protocol.'
            );

            $overridden = $mapper->map($parsed, [
                $image => [
                    'os_id' => 0,
                    'os_name' => 'Regression image',
                    'os_image' => $image,
                    'hypervisor' => 'qemu',
                    'control_protocol_ids' => [(int) $login->getId()],
                ],
            ]);

            self::assertSame(
                [['id' => (int) $login->getId()]],
                $overridden['labJson']['devices'][0]['controlProtocolTypes'],
                'The control protocol chosen for the image must be applied to its devices.'
            );
        } finally {
            @unlink($tmp);
        }
    }

    public function testMapperAppliesSeveralControlProtocolsChosenForAnImage(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $controlProtocols = $container->get(ControlProtocolTypeRepository::class);
        $serial = $controlProtocols->findByName('serial');
        $login = $controlProtocols->findByName('login');
        self::assertNotNull($serial);
        self::assertNotNull($login);

        $image = 'control-protocol-multiple-regression.img';
        $tmp = $this->writeUnl($image, 'telnet');

        try {
            $mapper = $container->get(EveNgMapper::class);
            $parsed = $container->get(UnlParser::class)->parseFile($tmp);

            $mapped = $mapper->map($parsed, [
                $image => [
                    'os_id' => 0,
                    'os_name' => 'Multiple protocols',
                    'os_image' => $image,
                    'hypervisor' => 'qemu',
                    'control_protocol_ids' => [
                        (int) $login->getId(),
                        (int) $serial->getId(),
                    ],
                ],
            ]);

            self::assertSame(
                [
                    ['id' => (int) $login->getId()],
                    ['id' => (int) $serial->getId()],
                ],
                $mapped['labJson']['devices'][0]['controlProtocolTypes'],
                'Every protocol selected for the image must be applied to its devices.'
            );
        } finally {
            @unlink($tmp);
        }
    }

    private function writeUnl(string $image, string $console): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'eve-control-protocol-') . '.unl';
        file_put_contents($tmp, <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<lab name="Control protocol regression" version="1">
  <topology>
    <nodes>
      <node id="1" type="qemu" template="linux" image="{$image}" name="Linux1" icon="Desktop_linux.png" left="10" top="20" ram="512" cpu="1" console="{$console}"/>
    </nodes>
    <networks/>
  </topology>
  <objects><textobjects/></objects>
</lab>
XML);

        return $tmp;
    }
}
