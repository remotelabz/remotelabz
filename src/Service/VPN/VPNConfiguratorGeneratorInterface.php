<?php

namespace App\Service\VPN;

interface VPNConfiguratorGeneratorInterface
{
    /**
     * Generate a string containing the VPN config file content.
     */
    public function generate(string $login, string $password, &$privateKey, &$certificate, int $validity = 365): string;

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
    public function setCAKey(string $CAKey): VPNConfiguratorGeneratorInterface;

    /**
     * Get the TLS key path.
     */
    public function getTLSKey(): string;

    /**
     * Set the TLS key path.
     */
    public function setTLSKey(string $TLSKey): VPNConfiguratorGeneratorInterface;

    /**
     * Get the remote addr to use in OVPN files.
     */
    public function getRemote(): string;

    /**
     * Set the remote addr to use in OVPN files.
     */
    public function setRemote(string $remote): VPNConfiguratorGeneratorInterface;
}
