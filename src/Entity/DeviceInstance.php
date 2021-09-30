<?php

namespace App\Entity;

use Exception;
use App\Entity\Instance;
use App\Instance\InstanceState;
use App\Utils\MacAddress;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DeviceInstanceRepository")
 * @Serializer\XmlRoot("device_instance")
 */
class DeviceInstance extends Instance
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"api_get_device_instance"})
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Device")
     * @Serializer\Groups({"api_get_lab_instance","api_get_device_instance", "worker"})
     */
    protected $device;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\LabInstance", inversedBy="deviceInstances", cascade={"persist", "remove"})
     * @ORM\JoinColumn(onDelete="CASCADE")
     * @Serializer\Groups({"api_get_lab_instance","api_get_device_instance"})
     */
    protected $labInstance;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\NetworkInterfaceInstance", mappedBy="deviceInstance", cascade={"persist"})
     * @Serializer\Groups({"api_get_device_instance", "worker"})
     */
    protected $networkInterfaceInstances;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\Groups({"api_get_lab_instance","api_get_device_instance", "worker"})
     */
    private $state;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Serializer\Groups({"api_get_lab_instance","api_get_device_instance", "worker"})
     */
    private $remotePort;

    /**
     * @ORM\OneToMany(targetEntity=DeviceInstanceLog::class, mappedBy="deviceInstance", cascade={"persist"})
     * @Serializer\Exclude
     */
    private $logs;

    public function __construct()
    {
        parent::__construct();
        $this->state = InstanceState::STOPPED;
        $this->networkInterfaceInstances = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\SerializedName("owner")
     * @Serializer\Groups({"api_get_device_instance"})
     * @Serializer\XmlAttribute
     */
    public function getOwnerId()
    {
        $id = null;
        // TODO: refractor to "getOwnerId"
        switch ($this->ownedBy) {
            case self::OWNED_BY_USER:
                $id = $this->user;
            break;
            case self::OWNED_BY_GROUP:
                $id = $this->_group;
            break;
        }

        return $id;
    }

    public function getLab(): ?Lab
    {
        return $this->labInstance->getLab();
    }

    public function getLabInstance(): ?LabInstance
    {
        return $this->labInstance;
    }

    public function setLabInstance(?LabInstance $labInstance): self
    {
        $this->labInstance = $labInstance;

        return $this;
    }

    /**
     * Get network interface instance associated to this lab instance
     *
     * @return NetworkInterfaceInstance
     */
    public function getNetworkInterfaceInstance($networkInterface): ?NetworkInterfaceInstance
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq("networkInterface", $networkInterface));

        $networkInterfaceInstance = ($this->networkInterfaceInstances !== null) ? $this->networkInterfaceInstances->matching($criteria)->first() : null;

        return $networkInterfaceInstance ?: null;
    }

    /**
     * Get network interface instances associated to this lab instance
     *
     * @return Collection|NetworkInterfaceInstance[]
     */
    public function getNetworkInterfaceInstances(): Collection
    {
        return $this->networkInterfaceInstances;
    }

    public function addNetworkInterfaceInstance(NetworkInterfaceInstance $networkInterfaceInstance): self
    {
        if (!$this->networkInterfaceInstances->contains($networkInterfaceInstance)) {
            $this->networkInterfaceInstances[] = $networkInterfaceInstance;
            $networkInterfaceInstance->setDeviceInstance($this);
        }

        return $this;
    }

    public function removeNetworkInterfaceInstance(NetworkInterfaceInstance $networkInterfaceInstance): self
    {
        if ($this->networkInterfaceInstances->contains($networkInterfaceInstance)) {
            $this->networkInterfaceInstances->removeElement($networkInterfaceInstance);
            // set the owning side to null (unless already changed)
            if ($networkInterfaceInstance->getDeviceInstance() === $this) {
                $networkInterfaceInstance->setDeviceInstance(null);
            }
        }

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function isStarted(): ?bool
    {
        return $this->state === InstanceState::STARTED;
    }

    public function getRemotePort(): ?int
    {
        return $this->remotePort;
    }

    public function setRemotePort(?int $remotePort): self
    {
        $this->remotePort = $remotePort;

        return $this;
    }

    /**
     * @return Collection|DeviceInstanceLog[]
     */
    public function getLogs()
    {
        return $this->logs;
    }

    /**
     * @return Collection|DeviceInstanceLog[]
     */
    public function getPublicLogs()
    {
        return \array_filter($this->logs, function ($log) { return $log->getScope() === DeviceInstanceLog::SCOPE_PUBLIC; });
    }

    public function addLog(DeviceInstanceLog $log): DeviceInstance
    {
        if (!$this->logs->contains($log)) {
            $this->logs[] = $log;
            $log->setDeviceInstance($this);
        }

        return $this;
    }

    public function removeLog(DeviceInstanceLog $log): DeviceInstance
    {
        if ($this->logs->contains($log)) {
            $this->logs->removeElement($log);
            // set the owning side to null (unless already changed)
            if ($log->getDeviceInstance() === $this) {
                $log->setDeviceInstance(null);
            }
        }

        return $this;
    }

    /**
     * Creates all sub-instances from device descriptor. This does not record them in the database.
     */
    public function populate()
    {
        if (!$this->device) {
            throw new Exception('No device is associated to this instance.');
        }

        /** @var NetworkInterface $networkInterface */
        foreach ($this->device->getNetworkInterfaces() as $networkInterface) {
            $networkInterfaceInstance = NetworkInterfaceInstance::create()
                ->setNetworkInterface($networkInterface)
                ->setDeviceInstance($this)
                ->setMacAddress(MacAddress::generate(['52', '54', '00']))
                ->setOwnedBy($this->ownedBy);

            switch ($this->ownedBy) {
                case self::OWNED_BY_USER:
                    $networkInterfaceInstance->setUser($this->user);
                    break;

                case self::OWNED_BY_GROUP:
                    $networkInterfaceInstance->setGroup($this->_group);
                    break;
            }

            $networkInterfaceInstance->populate();

            // $networkInterface->addInstance($networkInterfaceInstance);
            $this->addNetworkInterfaceInstance($networkInterfaceInstance);
        }
    }
}
