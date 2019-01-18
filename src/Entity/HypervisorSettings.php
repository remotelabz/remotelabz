<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\HypervisorSettingsRepository")
 */
class HypervisorSettings
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $VNCPortMin;

    /**
     * @ORM\Column(type="integer")
     */
    private $VNCPortMax;

    /**
     * @ORM\Column(type="integer")
     */
    private $WSPortMin;

    /**
     * @ORM\Column(type="integer")
     */
    private $WSPortMax;

    /**
     * @ORM\Column(type="integer")
     */
    private $consolePortMin;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $ip;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $ipv6;

    /**
     * Index minimal à utiliser rapport au système.
     * Exemple : nous avons déjà 13 interfaces de créer donc la prochaine interface_exists
     * libre sera la tap14 par exemple.
     * @ORM\Column(type="integer")
     */
    private $interfaceMinId;

    /**
     * Index actuel des interfaces de contrôle par rapport au système.
     * Exemple : nous avons déjà 13 interfaces de créer donc la prochaine interface_exists
     * libre sera la tap14 par exemple.
     * @ORM\Column(type="integer")
     */
    private $controlInterfaceId;

    /**
     * Index actuel des interfaces classiques par rapport au système.
     * Exemple : nous avons déjà 13 interfaces de créer donc la prochaine interface_exists
     * libre sera la tap14 par exemple.
     * @ORM\Column(type="integer")
     */
    private $interfaceId;

    /**
     * Index of the network used.
     * @ORM\Column(type="integer")
     */
    private $networkId;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVNCPortMin(): ?int
    {
        return $this->VNCPortMin;
    }

    public function setVNCPortMin(int $VNCPortMin): self
    {
        $this->VNCPortMin = $VNCPortMin;

        return $this;
    }

    public function getVNCPortMax(): ?int
    {
        return $this->VNCPortMax;
    }

    public function setVNCPortMax(int $VNCPortMax): self
    {
        $this->VNCPortMax = $VNCPortMax;

        return $this;
    }

    public function getWSPortMin(): ?int
    {
        return $this->WSPortMin;
    }

    public function setWSPortMin(int $WSPortMin): self
    {
        $this->WSPortMin = $WSPortMin;

        return $this;
    }

    public function getWSPortMax(): ?int
    {
        return $this->WSPortMax;
    }

    public function setWSPortMax(int $WSPortMax): self
    {
        $this->WSPortMax = $WSPortMax;

        return $this;
    }

    public function getConsolePortMin(): ?int
    {
        return $this->consolePortMin;
    }

    public function setConsolePortMin(int $consolePortMin): self
    {
        $this->consolePortMin = $consolePortMin;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getIpv6(): ?string
    {
        return $this->ipv6;
    }

    public function setIpv6(string $ipv6): self
    {
        $this->ipv6 = $ipv6;

        return $this;
    }

    public function getInterfaceMinId(): ?int
    {
        return $this->interfaceMinId;
    }

    public function setInterfaceMinId(int $interfaceMinId): self
    {
        $this->interfaceMinId = $interfaceMinId;

        return $this;
    }

    public function getControlInterfaceId(): ?int
    {
        return $this->controlInterfaceId;
    }

    public function setControlInterfaceId(int $controlInterfaceId): self
    {
        $this->controlInterfaceId = $controlInterfaceId;

        return $this;
    }

    public function getInterfaceId(): ?int
    {
        return $this->interfaceId;
    }

    public function setInterfaceId(int $interfaceId): self
    {
        $this->interfaceId = $interfaceId;

        return $this;
    }

    public function getNetworkId(): ?int
    {
        return $this->networkId;
    }

    public function setNetworkId(int $networkId): self
    {
        $this->networkId = $networkId;

        return $this;
    }
}
