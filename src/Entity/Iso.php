<?php

namespace App\Entity;

use App\Repository\IsoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;
use App\Entity\Interface\DirectoryAwareInterface;
use App\Entity\Trait\DirectoryAwareTrait;
/**
 * Represents an iso image disk 
 *
 * @author Florent Nolot
 */

#[ORM\Entity(repositoryClass: IsoRepository::class)]
class Iso implements DirectoryAwareInterface
{
    use DirectoryAwareTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Serializer\Groups(['worker','sandbox','api_get_lab_instance'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['worker','sandbox','api_get_lab_instance'])]
    private ?string $name;

    #[ORM\Column(length: 255, nullable: true)]
    //#[Serializer\Groups(['sandbox','api_get_lab_instance'])]
    #[Serializer\Groups(['worker'])]
    private ?string $filename = null;

    #[ORM\Column(length: 255, nullable: true)]
    //#[Serializer\Groups(['sandbox','api_get_lab_instance'])]
    #[Serializer\Groups(['worker'])]
    private ?string $filename_url = null;

    #[Assert\Type(type: 'string')]
    #[ORM\Column(type: 'text', nullable: true)]
    private $description = null;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Arch')]
    #[ORM\JoinColumn(nullable: true)]
    #[Serializer\Groups(['sandbox','worker'])]
    private $arch = null;
    
    /**
     * @var Collection<int, Device>
     */
    #[ORM\ManyToMany(targetEntity: Device::class, mappedBy: 'isos')]
    private Collection $devices;

    public function __construct()
    {
        $this->devices = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;

    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): void
    {
        $this->filename = $filename;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return Collection<int, Device>
     */
    public function getDevices(): Collection
    {
        return $this->devices;
    }

    public function addDevice(Device $device): self
    {
        if (!$this->devices->contains($device)) {
            $this->devices->add($device);
            $device->addIso($this);
        }
        return $this;
    }

    public function removeDevice(Device $device): self
    {
        if ($this->devices->contains($device)) {
            $this->devices->removeElement($device);
            $device->removeIso($this);
        }
        return $this;
    }
}
