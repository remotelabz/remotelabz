<?php

namespace App\Entity;

use App\Utils\Uuid;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use App\Instance\InstanciableInterface;
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
     * @Serializer\Groups({"api_get_lab", "api_get_device", "api_get_lab_instance", "api_groups", "api_get_group","api_addlab","sandbox"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\Groups({"api_get_lab", "export_lab", "worker","api_addlab","sandbox"})
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Serializer\Groups({"api_get_lab", "export_lab"})
     */
    private $shortDescription;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Serializer\Groups({"api_get_lab", "export_lab"})
     */
    private $description;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Serializer\Groups({"api_get_lab", "export_lab"})
     */
    private $tasks;

    /**
     * @ORM\Column(type="string", options={"default": "1"},  nullable=true)
     * @Serializer\Groups({"api_get_lab", "export_lab"})
     */
    private $version;

    /**
     * @ORM\Column(type="integer", options={"default": 300},  nullable=true)
     * @Serializer\Groups({"api_get_lab", "export_lab"})
     */
    private $scripttimeout;

    /**
     * @ORM\Column(type="integer", options={"default": 0})
     * @Serializer\Groups({"api_get_lab", "export_lab"})
     */
    private $lock;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Device", inversedBy="labs",cascade={"persist"})
     * @ORM\JoinTable(name="lab_device",
     *      joinColumns={@ORM\JoinColumn(name="lab_id", referencedColumnName="id", onDelete="CASCADE")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="device_id", referencedColumnName="id", onDelete="CASCADE")}
     * ))
     * @Serializer\Groups({"api_get_lab", "export_lab","sandbox"})
     */
    private $devices;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="createdLabs")
     * @Serializer\Groups({"api_get_lab"})
     */
    private $author;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\Groups({"api_get_lab", "worker","api_get_lab_instance","sandbox"})
     */
    private $uuid;

    /**
     * @ORM\Column(type="datetime")
     * @Serializer\Groups({"api_get_lab"})
     */
    private $createdAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Serializer\Groups({"api_get_lab"})
     */
    private $lastUpdated;

    /**
     * @ORM\Column(type="boolean")
     * @Serializer\Groups({})
     */
    private $isInternetAuthorized = false;

    /**
     * @ORM\ManyToMany(targetEntity=Group::class, inversedBy="labs")
     */
    private $groups;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Serializer\Exclude
     */
    private $banner;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\TextObject", mappedBy="lab")
     * @ORM\JoinColumn(nullable=true)
     *
     * @var Collection|TextObject[]
     */
    private $textobjects;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\NetworkDevice", mappedBy="lab")
     * @ORM\JoinColumn(nullable=true)
     *
     * @var Collection|NetworkDevice[]
     */
    private $networks;

    public function __construct()
    {
        $this->devices = new ArrayCollection();
        $this->connexions = new ArrayCollection();
        $this->activities = new ArrayCollection();
        $this->groups = new ArrayCollection();
        $this->uuid = (string) new Uuid();
        $this->createdAt = new \DateTime();
        $this->lastUpdated = new \DateTime();
        $this->textobjects = new ArrayCollection();
        $this->networks = new ArrayCollection();
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

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): self
    {
        $this->shortDescription = $shortDescription;

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

    public function getTasks(): ?string
    {
        return $this->tasks;
    }

    public function setTasks(?string $tasks): self
    {
        $this->tasks = $tasks;

        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getScripttimeout(): ?int
    {
        return $this->scripttimeout;
    }

    public function setScripttimeout(?int $scripttimeout): self
    {
        $this->scripttimeout = $scripttimeout;

        return $this;
    }

    public function getLock(): ?int
    {
        return $this->lock;
    }

    public function setLock(?int $lock): self
    {
        $this->lock = $lock;

        return $this;
    }

    /**y
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
            $device->addLab($this);
        }

        return $this;
    }

    public function removeDevice(Device $device): self
    {
        if ($this->devices->contains($device)) {
            $this->devices->removeElement($device);
            $device->removeLab($this);
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

    public function isInternetAuthorized(): bool
    {
        return $this->isInternetAuthorized;
    }

    public function setIsInternetAuthorized(bool $isInternetAuthorized): self
    {
        $this->isInternetAuthorized = $isInternetAuthorized;

        return $this;
    }

    /*public function getGroup(): ?Group
    {
        return $this->_group;
    }

    public function setGroup(?Group $_group): self
    {
        $this->_group = $_group;

        return $this;
    }*/

    /**
     * @return Collection|Group[]
     */
    public function getGroups(): Collection
    {
        return $this->groups;
    }

    public function addGroup(Group $group): self
    {
        if (!$this->groups->contains($group)) {
            $this->groups[] = $group;
            $group->addLab($this);
        }
        return $this;
    }

    public function removeGroup(Group $group): self
    {
        if ($this->groups->contains($group)) {
            $this->groups->removeElement($group);
        }

        return $this;
    }

    public function getBanner(): ?string
    {
        return $this->banner;
    }

    public function setBanner(?string $banner): self
    {
        $this->banner = $banner;

        return $this;
    }

     /**
     * @return Collection|TextObject[]
     */
    public function getTextobjects()
    {
        return $this->textobjects;
    }

    public function addTextobject(Lab $textobject): self
    {
        if (!$this->textobjects->contains($textobject)) {
            $this->textobjects[] = $textobject;
            $textobject->setLab($this);
        }

        return $this;
    }

    public function removeTextobject(Lab $textobject): self
    {
        if ($this->textobjects->contains($textobject)) {
            $this->textobjects->removeElement($textobject);
            // set the owning side to null (unless already changed)
            if ($textobject->getLab() === $this) {
                $textobject->setLab(null);
            }
        }

        return $this;
    }

     /**
     * @return Collection|NetworkDevice[]
     */
    public function getNetworks()
    {
        return $this->networks;
    }

    public function addNetwork(NetworkDevice $network): self
    {
        if (!$this->networks->contains($network)) {
            $this->networks[] = $network;
            $network->setLab($this);
        }

        return $this;
    }

    public function removeNetwork(NetworkDevice $network): self
    {
        if ($this->networks->contains($network)) {
            $this->networks->removeElement($network);
            // set the owning side to null (unless already changed)
            if ($network->getLab() === $this) {
                $network->setLab(null);
            }
        }

        return $this;
    }
}
