<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * @ORM\Entity(repositoryClass="App\Repository\NetworkInterfaceRepository")
 * @Serializer\XmlRoot("network_interface")
 */
class NetworkInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"primary_key"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\XmlAttribute
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\XmlAttribute
     */
    private $name;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\NetworkSettings", cascade={"persist", "remove"})
     * @Serializer\XmlList(entry="network_settings")
     */
    private $settings;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Device", inversedBy="networkInterfaces", cascade={"persist", "remove"})
     * @ORM\JoinColumn(onDelete="CASCADE")
     */
    private $device;

    /**
     * @ORM\Column(type="string", length=17)
     * @Serializer\XmlAttribute
     */
    private $macAddress;

    const TYPE_TAP = 'tap';
    const TYPE_OVS = 'ovs';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSettings(): ?NetworkSettings
    {
        return $this->settings;
    }

    public function setSettings(?NetworkSettings $settings): self
    {
        $this->settings = $settings;

        return $this;
    }

    public function getDevice(): ?Device
    {
        return $this->device;
    }

    public function setDevice(?Device $device): self
    {
        $this->device = $device;

        return $this;
    }

    public function getMacAddress(): ?string
    {
        return $this->macAddress;
    }

    public function setMacAddress(string $macAddress): self
    {
        $this->macAddress = $macAddress;

        return $this;
    }
}
