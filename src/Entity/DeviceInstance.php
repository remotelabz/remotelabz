<?php

namespace App\Entity;

use App\Entity\Instance;
use App\Instance\InstanceState;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Criteria;
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
     * @Serializer\Groups({"primary_key"})
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Device", inversedBy="instances")
     * @Serializer\Groups({"lab", "start_lab", "stop_lab", "instance_manager"})
     */
    protected $device;

    // /**
    //  * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="deviceInstances")
    //  * @Serializer\Groups({"user"})
    //  */
    // protected $user;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Lab", inversedBy="deviceInstances")
     * @ORM\JoinColumn(nullable=false)
     * @Serializer\Groups({"lab"})
     */
    private $lab;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\LabInstance", inversedBy="deviceInstances", cascade={"persist"})
     * @Serializer\XmlElement(cdata=false)
     * @Serializer\Groups({"lab"})
     */
    protected $labInstance;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\NetworkInterfaceInstance", mappedBy="deviceInstance")
     * @Serializer\XmlList(inline=true, entry="network_interface_instance")
     * @Serializer\Groups({"lab", "start_lab", "stop_lab"})
     */
    protected $networkInterfaceInstances;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\Groups({"lab", "start_lab", "stop_lab", "instance_manager", "instances"})
     */
    private $state;

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

    // public function getUser(): ?User
    // {
    //     return $this->user;
    // }

    // public function setUser(?User $user): self
    // {
    //     $this->user = $user;

    //     return $this;
    // }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({"lab"})
     * @Serializer\XmlAttribute
     */
    public function getUserId(): ?int
    {
        return $this->user->getId();
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
    public function getNetworkInterfaceInstances(): ArrayCollection
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
}
