<?php

namespace App\Entity;

use App\Instance\InstanciableInterface;
use App\Utils\Uuid;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\NetworkInterfaceRepository")
 */
class NetworkInterface implements InstanciableInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Serializer\Groups({"api_get_network_interface", "api_get_device_instance","api_get_device"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, options={"default": "tap"})
     * @Serializer\Groups({"api_get_network_interface", "export_lab", "worker"})
     */
    private $type = 'tap';

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\Groups({"api_get_network_interface", "export_lab", "worker"})
     */
    private $name;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\NetworkSettings", cascade={"persist", "remove"})
     */
    private $settings;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Device", inversedBy="networkInterfaces", cascade={"persist"})
     * @ORM\JoinColumn(nullable=true)
     * @Serializer\Groups({"api_get_network_interface"})
     */
    private $device;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\Groups({"api_get_network_interface", "worker"})
     */
    private $uuid;

    /**
     * @ORM\Column(type="integer", options={"default": 0})
     * @Serializer\Groups({"api_get_network_interface", "export_lab", "worker"})
     */
    private $vlan;

    /**
     * @ORM\Column(type="boolean", options={"default": 0})
     * @Serializer\Groups({"api_get_network_interface"})
     * @Assert\NotNull
     * @Assert\Type(type="boolean")
     */
    private $isTemplate;

    const TYPE_TAP = 'tap';
    const TYPE_OVS = 'ovs';

    public function __construct()
    {
        $this->uuid = (string) new Uuid();
        $this->vlan = 0;
    }

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

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({})
     */
    public function getAccessType(): ?string
    {
        return $this->settings->getProtocol();
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

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(?string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getVlan(): ?int
    {
        return $this->vlan;
    }

    public function setVlan(int $vlan): self
    {
        $this->vlan = $vlan;

        return $this;
    }

    public function getIsTemplate(): ?bool
    {
        return $this->isTemplate;
    }

    public function setIsTemplate(bool $isTemplate): self
    {
        $this->isTemplate = $isTemplate;

        return $this;
    }
}
