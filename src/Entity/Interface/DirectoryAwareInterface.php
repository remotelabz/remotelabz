<?php

namespace App\Entity\Interface;

use App\Entity\Directory;

/**
 * Interface for entities that can be organized in directories
 * Ensures consistent behavior across different entity types
 */
interface DirectoryAwareInterface
{
    /**
     * Get the directory containing this entity
     */
    public function getDirectory(): ?Directory;

    /**
     * Set the directory containing this entity
     */
    public function setDirectory(?Directory $directory): self;

    /**
     * Get the full path including directory path and entity name
     */
    public function getFullPath(): string;
}