<?php

namespace App\Entity\Trait;

use App\Entity\Directory;

/**
 * Trait to add directory relationship to entities
 * Implements common directory-related functionality
 */
trait DirectoryAwareTrait
{
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