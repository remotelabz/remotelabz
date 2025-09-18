<?php

namespace App\Entity;

use App\Repository\IsoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IsoRepository::class)]
class Iso
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filename = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filename_url = null;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Arch')]
    #[ORM\JoinColumn(nullable: true)]
    #[Serializer\Groups(['api_get_operating_system', 'api_get_lab_template', 'api_get_device', 'export_lab', 'worker', 'sandbox'])]
    private $arch = Null;
    
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getFilenameUrl(): ?string
    {
        return $this->filename_url;
    }

    public function setFilenameUrl(?string $filename_url): static
    {
        $this->filename_url = $filename_url;

        return $this;
    }

    public function getArch(): ?Arch
{
    return $this->arch;
}

public function setArch(?Arch $arch): static
{
    $this->arch = $arch;

    return $this;
}
}
