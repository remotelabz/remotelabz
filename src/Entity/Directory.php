<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;

/**
 * Represents a generic directory that can contain various entities
 * Supports hierarchical tree structure with parent/children relationships
 *
 * @author Your Name
 */
#[ORM\Entity(repositoryClass: 'App\Repository\DirectoryRepository')]
#[ORM\Table(name: 'directory')]
#[ORM\Index(name: 'idx_directory_parent', columns: ['parent_id'])]
#[ORM\Index(name: 'idx_directory_path', columns: ['path'], options: ['lengths' => [191]])]
#[ORM\HasLifecycleCallbacks]
class Directory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['api_directory'])]
    private ?int $id = null;

    /**
     * Directory name (not full path, just the name)
     */
    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Serializer\Groups(['api_directory'])]
    private ?string $name = null;

    /**
     * Full path from root (e.g., "/parent/child/current")
     * Calculated automatically for performance optimization
     */
    #[ORM\Column(type: 'string', length: 1000, nullable: true)]
    #[Serializer\Groups(['api_directory'])]
    private ?string $path = null;

    /**
     * Optional description
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Serializer\Groups(['api_directory'])]
    private ?string $description = null;

    /**
     * Parent directory (null if root)
     */
    #[ORM\ManyToOne(targetEntity: Directory::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Serializer\Groups(['api_directory'])]
    private ?Directory $parent = null;

    /**
     * Child directories
     * @var Collection<int, Directory>
     */
    #[ORM\OneToMany(targetEntity: Directory::class, mappedBy: 'parent', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $children;

    /**
     * Devices in this directory
     * @var Collection<int, Device>
     */
    #[ORM\OneToMany(targetEntity: Device::class, mappedBy: 'directory')]
    private Collection $devices;

    /**
     * ISOs in this directory
     * @var Collection<int, Iso>
     */
    #[ORM\OneToMany(targetEntity: Iso::class, mappedBy: 'directory')]
    private Collection $isos;

    /**
     * Operating Systems in this directory
     * @var Collection<int, OperatingSystem>
     */
    #[ORM\OneToMany(targetEntity: OperatingSystem::class, mappedBy: 'directory')]
    private Collection $operatingSystems;

    /**
     * Tree depth level (0 for root)
     */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Serializer\Groups(['api_directory'])]
    private int $level = 0;

    #[ORM\Column(type: 'datetime')]
    #[Serializer\Groups(['api_directory'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    #[Serializer\Groups(['api_directory'])]
    private \DateTimeInterface $updatedAt;

    /**
     * Soft delete support (optional)
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->devices = new ArrayCollection();
        $this->isos = new ArrayCollection();
        $this->operatingSystems = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    // ========== Lifecycle Callbacks ==========

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTime();
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updatePathAndLevel(): void
    {
        $this->computePath();
        $this->computeLevel();
    }

    // ========== Path Management ==========

    /**
     * Compute full path from root
     */
    private function computePath(): void
    {
        if ($this->parent === null) {
            $this->path = '/' . $this->name;
        } else {
            $parentPath = $this->parent->getPath() ?? '/' . $this->parent->getName();
            $this->path = $parentPath . '/' . $this->name;
        }
    }

    /**
     * Compute tree depth level
     */
    private function computeLevel(): void
    {
        if ($this->parent === null) {
            $this->level = 0;
        } else {
            $this->level = $this->parent->getLevel() + 1;
        }
    }

    /**
     * Get all ancestor directories from root to current
     * @return Directory[]
     */
    public function getAncestors(): array
    {
        $ancestors = [];
        $current = $this->parent;
        
        while ($current !== null) {
            array_unshift($ancestors, $current);
            $current = $current->getParent();
        }
        
        return $ancestors;
    }

    /**
     * Get breadcrumb path as array of directories
     * @return Directory[]
     */
    public function getBreadcrumb(): array
    {
        $breadcrumb = $this->getAncestors();
        $breadcrumb[] = $this;
        return $breadcrumb;
    }

    /**
     * Check if this directory is root
     */
    public function isRoot(): bool
    {
        return $this->parent === null;
    }

    /**
     * Check if this directory has children
     */
    public function hasChildren(): bool
    {
        return $this->children->count() > 0;
    }

    /**
     * Get total count of items in this directory
     */
    public function getTotalItemsCount(): int
    {
        return $this->devices->count() 
            + $this->isos->count() 
            + $this->operatingSystems->count();
    }

    /**
     * Check if directory is empty (no items and no children)
     */
    public function isEmpty(): bool
    {
        return $this->getTotalItemsCount() === 0 && !$this->hasChildren();
    }

    // ========== Getters & Setters ==========

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): self
    {
        $this->path = $path;
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

    public function getParent(): ?Directory
    {
        return $this->parent;
    }

    public function setParent(?Directory $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return Collection<int, Directory>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(Directory $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }
        return $this;
    }

    public function removeChild(Directory $child): self
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }
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
            $device->setDirectory($this);
        }
        return $this;
    }

    public function removeDevice(Device $device): self
    {
        if ($this->devices->removeElement($device)) {
            if ($device->getDirectory() === $this) {
                $device->setDirectory(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Iso>
     */
    public function getIsos(): Collection
    {
        return $this->isos;
    }

    public function addIso(Iso $iso): self
    {
        if (!$this->isos->contains($iso)) {
            $this->isos->add($iso);
            $iso->setDirectory($this);
        }
        return $this;
    }

    public function removeIso(Iso $iso): self
    {
        if ($this->isos->removeElement($iso)) {
            if ($iso->getDirectory() === $this) {
                $iso->setDirectory(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, OperatingSystem>
     */
    public function getOperatingSystems(): Collection
    {
        return $this->operatingSystems;
    }

    public function addOperatingSystem(OperatingSystem $operatingSystem): self
    {
        if (!$this->operatingSystems->contains($operatingSystem)) {
            $this->operatingSystems->add($operatingSystem);
            $operatingSystem->setDirectory($this);
        }
        return $this;
    }

    public function removeOperatingSystem(OperatingSystem $operatingSystem): self
    {
        if ($this->operatingSystems->removeElement($operatingSystem)) {
            if ($operatingSystem->getDirectory() === $this) {
                $operatingSystem->setDirectory(null);
            }
        }
        return $this;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): self
    {
        $this->level = $level;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeInterface $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * Soft delete
     */
    public function delete(): self
    {
        $this->deletedAt = new \DateTime();
        return $this;
    }

    /**
     * Restore from soft delete
     */
    public function restore(): self
    {
        $this->deletedAt = null;
        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }
}