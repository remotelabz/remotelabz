<?php

namespace App\Entity;

use App\Utils\Uuid;
use Doctrine\ORM\Mapping as ORM;
use App\Instance\InstanciableInterface;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Filesystem\Filesystem;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DeviceRepository")
 */
class Device implements InstanciableInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"primary_key", "device"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"device", "network_interfaces", "lab", "start_lab", "stop_lab", "instance_manager"})
     * @Assert\NotBlank
     * @Assert\Type(type="string")
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"device", "lab"})
     * @Assert\Type(type="string")
     */
    private $brand;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"device", "lab"})
     * @Assert\Type(type="string")
     */
    private $model;

    /**
     * @ORM\Column(type="integer")
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"lab"})
     * @Assert\Type(type="integer")
     */
    private $launchOrder;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     *
     * @Assert\File(mimeTypes={ "text/x-shellscript", "application/x-sh" })
     */
    private $launchScript;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\NetworkInterface", mappedBy="device", cascade={"persist", "remove"})
     * @Serializer\XmlList(inline=true, entry="network_interface")
     * @Serializer\Groups({"device", "lab"})
     */
    private $networkInterfaces;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Lab", mappedBy="devices")
     * @Serializer\Groups({"details"})
     */
    private $labs;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"device", "lab", "start_lab", "stop_lab"})
     * @Assert\NotNull
     * @Assert\Choice({"vm"})
     */
    private $type;

    /**
     * @ORM\Column(type="integer")
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"device", "lab", "start_lab", "stop_lab"})
     */
    private $virtuality;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"device", "lab", "start_lab", "stop_lab"})
     */
    private $hypervisor;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\OperatingSystem")
     * @Serializer\XmlList(entry="operating_system")
     * @Serializer\Groups({"device", "lab", "start_lab", "stop_lab"})
     * @Assert\NotNull
     * @Assert\Valid
     */
    private $operatingSystem;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\NetworkInterface", cascade={"persist", "remove"})
     * @Serializer\XmlList(inline=true, entry="control_interface")
     * @Serializer\Groups({"lab"})
     */
    private $controlInterface;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Flavor")
     * @Serializer\XmlList(entry="flavor")
     * @Serializer\Groups({"device", "lab", "start_lab", "stop_lab"})
     * @Assert\NotNull
     * @Assert\Valid
     */
    private $flavor;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\DeviceInstance", mappedBy="device", cascade={"persist", "remove"})
     * @Serializer\XmlList(inline=true, entry="instance")
     * @Serializer\Groups({"lab"})
     */
    private $instances;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"lab", "start_lab", "stop_lab", "instance_manager"})
     */
    private $uuid;

    /**
     * @ORM\Column(type="datetime")
     * @Serializer\Groups({"lab"})
     * @Assert\Type(type="\DateTime")
     */
    private $createdAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Serializer\Groups({"lab"})
     */
    private $lastUpdated;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\EditorData", cascade={"persist", "remove"})
     * @ORM\JoinColumn(name="editor_data_id", referencedColumnName="id", onDelete="SET NULL")
     * @Serializer\Groups({"device", "editor"})
     */
    private $editorData;

    /**
     * @ORM\Column(type="boolean")
     * @Serializer\Groups({"lab"})
     * @Assert\NotNull
     * @Assert\Type(type="boolean")
     */
    private $isTemplate;

    public function __construct()
    {
        $this->networkInterfaces = new ArrayCollection();
        $this->instances = new ArrayCollection();
        $this->uuid = (string) new Uuid();
        $this->createdAt = new \DateTime();
        $this->labs = new ArrayCollection();
        $this->editorData = new EditorData();
        $this->editorData->setDevice($this);
        $this->type = "vm";
        $this->hypervisor = "qemu";
        $this->launchOrder = 0;
        $this->virtuality = 1;
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

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(string $brand): self
    {
        $this->brand = $brand;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function getLaunchOrder(): ?int
    {
        return $this->launchOrder;
    }

    public function setLaunchOrder(int $launchOrder): self
    {
        $this->launchOrder = $launchOrder;

        return $this;
    }

    public function getLaunchScript()
    {
        return $this->launchScript;
    }

    public function setLaunchScript($launchScript): self
    {
        $this->launchScript = $launchScript;

        return $this;
    }

    /**
     * @return Collection|NetworkInterface[]
     */
    public function getNetworkInterfaces()
    {
        return $this->networkInterfaces;
    }

    public function addNetworkInterface(NetworkInterface $networkInterface): self
    {
        if (!$this->networkInterfaces->contains($networkInterface)) {
            $this->networkInterfaces[] = $networkInterface;
            $networkInterface->setDevice($this);
        }

        return $this;
    }

    public function removeNetworkInterface(NetworkInterface $networkInterface): self
    {
        if ($this->networkInterfaces->contains($networkInterface)) {
            $this->networkInterfaces->removeElement($networkInterface);
            // set the owning side to null (unless already changed)
            if ($networkInterface->getDevice() === $this) {
                $networkInterface->setDevice(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Lab[]
     */
    public function getLabs()
    {
        return $this->labs;
    }

    public function addLab(Lab $lab): self
    {
        if (!$this->labs->contains($lab)) {
            $this->labs[] = $lab;
            $lab->addDevice($this);
        }

        return $this;
    }

    public function removeLab(Lab $lab): self
    {
        if ($this->labs->contains($lab)) {
            $this->labs->removeElement($lab);
            $lab->removeDevice($this);
        }

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getVirtuality(): ?int
    {
        return $this->virtuality;
    }

    public function setVirtuality(int $virtuality): self
    {
        $this->virtuality = $virtuality;

        return $this;
    }

    public function getHypervisor(): ?string
    {
        return $this->hypervisor;
    }

    public function setHypervisor(string $hypervisor): self
    {
        $this->hypervisor = $hypervisor;

        return $this;
    }

    public function getOperatingSystem(): ?OperatingSystem
    {
        return $this->operatingSystem;
    }

    public function setOperatingSystem(?OperatingSystem $operatingSystem): self
    {
        $this->operatingSystem = $operatingSystem;

        return $this;
    }

    public function getControlInterface(): ?NetworkInterface
    {
        return $this->controlInterface;
    }

    public function setControlInterface(?NetworkInterface $controlInterface): self
    {
        $this->controlInterface = $controlInterface;

        return $this;
    }

    public function getFlavor(): ?Flavor
    {
        return $this->flavor;
    }

    public function setFlavor(?Flavor $flavor): self
    {
        $this->flavor = $flavor;

        return $this;
    }

    /**
     * @return Collection|Instance[]
     */
    public function getInstances()
    {
        return $this->instances;
    }

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
            $instance->setDevice($this);
        }

        return $this;
    }

    public function removeInstance(Instance $instance): self
    {
        if ($this->instances->contains($instance)) {
            $this->instances->removeElement($instance);
            // set the owning side to null (unless already changed)
            if ($instance->getDevice() === $this) {
                $instance->setDevice(null);
            }
        }

        return $this;
    }

    public function setInstances(array $instances): self
    {
        $this->getInstances()->clear();
        foreach ($instances as $instance) {
            $this->addInstance($instance);
        }

        return $this;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(?string $uuid): self
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

    public function getEditorData(): ?EditorData
    {
        return $this->editorData;
    }

    public function setEditorData(?EditorData $editorData): self
    {
        $this->editorData = $editorData;

        return $this;
    }

    public function getIsTemplate(): ?bool
    {
        return $this->isTemplate;
    }

    public function setIsTemplate(bool $isTemplate): self
    {
        $this->isTemplate = $isTemplate;

        return $this;
    }
}
