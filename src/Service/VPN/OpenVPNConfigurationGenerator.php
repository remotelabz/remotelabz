<?php

namespace App\Service\VPN;

use Exception;

class OpenVPNConfigurationGenerator extends AbstractVPNConfigurationGenerator implements VPNConfiguratorGeneratorInterface
{
    /**
     * Generates a new OpenSSL certificate.
     *
     * @param resource $privateKey  The private key generated
     * @param resource $certificate The x509 certificate generated
     *
     * @return string the OpenVPN configuration file
     */
    public function generate(string $login, string $CAKeyPassphrase, &$privateKey, &$certificate, int $validity = 365): string
    {
        $dn = [
            'countryName' => $this->getCountry(),
            'stateOrProvinceName' => $this->getProvince(),
            'localityName' => $this->getCity(),
            'organizationName' => $this->getOrganization(),
            'commonName' => $this->getCommonName(),
            'emailAddress' => $this->getEmail(),
        ];

        $privateKey = openssl_pkey_new([
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if (!$privateKey) {
            throw new Exception('Failed to generate OpenSSL private key.');
        }

        $csr = openssl_csr_new(
            $dn,
            $privateKey,
            [
                'digest_alg' => 'sha256',
            ]
        );

        if (!$csr) {
            throw new Exception('Failed to generate OpenSSL Certificate Signing Request.');
        }

        $certificate = openssl_csr_sign(
            $csr,
            file_get_contents($this->getCACert()),
            [file_get_contents($this->getCAKey()), $CAKeyPassphrase],
            $validity,
            [
                'digest_alg' => 'sha256',
            ]
        );

        openssl_x509_export($certificate, $certificateOut, true);
        openssl_pkey_export($privateKey, $privateKeyOut);

        if (!$certificate) {
            throw new Exception('Failed to sign OpenSSL X509 Certificate.');
        }

        $hostname = $this->getRemote();
        $CACert = file_get_contents($this->getCACert());
        $TLSKeyContent = file_get_contents($this->getTLSKey());
        $config = <<<END
client
dev tun
dev-type tun
tun-mtu 1500
cipher AES-256-GCM
remote $hostname
resolv-retry infinite
key-direction 1
nobind
persist-key
persist-tun
verb 1
keepalive 10 120
port 1194
proto udp
comp-lzo
<ca>
$CACert
</ca>
<cert>
$certificateOut
</cert>
<key>
$privateKeyOut
</key>
<tls-auth>
$TLSKeyContent
</tls-auth>
END;

        return $config;
    }
}
