<?php

namespace App\Tests\Service\VPN;

use App\Service\VPN\OpenVPNConfigurationGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;

class OpenVPNConfigurationGeneratorTest extends WebTestCase
{
    public function testOpenVPNConfigurationGenerator()
    {
        self::bootKernel();

        /** @var OpenVPNConfigurationGenerator */
        $generator = self::$container->get('openvpn_configuration_generator');

        $generator->generate($privateKey, $x509);
        openssl_x509_export($x509, $x509Content);

        $this->assertIsString($x509Content);
        $this->assertStringContainsString('-----BEGIN CERTIFICATE-----', $x509Content);

        $certsDir = $generator->getExportPath();

        openssl_pkey_export_to_file($privateKey, $certsDir . '/test.key');
        openssl_x509_export_to_file($x509, $certsDir . '/test.crt');

        $this->assertFileExists($certsDir . '/test.key');
        $this->assertFileExists($certsDir . '/test.crt');

        $filesystem = new Filesystem();
        $filesystem->remove([
            $certsDir . '/test.key',
            $certsDir . '/test.crt'
        ]);

        $this->assertFileNotExists($certsDir . '/test.key');
        $this->assertFileNotExists($certsDir . '/test.crt');
    }
}