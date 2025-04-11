<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: 'App\Repository\EditorDataRepository')]
class EditorData
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups([])]
    private $id;

    #[Assert\Type(type: 'int')]
    #[Assert\GreaterThanOrEqual(0)]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['api_get_device', 'api_get_lab_template', 'export_lab'])]
    private int $x;

    #[Assert\Type(type: 'int')]
    #[Assert\GreaterThanOrEqual(0)]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['api_get_device', 'api_get_lab_template', 'export_lab'])]
    private int $y;

    #[ORM\OneToOne(targetEntity: 'App\Entity\Device', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'device_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Serializer\Groups([])]
    private $device;

    public function __construct()
    {
        $this->x = 0;
        $this->y = 0;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getX(): ?int
    {
        return $this->x;
    }

    public function setX(int $x): self
    {
        $this->x = $x;

        return $this;
    }

    public function getY(): ?int
    {
        return $this->y;
    }

    public function setY(int $y): self
    {
        $this->y = $y;

        return $this;
    }

    public function getDevice(): ?Device
    {
        return $this->device;
    }

    public function setDevice(?Device $device): self
    {
        $this->device = $device;

        return $this;
    }
}
