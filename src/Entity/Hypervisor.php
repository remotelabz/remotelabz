<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\HypervisorRepository")
 */
class Hypervisor
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $command;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\OperatingSystem", mappedBy="hypervisor")
     */
    private $operatingSystems;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $arguments;

    public function __construct()
    {
        $this->systems = new ArrayCollection();
    }

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

    public function getCommand(): ?string
    {
        return $this->command;
    }

    public function setCommand(string $command): self
    {
        $this->command = $command;

        return $this;
    }

    /**
     * @return Collection|OperatingSystem[]
     */
    public function getOperatingSystems(): Collection
    {
        return $this->operatingSystems;
    }

    public function addOperatingSystem(OperatingSystem $operatingSystem): self
    {
        if (!$this->operatingSystems->contains($operatingSystem)) {
            $this->operatingSystems[] = $operatingSystem;
            $operatingSystem->setHypervisor($this);
        }

        return $this;
    }

    public function removeOperatingSystem(OperatingSystem $operatingSystem): self
    {
        if ($this->operatingSystems->contains($operatingSystem)) {
            $this->operatingSystems->removeElement($operatingSystem);
            // set the owning side to null (unless already changed)
            if ($operatingSystem->getHypervisor() === $this) {
                $operatingSystem->setHypervisor(null);
            }
        }

        return $this;
    }

    public function getArguments(): ?string
    {
        return $this->arguments;
    }

    public function setArguments(?string $arguments): self
    {
        $this->arguments = $arguments;

        return $this;
    }
}
