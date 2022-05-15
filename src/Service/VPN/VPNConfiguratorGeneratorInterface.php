<?php

namespace App\Service\VPN;

interface VPNConfiguratorGeneratorInterface
{
    /**
     * Generate a new x509 certificate.
     */
    public function generate(&$privateKey, &$certificate);

    /**
     * Generates a new OpenVPN configuration file.
     *
     * @return string the OpenVPN configuration file
     */
    public function generateConfig(string $privateKey, string $certificate): string;

    public function getCountry(): string;

    public function setCountry(string $country): self;

    public function getProvince(): string;

    public function setProvince(string $province): self;

    public function getCity(): string;

    public function setCity(string $city): self;

    public function getOrganization(): string;

    public function setOrganization(string $organization): self;

    public function getEmail(): string;

    public function setEmail(string $email): self;

    /**
     * Get the CA certificate path.
     */
    public function getCACert(): string;

    /**
     * Set the CA certificate path.
     */
    public function setCACert(string $path): self;

    public function getExportPath(): string;

    public function setExportPath(string $path): self;

    public function getCommonName(): string;

    public function setCommonName(string $commonName): self;

    /**
     * Get the CA key path.
     */
    public function getCAKey(): string;

    /**
     * Set the CA key path.
     */
    public function setCAKey(string $CAKey): self;

    /**
     * Get the CA key passphrase.
     */
    public function getCAKeyPassphrase(): string;

    /**
     * Set the CA key passphrase.
     */
    public function setCAKeyPassphrase(string $CAKeyPassphrase): self;

    /**
     * Get the TLS key path.
     */
    public function getTLSKey(): string;

    /**
     * Set the TLS key path.
     */
    public function setTLSKey(string $TLSKey): self;

    /**
     * Get the remote addr to use in OVPN files.
     */
    public function getVpnAddress(): string;

    /**
     * Set the remote addr to use in OVPN files.
     */
    public function setVpnAddress(string $remote): self;

    /**
     * Get the certificate validity (in days).
     */
    public function getValidity(): int;

    /**
     * Set the certificate validity (in days).
     */
    public function setValidity(int $validity): self;
}
