<?php

namespace App\Entity;

use Exception;
use App\Entity\Instance;
use App\Entity\ControlProtocolTypeInstance;
use App\Entity\NetworkInterfaceInstance;
use App\Instance\InstanceState;
use App\Utils\MacAddress;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: 'App\Repository\DeviceInstanceRepository')]
#[Serializer\XmlRoot('device_instance')]
class DeviceInstance extends Instance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Serializer\XmlAttribute]
    #[Serializer\Groups(['api_get_device_instance', 'sandbox', 'api_get_lab_instance'])]
    private $id;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Device')]
    #[Serializer\Groups(['api_get_lab_instance', 'api_get_device_instance', 'worker', 'sandbox'])]
    protected $device;

    #[Assert\NotNull]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['api_get_lab_instance', 'api_get_device_instance', 'worker', 'sandbox'])]
    private $nbCpu;

        #[ORM\Column(type: 'integer', nullable: true)]
    #[Serializer\Groups(['api_get_lab_instance', 'api_get_device_instance', 'worker', 'sandbox'])]
    private $nbCore;

        #[ORM\Column(type: 'integer', nullable: true)]
    #[Serializer\Groups(['api_get_lab_instance', 'api_get_device_instance', 'worker', 'sandbox'])]
    private $nbSocket;

        #[ORM\Column(type: 'integer', nullable: true)]
    #[Serializer\Groups(['api_get_lab_instance', 'api_get_device_instance', 'worker', 'sandbox'])]
    private $nbThread;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\LabInstance', inversedBy: 'deviceInstances', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[Serializer\Groups(['api_get_lab_instance', 'api_get_device_instance'])]
    protected $labInstance;

    #[ORM\OneToMany(targetEntity: 'App\Entity\NetworkInterfaceInstance', mappedBy: 'deviceInstance', cascade: ['persist'])]
    #[Serializer\Groups(['api_get_device_instance', 'worker', 'sandbox', 'api_get_lab_instance'])]
    protected $networkInterfaceInstances;

    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['api_get_lab_instance', 'api_get_device_instance', 'worker', 'sandbox'])]
    private $state;

     #[ORM\OneToMany(targetEntity: 'App\Entity\ControlProtocolTypeInstance', mappedBy: 'deviceInstance', cascade: ['persist'])]
    #[Serializer\Groups(['api_get_lab_instance', 'api_get_device_instance', 'worker', 'sandbox'])]
    private $controlProtocolTypeInstances;
    

    #[ORM\OneToMany(targetEntity: DeviceInstanceLog::class, mappedBy: 'deviceInstance', cascade: ['persist'])]
    #[Serializer\Exclude]
    private $logs;

    public function __construct()
    {
        parent::__construct();
        $this->state = InstanceState::STOPPED;
        $this->networkInterfaceInstances = new ArrayCollection();
        $this->controlProtocolTypeInstances = new ArrayCollection();
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

    public function getNbCpu(): ?int
    {
        return $this->nbCpu;
    }

    public function setNbCpu(int $nb): self
    {
        $this->nbCpu = $nb;

        return $this;
    }

    public function getNbSocket(): ?int
    {
        return $this->nbSocket;
    }

    public function setNbSocket(?int $nb): void
    {
        $this->nbSocket = $nb;
    }

    public function getNbCore(): ?int
    {
        return $this->nbCore;
    }

    public function setNbCore(?int $nb): void
    {
        $this->nbCore = $nb;
    }

    public function getNbThread(): ?int
    {
        return $this->nbThread;
    }

    public function setNbThread(?int $nb): void
    {
        $this->nbThread = $nb;
    }
    
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('owner')]
    #[Serializer\Groups(['api_get_device_instance', 'worker'])]
    #[Serializer\XmlAttribute]
    public function getOwnerId()
    {
        $id = null;
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
    public function getNetworkInterfaceInstances()
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
     * Get network interface instance associated to this lab instance
     *
     * @return ControlProtocolTypeInstance
     */
    public function getControlProtocolTypeInstance($controlProtocolType): ?ControlProtocolTypeInstance
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq("controlProtocolType", $controlProtocolType));

        $controlProtocolTypeInstance = ($this->controlProtocolTypeInstances !== null) ? $this->controlProtocolTypeInstances->matching($criteria)->first() : null;

        return $controlProtocolTypeInstance ?: null;
    }

    /**
     * Get network interface instances associated to this lab instance
     *
     * @return Collection|ControlProtocolTypeInstance[]
     */
    public function getControlProtocolTypeInstances()
    {
        return $this->controlProtocolTypeInstances;
    }

    public function addControlProtocolTypeInstance(ControlProtocolTypeInstance $controlProtocolTypeInstance): self
    {
        if (!$this->controlProtocolTypeInstances->contains($controlProtocolTypeInstance)) {
            $this->controlProtocolTypeInstances[] = $controlProtocolTypeInstance;
            $controlProtocolTypeInstance->setDeviceInstance($this);
        }

        return $this;
    }

    public function removeControlProtocolTypeInstance(ControlProtocolTypeInstance $controlProtocolTypeInstance): self
    {
        if ($this->controlProtocolTypeInstances->contains($controlProtocolTypeInstance)) {
            $this->controlProtocolTypeInstances->removeElement($controlProtocolTypeInstance);
            // set the owning side to null (unless already changed)
            if ($controlProtocolTypeInstance->getDeviceInstance() === $this) {
                $controlProtocolTypeInstance->setDeviceInstance(null);
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

                case self::OWNED_BY_GUEST:
                    $networkInterfaceInstance->setGuest($this->guest);
                    break;

                case self::OWNED_BY_GROUP:
                    $networkInterfaceInstance->setGroup($this->_group);
                    break;
            }

            $networkInterfaceInstance->populate();

            // $networkInterface->addInstance($networkInterfaceInstance);
            $this->addNetworkInterfaceInstance($networkInterfaceInstance);
        }

        /** @var ControlProtocolType $controlProtocolType */
        foreach ($this->device->getControlProtocolTypes() as $controlProtocolType) {
            $controlProtocolTypeInstance = ControlProtocolTypeInstance::create()
                ->setControlProtocolType($controlProtocolType)
                ->setPort(0)
                ->setOwnedBy($this->ownedBy);
                switch ($this->ownedBy) {
                    case self::OWNED_BY_USER:
                        $controlProtocolTypeInstance->setUser($this->user);
                        break;

                    case self::OWNED_BY_GUEST:
                        $controlProtocolTypeInstance->setGuest($this->guest);
                        break;
    
                    case self::OWNED_BY_GROUP:
                        $controlProtocolTypeInstance->setGroup($this->_group);
                        break;
                }
            $this->addControlProtocolTypeInstance($controlProtocolTypeInstance);
        }

    }
}
