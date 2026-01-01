<?php

namespace App\Entity\Trait;

use App\Entity\Directory;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Trait to add directory relationship to entities
 * Implements common directory-related functionality
 */
trait DirectoryAwareTrait
{
    /**
     * The directory containing this entity
     */
    #[ORM\ManyToOne(targetEntity: Directory::class)]
    #[ORM\JoinColumn(name: 'directory_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Serializer\Groups(['api_directory_aware'])]
    private ?Directory $directory = null;

    /**
     * Get the directory containing this entity
     */
    public function getDirectory(): ?Directory
    {
        return $this->directory;
    }

    /**
     * Set the directory containing this entity
     */
    public function setDirectory(?Directory $directory): self
    {
        $this->directory = $directory;
        return $this;
    }

    /**
     * Get the full path including directory path and entity name
     * Requires the entity to have a getName() method
     */
    public function getFullPath(): string
    {
        if ($this->directory === null) {
            return '/' . $this->getName();
        }
        
        return $this->directory->getPath() . '/' . $this->getName();
    }

    /**
     * Check if entity is in root directory (no parent directory)
     */
    public function isInRoot(): bool
    {
        return $this->directory === null;
    }

    /**
     * Get breadcrumb including directory path and entity
     * @return array
     */
    public function getBreadcrumb(): array
    {
        if ($this->directory === null) {
            return [$this];
        }
        
        $breadcrumb = $this->directory->getBreadcrumb();
        $breadcrumb[] = $this;
        return $breadcrumb;
    }
}