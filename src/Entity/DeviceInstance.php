<?php

namespace App\Entity;

use App\Entity\Instance;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DeviceInstanceRepository")
 * @Serializer\XmlRoot("instance")
 */
class DeviceInstance extends Instance
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
     * @ORM\ManyToOne(targetEntity="App\Entity\Device", inversedBy="instances")
     * @Serializer\Groups({"lab"})
     */
    protected $device;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="deviceInstances")
     * @Serializer\Groups({"user"})
     */
    protected $user;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Lab", inversedBy="deviceInstances")
     * @ORM\JoinColumn(nullable=false)
     */
    private $lab;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getLab(): ?Lab
    {
        return $this->lab;
    }

    public function setLab(?Lab $lab): self
    {
        $this->lab = $lab;

        return $this;
    }
}
