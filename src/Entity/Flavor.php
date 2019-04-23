<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FlavorRepository")
 */
class Flavor
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
    private $name;

    /**
     * @ORM\Column(type="bigint")
     * @Serializer\XmlAttribute
     */
    private $memory;

    /**
     * @ORM\Column(type="bigint")
     * @Serializer\XmlAttribute
     */
    private $disk;

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

    public function getMemory(): ?int
    {
        return $this->memory;
    }

    public function setMemory(int $memory): self
    {
        $this->memory = $memory;

        return $this;
    }

    public function getDisk(): ?int
    {
        return $this->disk;
    }

    public function setDisk(int $disk): self
    {
        $this->disk = $disk;

        return $this;
    }
}
