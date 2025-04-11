<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: 'App\Repository\FlavorRepository')]
class Flavor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Serializer\XmlAttribute]
    #[Serializer\Groups(['api_get_flavor', 'api_get_device', 'api_get_lab_template'])]
    private $id;

    #[Assert\NotBlank]
    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\XmlAttribute]
    #[Serializer\Groups(['api_get_flavor', 'export_lab', 'api_get_device', 'api_get_lab_template'])]
    private $name;

    #[Assert\NotBlank]
    #[Assert\GreaterThan(value: 0)]
    #[ORM\Column(type: 'bigint')]
    #[Serializer\XmlAttribute]
    #[Serializer\Groups(['api_get_flavor', 'export_lab', 'worker', 'api_get_lab_template'])]
    private $memory;

    #[Assert\NotBlank]
    #[Assert\GreaterThan(value: 0)]
    #[ORM\Column(type: 'bigint')]
    #[Serializer\XmlAttribute]
    #[Serializer\Groups(['api_get_flavor', 'export_lab', 'worker', 'api_get_lab_template'])]
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
