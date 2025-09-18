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
    private ?string $Name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Filename = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Filename_url = null;

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
        return $this->Name;
    }

    public function setName(string $Name): static
    {
        $this->Name = $Name;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->Filename;
    }

    public function setFilename(?string $Filename): static
    {
        $this->Filename = $Filename;

        return $this;
    }

    public function getFilenameUrl(): ?string
    {
        return $this->Filename_url;
    }

    public function setFilenameUrl(?string $Filename_url): static
    {
        $this->Filename_url = $Filename_url;

        return $this;
    }
}
