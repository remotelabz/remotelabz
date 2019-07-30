<?php

namespace App\Entity;

use App\Entity\Instance;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * @ORM\Entity(repositoryClass="App\Repository\NetworkInterfaceInstanceRepository")
 * @Serializer\XmlRoot("instance")
 */
class NetworkInterfaceInstance extends Instance
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
     * @ORM\ManyToOne(targetEntity="App\Entity\NetworkInterface", inversedBy="instances")
     * @Serializer\Groups({"lab"})
     */
    protected $networkInterface;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="networkInterfaceInstances")
     * @Serializer\Groups({"user"})
     */
    protected $user;

    /**
     * @ORM\Column(type="integer")
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"lab"})
     */
    private $remotePort;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Lab", inversedBy="networkInterfaceInstances")
     * @ORM\JoinColumn(nullable=false)
     */
    private $lab;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNetworkInterface(): ?NetworkInterface
    {
        return $this->networkInterface;
    }

    public function setNetworkInterface(?NetworkInterface $networkInterface): self
    {
        $this->networkInterface = $networkInterface;

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

    public function getRemotePort(): ?int
    {
        return $this->remotePort;
    }

    public function setRemotePort(int $remotePort): self
    {
        $this->remotePort = $remotePort;

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
