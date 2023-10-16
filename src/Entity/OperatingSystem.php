<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents a disk image with metadata
 * 
 * @ORM\Entity(repositoryClass="App\Repository\OperatingSystemRepository")
 * @Serializer\XmlRoot("operating_system")
 * 
 * @author Julien Hubert <julien.hubert@outlook.com>
 */
class OperatingSystem
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"api_get_operating_system", "api_get_lab_template", "api_get_device","api_delete_os"})
     * @var int
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"api_get_operating_system", "api_get_lab_template", "api_get_device", "export_lab", "worker","sandbox"})
     * @var string
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Serializer\Exclude
     * @Assert\Url
     * @var string
     */
    private $imageUrl;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Serializer\Groups({"api_delete_os"})
     * @var string
     */
    private $imageFilename;

    /**
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"api_get_operating_system", "api_get_lab_template", "export_lab", "api_get_lab_instance", "worker","sandbox"})
     * @var string
     */
    private $image;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Hypervisor")
     * @Serializer\Groups({"api_get_device", "api_delete_os","export_lab", "api_get_lab_instance", "worker"})
     * @Assert\NotNull
     * @Assert\Valid
     */
    private $hypervisor;

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

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function getImageFilename(): ?string
    {
        return $this->imageFilename;
    }

    public function setImageFilename(?string $imageFilename): self
    {
        $this->imageFilename = $imageFilename;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getHypervisor(): ?Hypervisor
    {
        return $this->hypervisor;
    }

    public function setHypervisor(?Hypervisor $hypervisor): self
    {
        $this->hypervisor = $hypervisor;

        return $this;
    }

    
}
