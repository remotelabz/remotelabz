<?php

namespace App\Entity;

use App\Repository\ControlProtocolRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Device;

/**
 * @ORM\Entity(repositoryClass=ControlProtocolRepository::class)
 */
class ControlProtocol
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Serializer\Groups({"api_get_device", "export_lab"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank
     * @Assert\Type(type="string")
     * @Serializer\Groups({"api_get_device", "api_get_device_instance", "api_get_lab_instance", "export_lab", "worker","sandbox"})
     */
    private $name;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Device", inversedBy="controlProtocols", cascade={"persist"})
     */
    private $devices;

    public function __construct()
    {
        $this->devices = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * @return Collection|Device[]
     */
    public function getDevices(): ?Device
    {
        return $this->devices;
    }

    public function addDevice(?Device $device): self
    {
        if (!$this->devices->contains($device)) {
            $this->devices[] = $device;
            $device->addControlProtocol($this);
        }
        return $this;
    }

    public function removeDevice(?Device $device): self
    {
        if ($this->devices->contains($device)) {
            $this->devices->removeElement($device);
            $device->removeControlProtocol($this);
        }

        return $this;
    }
}
