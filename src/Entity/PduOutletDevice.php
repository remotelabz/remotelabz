<?php

namespace App\Entity;

use App\Entity\Pdu;
use App\Entity\Device;
use App\Repository\PduOutletDeviceRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
#[UniqueEntity(fields: ['outlet', 'pdu'], message: 'This outlet is already in use on that pdu.')]
#[ORM\Entity(repositoryClass: PduOutletDeviceRepository::class)]
class PduOutletDevice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[Assert\GreaterThan(value: 0)]
    #[Assert\LessThanOrEqual(value: 42)]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['worker', 'api_get_device'])]
    private $outlet;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Pdu', inversedBy: 'outlets')]
    #[ORM\JoinColumn(name: 'pdu_id', referencedColumnName: 'id')]
    #[Serializer\Groups(['worker', 'api_get_device'])]
    private $pdu;

    #[ORM\OneToOne(targetEntity: 'App\Entity\Device', inversedBy: 'outlet')]
    #[ORM\JoinColumn(name: 'device_id', referencedColumnName: 'id')]
    private $device;
    

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOutlet(): ?int
    {
        return $this->outlet;
    }

    public function setOutlet(int $outlet): self
    {
        $this->outlet = $outlet;

        return $this;
    }

    public function getPdu(): ?Pdu
    {
        return $this->pdu;
    }

    public function setPdu(Pdu $pdu): self
    {
        $this->pdu = $pdu;

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
