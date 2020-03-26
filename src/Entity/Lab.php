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
 * @Serializer\XmlRoot("lab")
 */
class Lab implements InstanciableInterface
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
     * @ORM\Column(type="string", length=255)
     * @Serializer\XmlAttribute
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
     * @Serializer\XmlList(inline=true, entry="device")
     * @Serializer\Groups({"lab"})
     */
    private $devices;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Connexion", inversedBy="labs")
     * @Serializer\XmlList(inline=true, entry="connexion")
     * @Serializer\Groups({"lab", "start_lab", "stop_lab"})
     */
    private $connexions;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Activity", mappedBy="lab")
     * @Serializer\XmlList(inline=true, entry="activity")
     * @Serializer\Groups({"lab"})
     */
    private $activities;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LabInstance", mappedBy="lab", cascade={"persist", "remove"})
     * @Serializer\XmlList(inline=true, entry="instance")
     * @Serializer\Groups({"lab", "start_lab", "stop_lab"})
     */
    private $instances;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="createdLabs")
     * @Serializer\XmlElement(cdata=false)
     * @Serializer\Groups({"lab", "lab_author"})
     */
    private $author;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"lab", "start_lab", "stop_lab", "instance_manager"})
     */
    private $uuid;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\DeviceInstance", mappedBy="lab")
     * @Serializer\Exclude
     */
    private $deviceInstances;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\NetworkInterfaceInstance", mappedBy="lab")
     * @Serializer\Exclude
     */
    private $networkInterfaceInstances;

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
     * @return Collection|Devices[]
     */
    public function getDevices(): Collection
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
     * @return Collection|Connexion[]
     */
    public function getConnexions(): Collection
    {
        return $this->connexions;
    }

    public function addConnexion(Connexion $connexion): self
    {
        if (!$this->connexions->contains($connexion)) {
            $this->connexions[] = $connexion;
        }

        return $this;
    }

    public function removeConnexion(Connexion $connexion): self
    {
        if ($this->connexions->contains($connexion)) {
            $this->connexions->removeElement($connexion);
        }

        return $this;
    }

    /**
     * @return Collection|Activity[]
     */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    public function addActivity(Activity $activity): self
    {
        if (!$this->activities->contains($activity)) {
            $this->activities[] = $activity;
            $activity->setLab($this);
        }

        return $this;
    }

    public function removeActivity(Activity $activity): self
    {
        if ($this->activities->contains($activity)) {
            $this->activities->removeElement($activity);
            // set the owning side to null (unless already changed)
            if ($activity->getLab() === $this) {
                $activity->setLab(null);
            }
        }

        return $this;
    }

    /*public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }
*/

    /**
     * @return Collection|Instance[]
     */
    public function getInstances(): Collection
    {
        return $this->instances;
    }

    /**
     * Returns the instance corresponding to user.
     *
     * @param User $user
     * @return LabInstance|null
     */
    public function getUserInstance(User $user): ?Instance
    {
        $criteria = Criteria::create()->where(Criteria::expr()->eq("user", $user));

        $instance = $this->getInstances()->matching($criteria)->first();

        if (!$instance) {
            return null;
        }
        return $instance;
    }

    public function addInstance(Instance $instance): self
    {
        if (!$this->instances->contains($instance)) {
            $this->instances[] = $instance;
            $instance->setLab($this);
        }

        return $this;
    }

    public function removeInstance(Instance $instance): self
    {
        if ($this->instances->contains($instance)) {
            $this->instances->removeElement($instance);
            // set the owning side to null (unless already changed)
            if ($instance->getLab() === $this) {
                $instance->setLab(null);
            }
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

    /**
     * @return Collection|DeviceInstance[]
     */
    public function getDeviceInstances(): Collection
    {
        return $this->deviceInstances;
    }

    public function addDeviceInstance(DeviceInstance $deviceInstance): self
    {
        if (!$this->deviceInstances->contains($deviceInstance)) {
            $this->deviceInstances[] = $deviceInstance;
            $deviceInstance->setLab($this);
        }

        return $this;
    }

    public function removeDeviceInstance(DeviceInstance $deviceInstance): self
    {
        if ($this->deviceInstances->contains($deviceInstance)) {
            $this->deviceInstances->removeElement($deviceInstance);
            // set the owning side to null (unless already changed)
            if ($deviceInstance->getLab() === $this) {
                $deviceInstance->setLab(null);
            }
        }

        return $this;
    }

    /**
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
            $networkInterfaceInstance->setLab($this);
        }

        return $this;
    }

    public function removeNetworkInterfaceInstance(NetworkInterfaceInstance $networkInterfaceInstance): self
    {
        if ($this->networkInterfaceInstances->contains($networkInterfaceInstance)) {
            $this->networkInterfaceInstances->removeElement($networkInterfaceInstance);
            // set the owning side to null (unless already changed)
            if ($networkInterfaceInstance->getLab() === $this) {
                $networkInterfaceInstance->setLab(null);
            }
        }

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
