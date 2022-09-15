<?php

namespace App\Entity;

use App\Repository\ControlProtocolRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;


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
     * @Serializer\Groups({"api_get_device", "export_lab"})
     */
    private $name;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Device", inversedBy="controlProtocols", cascade={"persist"})
     * @ORM\JoinColumn(nullable=true)
     * @Serializer\Groups({"api_get_control_protocol"})
     */
    private $device;

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

    public function getDevice(): ?Device
    {
        return $this->device;
    }

    public function setDevice(?Device $device): self
    {
        $this->device = $device;

        return $this;
    }

}
