<?php

namespace App\Entity;

use App\Entity\Instance;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity(repositoryClass="App\Repository\LabInstanceRepository")
 * @Serializer\XmlRoot("lab_instance")
 */
class LabInstance extends Instance
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"primary_key"})
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Lab", inversedBy="instances")
     * @Serializer\Groups({"lab", "start_lab", "stop_lab"})
     */
    protected $lab;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="labInstances")
     * @Serializer\Groups({"user", "start_lab", "stop_lab"})
     */
    protected $user;

    /**
     * @ORM\Column(type="boolean")
     */
    protected $isInternetConnected;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\DeviceInstance", mappedBy="labInstance")
     * @Serializer\XmlList(inline=true, entry="device_instance")
     * @Serializer\Groups({"lab", "start_lab", "stop_lab"})
     */
    private $deviceInstances;

    public function __construct()
    {
        parent::__construct();
        $this->deviceInstances = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLab(): ?Lab
    {
        return $this->lab;
    }

    public function setLab(?Lab $lab): self
    {
        $this->lab = $lab;

        return $this;
    }
    
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({"lab", "start_lab", "stop_lab"})
     * @Serializer\XmlAttribute
     */
    public function getUserId(): ?int
    {
        return $this->user->getId();
    }

    /**
     * Generate a bridge name with instance UUID.
     * 
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({"lab", "start_lab", "stop_lab"})
     * @Serializer\XmlAttribute
     */
    public function getBridgeName(): string
    {
        return "br-" . substr($this->uuid, 0, 8);
    }

    /**
     * Get device instance associated to this lab instance
     *
     * @return DeviceInstance
     */
    public function getDeviceInstance($device): ?DeviceInstance
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq("device", $device));

        $deviceInstance = ($this->deviceInstances !== null) ? $this->deviceInstances->matching($criteria)->first() : null;
        
        return $deviceInstance ?: null;
    }

    /**
     * Get device instances associated to this lab instance
     *
     * @return Collection|DeviceInstance[]
     */
    public function getDeviceInstances(): Collection
    {
        return $this->deviceInstances;
    }

    /**
     * Get device instance associated to this lab instance and the current user
     *
     * @return DeviceInstance
     */
    public function getUserDeviceInstance($device): ?DeviceInstance
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq("device", $device))
            ->andWhere(Criteria::expr()->eq("user", $this->user))
        ;

        $deviceInstance = ($this->deviceInstances !== null) ? $this->deviceInstances->matching($criteria)->first() : null;
        
        return $deviceInstance ?: null;
    }

    /**
     * Get device instances associated to this lab instance and the current user
     *
     * @return Collection|DeviceInstance[]
     */
    public function getUserDeviceInstances(): Collection
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq("user", $this->user))
        ;

        $deviceInstances = $this->deviceInstances->matching($criteria);

        return $deviceInstances;
    }

    /**
     * Get network interface instances associated to this lab instance
     *
     * @return Collection|NetworkInterfaceInstance[]
     */
    public function getNetworkInterfaceInstances(): Collection
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq("user", $this->user))
        ;

        $networkInterfaceInstances = $this->lab->getNetworkInterfaceInstances()->matching($criteria);

        return $networkInterfaceInstances;
    }

    public function getIsInternetConnected(): ?bool
    {
        return $this->isInternetConnected;
    }

    public function setIsInternetConnected(bool $isInternetConnected): self
    {
        $this->isInternetConnected = $isInternetConnected;

        return $this;
    }
    
    public function addDeviceInstance(DeviceInstance $deviceInstance): self
    {
        if (!$this->deviceInstances->contains($deviceInstance)) {
            $this->deviceInstances[] = $deviceInstance;
            $deviceInstance->setLabInstance($this);
        }

        return $this;
    }

    public function removeDeviceInstance(DeviceInstance $deviceInstance): self
    {
        if ($this->deviceInstances->contains($deviceInstance)) {
            $this->deviceInstances->removeElement($deviceInstance);
            // set the owning side to null (unless already changed)
            if ($deviceInstance->getLabInstance() === $this) {
                $deviceInstance->setLabInstance(null);
            }
        }

        return $this;
    }

    public function hasDeviceInstance(): bool
    {
        return $this->deviceInstances->count() > 0;
    }
}
