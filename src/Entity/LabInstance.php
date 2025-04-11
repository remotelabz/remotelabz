<?php

namespace App\Entity;

use Remotelabz\Message\Message\InstanceStateMessage;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use JMS\Serializer\Annotation as Serializer;
use Remotelabz\NetworkBundle\Entity\Network;

#[ORM\Entity(repositoryClass: 'App\Repository\LabInstanceRepository')]
class LabInstance extends Instance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['api_get_lab_instance', 'api_get_device_instance', 'sandbox', 'api_get_user'])]
    private $id;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Lab')]
    #[Serializer\Groups(['api_get_lab_instance', 'worker', 'sandbox'])]
    protected $lab;

    #[ORM\OneToMany(targetEntity: 'App\Entity\DeviceInstance', mappedBy: 'labInstance', cascade: ['persist', 'remove'])]
    #[Serializer\Groups(['api_get_lab_instance', 'worker', 'sandbox'])]
    private $deviceInstances;

    #[ORM\Column(type: 'boolean')]
    #[Serializer\Groups(['api_get_lab_instance', 'worker'])]
    private $isInterconnected;

    #[ORM\Column(type: 'datetime')]
    #[Serializer\Groups(['api_get_lab_instance'])]
    private $createdAt;

    #[ORM\Column(type: 'boolean')]
    #[Serializer\Groups(['api_get_lab_instance', 'worker'])]
    private $isInternetConnected;

    #[ORM\OneToOne(targetEntity: 'Remotelabz\NetworkBundle\Entity\Network', cascade: ['persist', 'remove'])]
    #[Serializer\Groups(['api_get_lab_instance', 'worker'])]
    private $network;

    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['api_get_lab_instance', 'worker'])]
    private $state;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Serializer\Groups(['api_get_lab_instance', 'worker'])]
    private $timerEnd;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Serializer\Groups(['api_get_lab_instance', 'worker'])]
    private $workerIp;


    #[ORM\OneToOne(targetEntity: 'App\Entity\JitsiCall', cascade: ['persist', 'remove'])]
    #[Serializer\Groups(['api_get_lab_instance'])]
    private $jitsiCall;

    const SCOPE_STANDALONE = 'standalone';
    const SCOPE_ACTIVITY = 'activity';

    public function __construct()
    {
        parent::__construct();
        $this->deviceInstances = new ArrayCollection();
        $this->scope = self::SCOPE_STANDALONE;
        $this->state = InstanceStateMessage::STATE_CREATING;
        $this->createdAt = new \DateTime();
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

    /**
     * Generate a bridge name with instance UUID.
     */
    #[Serializer\VirtualProperty]
    #[Serializer\Groups(['api_get_lab_instance', 'worker'])]
    #[Serializer\XmlAttribute]
    public function getBridgeName(): string
    {
        return 'br-'.substr($this->uuid, 0, 8);
    }

    /**
     * Get device instance associated to this lab instance.
     *
     * @return DeviceInstance
     */
    public function getDeviceInstance($device): ?DeviceInstance
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('device', $device));

        $deviceInstance = (null !== $this->deviceInstances) ? $this->deviceInstances->matching($criteria)->first() : null;

        return $deviceInstance ?: null;
    }

    /**
     * Get device instances associated to this lab instance.
     *
     * @return Collection|DeviceInstance[]
     */
    public function getDeviceInstances()
    {
        return $this->deviceInstances;
    }

    /**
     * Get device instance associated to this lab instance and the current user.
     *
     * @return DeviceInstance
     */
    public function getUserDeviceInstance($device): ?DeviceInstance
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('device', $device))
            ->andWhere(Criteria::expr()->eq('user', $this->user));

        $deviceInstance = (null !== $this->deviceInstances) ? $this->deviceInstances->matching($criteria)->first() : null;

        return $deviceInstance ?: null;
    }

    /**
     * Get device instances associated to this lab instance and the current user.
     *
     * @return Collection|DeviceInstance[]
     */
    public function getUserDeviceInstances()
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq('user', $this->user));

        $deviceInstances = $this->deviceInstances->matching($criteria);

        return $deviceInstances;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function isInternetConnected(): ?bool
    {
        return $this->isInternetConnected;
    }

    public function setInternetConnected(bool $isInternetConnected): self
    {
        $this->isInternetConnected = $isInternetConnected;

        return $this;
    }

    public function isInterconnected(): ?bool
    {
        return $this->isInterconnected;
    }

    public function setInterconnected(bool $isInterconnected): self
    {
        $this->isInterconnected = $isInterconnected;

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

    public function getNetwork(): ?Network
    {
        return $this->network;
    }

    public function setNetwork(?Network $network): self
    {
        $this->network = $network;

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

    public function getTimerEnd(): ?\DateTimeInterface
    {
        return $this->timerEnd;
    }

    public function setTimerEnd(\DateTimeInterface $timerEnd): self
    {
        $this->timerEnd = $timerEnd;

        return $this;
    }

    public function getWorkerIp(): ?string
    {
        return $this->workerIp;
    }

    public function setWorkerIp(?string $workerIp): self
    {
        $this->workerIp = $workerIp;

        return $this;
    }

    public function isCreated(): ?bool
    {
        return InstanceStateMessage::STATE_CREATED === $this->state;
    }

    public function getJitsiCall(): ?JitsiCall
    {
        return $this->jitsiCall;
    }

    public function setJitsiCall(?JitsiCall $jitsiCall): self
    {
        $this->jitsiCall = $jitsiCall;

        return $this;
    }

    /**
     * Creates all sub-instances from Lab descriptor. This does not record them in the database.
     */
    public function populate()
    {
        if (!$this->lab) {
            throw new Exception('No lab is associated to this instance.');
        }

        foreach ($this->lab->getDevices() as $device) {
            $deviceInstance = DeviceInstance::create()
                ->setDevice($device)
                ->setNbCpu($device->getNbCpu())
                ->setLabInstance($this)
                ->setOwnedBy($this->ownedBy);

            switch ($this->ownedBy) {
                case self::OWNED_BY_USER:
                    $deviceInstance->setUser($this->user);
                    break;

                case self::OWNED_BY_GUEST:
                    $deviceInstance->setGuest($this->guest);
                    break;

                case self::OWNED_BY_GROUP:
                    $deviceInstance->setGroup($this->_group);
                    break;
            }

            $deviceInstance->populate();

            $this->addDeviceInstance($deviceInstance);
        }

        if (self::OWNED_BY_GROUP == $this->ownedBy) {
            $jitsiCall = new JitsiCall();
            $this->setJitsiCall($jitsiCall);
        }
    }
}
