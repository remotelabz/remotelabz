<?php

namespace App\Entity;

use App\Utils\Uuid;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use App\Instance\InstanciableInterface;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity(repositoryClass="App\Repository\LabRepository")
 */
class Lab implements InstanciableInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Serializer\Groups({"primary_key"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\Groups({"lab", "start_lab", "stop_lab", "instance_manager"})
     */
    private $name;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Serializer\Groups({"lab"})
     */
    private $description;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Device", inversedBy="labs")
     * @Serializer\Groups({"lab"})
     */
    private $devices;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LabInstance", mappedBy="lab", cascade={"persist", "remove"})
     * @Serializer\Groups({"lab", "start_lab", "stop_lab"})
     */
    private $instances;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="createdLabs")
     * @Serializer\Groups({"lab", "lab_author"})
     */
    private $author;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\Groups({"lab", "start_lab", "stop_lab", "instance_manager"})
     */
    private $uuid;

    /**
     * @ORM\Column(type="datetime")
     * @Serializer\Groups({"lab"})
     */
    private $createdAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Serializer\Groups({"lab"})
     */
    private $lastUpdated;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\NetworkSettings", inversedBy="lab", cascade={"persist", "remove"})
     */
    private $networkSettings;

    public function __construct()
    {
        $this->devices = new ArrayCollection();
        $this->connexions = new ArrayCollection();
        $this->activities = new ArrayCollection();
        $this->instances = new ArrayCollection();
        $this->uuid = (string) new Uuid();
        $this->deviceInstances = new ArrayCollection();
        $this->networkInterfaceInstances = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->lastUpdated = new \DateTime();
    }

    public static function create(): self
    {
        return new static();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection|Device[]
     */
    public function getDevices()
    {
        return $this->devices;
    }

    public function addDevice(Device $device): self
    {
        if (!$this->devices->contains($device)) {
            $this->devices[] = $device;
        }

        return $this;
    }

    public function removeDevice(Device $device): self
    {
        if ($this->devices->contains($device)) {
            $this->devices->removeElement($device);
        }

        return $this;
    }

    /**
     * @return Collection|Instance[]
     */
    public function getInstances()
    {
        return $this->instances;
    }

    /**
     * Returns the instance corresponding to user.
     *
     * @param User $user
     * @return LabInstance|null
     */
    public function getUserInstance(User $user): ?LabInstance
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq("user", $user));

        $instance = $this->instances->matching($criteria)->first();

        return $instance ?: null;
    }

    public function addInstance(Instance $instance): self
    {
        if (!$this->instances->contains($instance)) {
            $this->instances[] = $instance;
        }

        return $this;
    }

    public function removeInstance(Instance $instance): self
    {
        if ($this->instances->contains($instance)) {
            $this->instances->removeElement($instance);
        }

        return $this;
    }

    public function hasDeviceUserInstance(User $user): bool
    {
        return $this->devices->exists(function ($index, Device $device) use ($user) {
            return $device->getUserInstance($user) != null;
        });
    }

    public function setInstances(array $instances): self
    {
        $this->getInstances()->clear();
        foreach ($instances as $instance) {
            $this->addInstance($instance);
        }

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
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

    public function getLastUpdated(): ?\DateTimeInterface
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(?\DateTimeInterface $lastUpdated): self
    {
        $this->lastUpdated = $lastUpdated;

        return $this;
    }

    public function getNetworkSettings(): ?NetworkSettings
    {
        return $this->networkSettings;
    }

    public function setNetworkSettings(?NetworkSettings $networkSettings): self
    {
        $this->networkSettings = $networkSettings;

        return $this;
    }
}
