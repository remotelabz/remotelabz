<?php

namespace App\Tests\Service;

use App\Service\Lab\Unl\EveNgMapper;
use App\Service\Lab\Unl\UnlParser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * RemoteLabz isolates links with the `NetworkInterface.vlan` value: the worker
 * tags the OVS port with it (`tag=<vlan>`), so two interfaces sharing a VLAN
 * share the same L2 segment, and a `vlan` of 0 means "untagged" (link to a
 * native switch).
 *
 * EVE-NG stores links as <network type="bridge"> segments. The import used to
 * set `vlan = 0` on every interface, which put the whole lab in a single VLAN.
 * Each bridge must now get its own VLAN, shared by both ends of the link.
 */
class EveNgNetworkVlanTest extends WebTestCase
{
    public function testEachBridgeGetsItsOwnVlanSharedByBothEnds(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $tmp = tempnam(sys_get_temp_dir(), 'eve-vlan-') . '.unl';
        file_put_contents($tmp, <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<lab name="VLAN regression" version="1">
  <topology>
    <nodes>
      <node id="1" type="qemu" template="linux" image="vlan-regression.img" name="R1" icon="Router.png" left="10" top="20" ram="512" cpu="1">
        <interface id="0" name="e0" type="ethernet" network_id="1"/>
        <interface id="1" name="e1" type="ethernet" network_id="4"/>
      </node>
      <node id="2" type="qemu" template="linux" image="vlan-regression.img" name="R2" icon="Router.png" left="30" top="20" ram="512" cpu="1">
        <interface id="0" name="e0" type="ethernet" network_id="1"/>
        <interface id="1" name="e1" type="ethernet" network_id="2"/>
        <interface id="2" name="e2" type="ethernet" network_id="4"/>
      </node>
      <node id="3" type="qemu" template="linux" image="vlan-regression.img" name="R3" icon="Router.png" left="50" top="20" ram="512" cpu="1">
        <interface id="0" name="e0" type="ethernet" network_id="2"/>
        <interface id="1" name="e1" type="ethernet" network_id="3"/>
        <interface id="2" name="e2" type="ethernet" network_id="4"/>
      </node>
    </nodes>
    <networks>
      <network id="1" type="bridge" name="R1-R2" left="0" top="0" visibility="0" icon="cloud.png"/>
      <network id="2" type="bridge" name="R2-R3" left="0" top="0" visibility="0" icon="cloud.png"/>
      <network id="3" type="nat0" name="Net" left="0" top="0" visibility="0" icon="cloud.png"/>
      <network id="4" type="bridge" name="shared" left="0" top="0" visibility="0" icon="cloud.png"/>
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

        $devices = [];
        foreach ($mapped['labJson']['devices'] as $device) {
            $devices[$device['name']] = $device;
        }

        $interface = static function (string $deviceName, string $interfaceName) use ($devices): array {
            foreach ($devices[$deviceName]['networkInterfaces'] as $networkInterface) {
                if ($interfaceName === $networkInterface['name']) {
                    return $networkInterface;
                }
            }

            self::fail(sprintf('%s has no interface %s.', $deviceName, $interfaceName));
        };

        // Link R1 <-> R2: both ends share the VLAN and the connection.
        $r1e0 = $interface('R1', 'eth0');
        $r2e0 = $interface('R2', 'eth0');
        self::assertSame($r1e0['connection'], $r2e0['connection']);
        self::assertSame($r1e0['vlan'], $r2e0['vlan'], 'Both ends of a link must share the same VLAN.');
        self::assertGreaterThan(0, $r1e0['vlan'], 'A direct link must not stay in the untagged VLAN 0.');

        // Link R2 <-> R3: its own VLAN, different from the first link.
        $r2e1 = $interface('R2', 'eth1');
        $r3e0 = $interface('R3', 'eth0');
        self::assertSame($r2e1['connection'], $r3e0['connection']);
        self::assertSame($r2e1['vlan'], $r3e0['vlan']);
        self::assertGreaterThan(0, $r2e1['vlan']);
        self::assertNotSame(
            $r1e0['vlan'],
            $r2e1['vlan'],
            'Two distinct links must be isolated in different VLANs.'
        );

        // A bridge shared by three devices stays one L2 segment: one VLAN.
        $r1e1 = $interface('R1', 'eth1');
        $r2e2 = $interface('R2', 'eth2');
        $r3e2 = $interface('R3', 'eth2');
        self::assertSame($r1e1['vlan'], $r2e2['vlan']);
        self::assertSame($r2e2['vlan'], $r3e2['vlan']);
        self::assertGreaterThan(0, $r1e1['vlan']);
        self::assertNotContains($r1e1['vlan'], [$r1e0['vlan'], $r2e1['vlan']]);

        // A system network (nat0) is materialised by a native switch: its links
        // stay untagged (vlan 0), as in the editor.
        $natNodeInterface = $interface('R3', 'eth1');
        self::assertSame(0, $natNodeInterface['vlan']);

        $switch = $devices['Net'] ?? null;
        self::assertNotNull($switch, 'A nat0 network must be materialised by a native switch device.');
        self::assertSame('switch', $switch['type']);
        self::assertSame(0, $switch['networkInterfaces'][0]['vlan']);
        self::assertSame(
            $natNodeInterface['connection'],
            $switch['networkInterfaces'][0]['connection'],
            'The node and the switch must be linked by the same connection.'
        );
    }
}
