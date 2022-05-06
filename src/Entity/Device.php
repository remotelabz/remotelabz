<?php

namespace App\Entity;

use App\Utils\Uuid;
use App\Entity\OperatingSystem;
use App\Entity\Hypervisor;
use Doctrine\ORM\Mapping as ORM;
use App\Instance\InstanciableInterface;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
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
     * @Serializer\Groups({"api_get_device", "api_get_lab", "api_get_network_interface", "api_get_device_instance","sandbox"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\Groups({"api_get_device", "export_lab", "worker","sandbox","api_get_device_instance","api_get_lab_instance"})
     * @Assert\NotBlank
     * @Assert\Type(type="string")
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Serializer\Groups({"api_get_device", "export_lab"})
     * @Assert\Type(type="string")
     */
    private $brand;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Serializer\Groups({"api_get_device", "export_lab"})
     * @Assert\Type(type="string")
     */
    private $model;

    /**
     * @ORM\Column(type="integer")
     * @Serializer\Groups({})
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
     * @ORM\OneToMany(targetEntity="App\Entity\NetworkInterface", mappedBy="device", cascade={"persist"})
     * @Serializer\Groups({"api_get_device", "export_lab"})
     */
    private $networkInterfaces;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Lab", mappedBy="devices")
     * @Serializer\Groups({"api_get_device"})
     */
    private $labs;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\Groups({"api_get_device", "export_lab", "worker"})
     * @Assert\NotNull
     * @Assert\Choice({"vm","container"})
     */
    private $type;

    /**
     * @ORM\Column(type="integer")
     * @Serializer\Groups({"api_get_device", "export_lab", "worker"})
     */
    private $virtuality;

    /**
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Hypervisor")
     * @Serializer\Groups({"api_get_device", "api_get_device_instance", "api_get_lab_instance", "export_lab", "worker","sandbox"})
     * @Assert\NotNull
     * @Assert\Valid
     */
    private $hypervisor;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\OperatingSystem")
     * @Serializer\Groups({"api_get_device", "export_lab", "api_get_lab_instance", "worker"})
     * @Assert\NotNull
     * @Assert\Valid
     */
    private $operatingSystem;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\NetworkInterface", cascade={"persist", "remove"})
     * @Serializer\Groups({})
     */
    private $controlInterface;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Flavor")
     * @Serializer\Groups({"api_get_device", "export_lab", "worker"})
     * @Assert\NotNull
     * @Assert\Valid
     */
    private $flavor;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\Groups({"api_get_device", "worker"})
     */
    private $uuid;

    /**
     * @ORM\Column(type="datetime")
     * @Serializer\Groups({"api_get_device"})
     * @Assert\Type(type="\DateTime")
     */
    private $createdAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Serializer\Groups({"api_get_device"})
     */
    private $lastUpdated;

    /**
     * @ORM\Column(type="boolean", options={"default": 1})
     * @Serializer\Groups({"api_get_device", "export_lab", "worker"})
     */
    private $vnc;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\EditorData", cascade={"persist"})
     * @ORM\JoinColumn(name="editor_data_id", referencedColumnName="id", onDelete="CASCADE")
     * @Serializer\Groups({"api_get_device", "export_lab"})
     */
    private $editorData;

    /**
     * @ORM\Column(type="boolean", options={"default": 0})
     * @Serializer\Groups({"api_get_device"})
     * @Assert\NotNull
     * @Assert\Type(type="boolean")
     */
    private $isTemplate;

    public function __construct()
    {
        $this->networkInterfaces = new ArrayCollection();
        $this->uuid = (string) new Uuid();
        $this->createdAt = new \DateTime();
        $this->labs = new ArrayCollection();
        $this->editorData = new EditorData();
        $this->editorData->setDevice($this);
        /*$this->type = 'vm';
        $this->hypervisor = 'qemu';*/
        $this->launchOrder = 0;
        $this->virtuality = 1;
        $this->vnc = true;
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

    public function getHypervisor(): ?Hypervisor
    {
        return $this->hypervisor;
    }

    public function setHypervisor(?Hypervisor $hypervisor): self
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

    public function getVnc(): bool
    {
        return $this->vnc;
    }

    public function setVnc(bool $vnc): self
    {
        $this->vnc = $vnc;

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
