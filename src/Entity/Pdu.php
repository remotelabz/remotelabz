<?php

namespace App\Entity;

use App\Entity\PduOutletDevice;
use App\Repository\PduRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[UniqueEntity('ip')]
#[ORM\Entity(repositoryClass: PduRepository::class)]
class Pdu
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[Assert\Choice(['raritan', 'apc'])]
    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['worker', 'api_get_device'])]
    private $brand;

    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['worker', 'api_get_device'])]
    private $model;

    #[Assert\Range(min: 0, max: 42)]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['worker', 'api_get_device'])]
    private $numberOfOutlets;

    #[Assert\Ip(version: 4)]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Serializer\Groups(['worker', 'api_get_device'])]
    private $ip;

    #[ORM\OneToMany(targetEntity: 'App\Entity\PduOutletDevice', mappedBy: 'pdu', cascade: ['persist', 'remove'])]
    private $outlets;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(string $brand): self
    {
        $this->brand = $brand;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function getNumberOfOutlets(): ?int
    {
        return $this->numberOfOutlets;
    }

    public function setNumberOfOutlets(int $numberOfOutlets): self
    {
        $this->numberOfOutlets = $numberOfOutlets;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    /**
     * @return Collection|PduOutletDevice[]
     */
    public function getOutlets()
    {
        return $this->outlets;
    }

    public function addOutlet(PduOutletDevice $outlet): self
    {
        if (!$this->outlets->contains($outlet)) {
            $this->outlets[] = $outlet;
            $outlet->setPdu($this);
        }

        return $this;
    }

    public function removeOutlet(PduOutletDevice $outlet): self
    {
        if ($this->outlets->contains($createdLoutletab)) {
            $this->outlets->removeElement($outlet);
            if ($outlet->getPdu() === $this) {
                $outlet->setPdu(null);
            }
        }

        return $this;
    }
}
