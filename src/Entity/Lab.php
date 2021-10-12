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
     * @Serializer\Groups({"api_get_lab", "api_get_device", "api_get_lab_instance", "api_groups", "api_get_group","api_addlab"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\Groups({"api_get_lab", "export_lab", "worker","api_addlab"})
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
     * @ORM\ManyToMany(targetEntity="App\Entity\Device", inversedBy="labs")
     * @ORM\JoinTable(name="lab_device",
     *      joinColumns={@ORM\JoinColumn(name="lab_id", referencedColumnName="id", onDelete="CASCADE")},
     *      inverseJoinColumns={@ORM\JoinColumn(name="device_id", referencedColumnName="id", onDelete="CASCADE")}
     * ))
     * @Serializer\Groups({"api_get_lab", "export_lab"})
     */
    private $devices;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="createdLabs")
     * @Serializer\Groups({"api_get_lab"})
     */
    private $author;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\Groups({"api_get_lab", "worker","api_get_lab_instance"})
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
     * @ORM\ManyToOne(targetEntity=Group::class, inversedBy="labs")
     */
    private $_group;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Serializer\Exclude
     */
    private $banner;

    public function __construct()
    {
        $this->devices = new ArrayCollection();
        $this->connexions = new ArrayCollection();
        $this->activities = new ArrayCollection();
        $this->uuid = (string) new Uuid();
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

    public function getGroup(): ?Group
    {
        return $this->_group;
    }

    public function setGroup(?Group $_group): self
    {
        $this->_group = $_group;

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
}
