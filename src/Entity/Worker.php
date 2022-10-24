<?php

namespace App\Entity;

use App\Repository\WorkerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;


/**
 * @ORM\Entity(repositoryClass=WorkerRepository::class)
 */
class Worker
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $Name;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\Ip(version="4")
     * @Serializer\Groups({"api_get_device", "export_lab", "worker"})
     */
    private $IPv4;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\Ip(version="6")
     * @Serializer\Groups({"api_get_device", "export_lab", "worker"})
     */
    private $IPv6;

    /**
     * @ORM\Column(type="boolean")
     */
    private $Available;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->Name;
    }

    public function setName(string $Name): self
    {
        $this->Name = $Name;

        return $this;
    }

    public function getIPv4(): ?string
    {
        return $this->IPv4;
    }

    public function setIPv4(string $IPv4): self
    {
        $this->IPv4 = $IPv4;

        return $this;
    }

    public function getIPv6(): ?string
    {
        return $this->IPv6;
    }

    public function setIPv6(?string $IPv6): self
    {
        $this->IPv6 = $IPv6;

        return $this;
    }

    public function getAvailable(): ?bool
    {
        return $this->Available;
    }

    public function setAvailable(bool $Available): self
    {
        $this->Available = $Available;

        return $this;
    }
}
