<?php

namespace App\Tests\Service\VPN;

use App\Service\VPN\OpenVPNConfigurationGenerator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;

class OpenVPNConfigurationGeneratorTest extends WebTestCase
{
    public function testOpenVPNConfigurationGenerator()
    {
        self::bootKernel();

        /** @var OpenVPNConfigurationGenerator */
        $generator = self::getContainer()->get('openvpn_configuration_generator');

        // CA jetable : le test ne doit dependre ni de l'installation OpenVPN de
        // l'hote ni des permissions de sa cle CA (600 www-data sur un serveur
        // installe). On genere une CA temporaire et on l'injecte via les setters.
        $tmpDir = sys_get_temp_dir() . '/remotelabz-openvpn-' . uniqid();
        $filesystem = new Filesystem();
        $filesystem->mkdir($tmpDir);

        try {
            $caKey = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);
            $this->assertNotFalse($caKey, 'CA private key generation failed');

            $caCsr = openssl_csr_new(['commonName' => 'RemoteLabz Test CA'], $caKey, ['digest_alg' => 'sha256']);
            $this->assertNotFalse($caCsr, 'CA CSR generation failed');

            $caCert = openssl_csr_sign($caCsr, null, $caKey, 365, ['digest_alg' => 'sha256']);
            $this->assertNotFalse($caCert, 'CA certificate signing failed');

            $this->assertTrue(openssl_pkey_export_to_file($caKey, $tmpDir . '/ca.key'));
            $this->assertTrue(openssl_x509_export_to_file($caCert, $tmpDir . '/ca.crt'));

            $generator
                ->setCACert($tmpDir . '/ca.crt')
                ->setCAKey($tmpDir . '/ca.key')
                ->setCAKeyPassphrase('')
                ->setExportPath($tmpDir);

            $privateKey = null;
            $certificate = null;
            $generator->generate($privateKey, $certificate);

            openssl_x509_export($certificate, $x509Content);

            $this->assertIsString($x509Content);
            $this->assertStringContainsString('-----BEGIN CERTIFICATE-----', $x509Content);

            $certsDir = $generator->getExportPath();

            openssl_pkey_export_to_file($privateKey, $certsDir . '/test.key');
            openssl_x509_export_to_file($certificate, $certsDir . '/test.crt');

            $this->assertFileExists($certsDir . '/test.key');
            $this->assertFileExists($certsDir . '/test.crt');

            $filesystem->remove([
                $certsDir . '/test.key',
                $certsDir . '/test.crt'
            ]);

            $this->assertFileDoesNotExist($certsDir . '/test.key');
            $this->assertFileDoesNotExist($certsDir . '/test.crt');
        } finally {
            $filesystem->remove($tmpDir);
        }
    }
}
