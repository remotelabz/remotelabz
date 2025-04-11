<?php

namespace App\Entity;

use App\Repository\ControlProtocolTypeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: ControlProtocolTypeRepository::class)]
class ControlProtocolType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['api_get_controlProtocolType', 'api_delete_os', 'api_get_device', 'export_lab', 'worker', 'sandbox', 'api_get_device_instance', 'api_get_lab_instance', 'api_get_lab_template'])]
    private $id;

    #[Assert\NotBlank]
    #[Assert\Type(type: 'string')]
    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['api_get_controlProtocolType', 'api_get_device', 'api_get_device_instance', 'api_get_lab_instance', 'export_lab', 'worker', 'sandbox'])]
    private $name;

    #[ORM\OneToMany(targetEntity: 'App\Entity\ControlProtocolTypeInstance', mappedBy: 'controlProtocolType', cascade: ['persist'])]
    #[Serializer\Groups(['api_get_lab_instance', 'api_get_device_instance', 'worker', 'sandbox'])]
    private $controlProtocolTypeInstances;


    #[ORM\ManyToMany(targetEntity: 'App\Entity\Device', inversedBy: 'controlProtocolTypes', cascade: ['persist'])]
    private $devices;

    public function __construct()
    {
        $this->devices = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    
    #[Serializer\Groups(["api_get_device"])]
    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function addDevice(?Device $device): self
    {
        if (!$this->devices->contains($device)) {
            $this->devices[] = $device;
            $device->addControlProtocolType($this);
        }
        return $this;
    }

    public function removeDevice(?Device $device): self
    {
        if ($this->devices->contains($device)) {
            $this->devices->removeElement($device);
            $device->removeControlProtocolType($this);
        }

        return $this;
    }
}
