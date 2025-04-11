<?php

namespace App\Entity;

use App\Repository\ControlProtocolTypeInstanceRepository;
use App\Entity\ControlProtocolType;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Instance;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: ControlProtocolTypeInstanceRepository::class)]
class ControlProtocolTypeInstance extends Instance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['api_get_lab_instance', 'api_get_device_instance', 'worker', 'sandbox'])]
    private $port;

     #[ORM\ManyToOne(targetEntity: 'App\Entity\ControlProtocolType', inversedBy: 'controlProtocolTypeInstances', cascade: ['persist'])]
    #[Serializer\Groups(['api_get_lab_instance', 'api_get_device_instance', 'worker', 'sandbox'])]
    private $controlProtocolType;

     #[ORM\ManyToOne(targetEntity: 'App\Entity\DeviceInstance', inversedBy: 'controlProtocolTypeInstances', cascade: ['persist'])]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private $deviceInstance;

    public function __construct()
    {
        parent::__construct();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function setPort(int $port): self
    {
        $this->port = $port;

        return $this;
    }

    public function getDeviceInstance(): ?DeviceInstance
    {
        return $this->deviceInstance;
    }

    public function setDeviceInstance(?DeviceInstance $deviceInstance): self
    {
        $this->deviceInstance = $deviceInstance;

        return $this;
    }

    public function getControlProtocolType(): ?ControlProtocolType
    {
        return $this->controlProtocolType;
    }

    public function setControlProtocolType(?ControlProtocolType $controlProtocolType): self
    {
        $this->controlProtocolType = $controlProtocolType;

        return $this;
    }


}
