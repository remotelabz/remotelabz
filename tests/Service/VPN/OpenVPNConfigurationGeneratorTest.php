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

        $x509 = $generator->generate('test', self::$container->getParameter('app.ssl.ca.key.passphrase'), $privateKey, $certificate, self::$container->getParameter('app.ssl.certs.validity'));

        // echo $x509;

        $this->assertIsString($x509);
        $this->assertStringContainsString('-----BEGIN CERTIFICATE-----', $x509);

        $certsDir = self::$container->getParameter('app.ssl.certs.dir');

        openssl_pkey_export_to_file($privateKey, $certsDir . '/test.key');
        openssl_x509_export_to_file($certificate, $certsDir . '/test.crt');

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