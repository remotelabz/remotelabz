<?php

namespace App\Tests\Service;

use App\Service\Lab\Unl\EveNgMapper;
use App\Service\Lab\Unl\UnlParser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Regression: EVE-NG stores the horizontal coordinate in `left` and the vertical
 * one in `top`, whereas RemoteLabz keeps the vertical value in `EditorData.x`
 * and the horizontal one in `EditorData.y` (see DeviceController, which exposes
 * `left => getY()` and `top => getX()`).
 *
 * The import used to copy `left`/`top` straight into `x`/`y`, so every device
 * was transposed on the canvas (the annotations, positioned by their HTML
 * `top`/`left`, stayed correct). The mapper must therefore swap the two, and it
 * must not carry EVE-NG interface names over as connector labels.
 */
class EveNgDevicePlacementTest extends WebTestCase
{
    public function testDeviceCoordinatesAreSwappedAndInterfacesHaveNoLabel(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $tmp = tempnam(sys_get_temp_dir(), 'eve-placement-') . '.unl';
        file_put_contents($tmp, <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<lab name="Placement regression" version="1">
  <topology>
    <nodes>
      <node id="1" type="qemu" template="vios" image="placement-regression.qcow2" name="R1" icon="Router.png" left="120" top="340" ram="512" cpu="1">
        <interface id="0" name="e0" type="ethernet" network_id="1"/>
      </node>
    </nodes>
    <networks>
      <network id="1" type="bridge" name="R1iface_0" left="0" top="0" visibility="0" icon="cloud.png"/>
    </networks>
  </topology>
  <objects><textobjects/></objects>
</lab>
XML);

        try {
            $parsed = $container->get(UnlParser::class)->parseFile($tmp);
            $mapped = $container->get(EveNgMapper::class)->map($parsed);
        } finally {
            @unlink($tmp);
        }

        $devices = $mapped['labJson']['devices'];
        self::assertNotEmpty($devices);

        $device = $devices[0];
        self::assertSame(
            ['x' => 340, 'y' => 120],
            $device['editorData'],
            'EVE-NG left/top must map to RemoteLabz EditorData x=top / y=left.'
        );

        self::assertNotEmpty($device['networkInterfaces']);
        foreach ($device['networkInterfaces'] as $interface) {
            self::assertSame(
                '',
                $interface['connectorLabel'],
                'EVE-NG interface names must not be imported as connector labels.'
            );
        }
    }
}
