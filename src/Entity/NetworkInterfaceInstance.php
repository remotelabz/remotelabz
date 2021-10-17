<?php

namespace App\Entity;

use App\Entity\Instance;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity(repositoryClass="App\Repository\NetworkInterfaceInstanceRepository")
 * @UniqueEntity(
 *     fields="macAddress",
 *     errorPath="macAddress",
 *     message="This MAC address is already used by another interface."
 * )
 */
class NetworkInterfaceInstance extends Instance
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Serializer\Groups({"api_get_device_instance"})
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\NetworkInterface")
     * @Serializer\Groups({"api_get_device_instance", "worker"})
     */
    protected $networkInterface;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\DeviceInstance", inversedBy="networkInterfaceInstances", cascade={"persist"})
     * @ORM\JoinColumn(onDelete="CASCADE")
     */
    private $deviceInstance;

    /**
     * @ORM\Column(type="string", length=17)
     * @Serializer\Groups({"api_get_device_instance", "worker"})
     * @Assert\Regex("/^[a-fA-F0-9:]{17}$/")
     */
    private $macAddress;

    public function __construct()
    {
        parent::__construct();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNetworkInterface(): ?NetworkInterface
    {
        return $this->networkInterface;
    }

    public function setNetworkInterface(?NetworkInterface $networkInterface): self
    {
        $this->networkInterface = $networkInterface;

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

    public function getMacAddress(): ?string
    {
        return $this->macAddress;
    }

    public function setMacAddress(string $macAddress): self
    {
        $this->macAddress = $macAddress;

        return $this;
    }

    public function populate()
    {
    }
}
