<?php

namespace App\Entity;

use App\Utils\Uuid;
use App\Entity\OperatingSystem;
use App\Entity\Hypervisor;
use App\Entity\PduOutletDevice;
use App\Entity\ControlProtocolType;
use Doctrine\ORM\Mapping as ORM;
use App\Instance\InstanciableInterface;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: 'App\Repository\DeviceRepository')]
class Device implements InstanciableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['api_get_device', 'api_get_lab', 'api_get_network_interface', 'api_get_device_instance', 'sandbox', 'api_get_lab_instance', 'api_get_lab_template'])]
    private $id;

    #[Assert\NotBlank]
    #[Assert\Type(type: 'string')]
    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['api_get_device', 'api_get_lab_template', 'export_lab', 'worker', 'sandbox', 'api_get_device_instance', 'api_get_lab_instance'])]
    private $name;

    #[Assert\Type(type: 'string')]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Serializer\Groups(['api_get_device', 'export_lab', 'api_get_lab_template', 'worker'])]
    private $brand;

    #[Assert\Type(type: 'string')]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Serializer\Groups(['api_get_device', 'export_lab', 'api_get_lab_template', 'worker'])]
    private $model;

    #[Assert\Type(type: 'integer')]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups([])]
    private int $launchOrder;

    #[Assert\File(mimeTypes: ['text/x-shellscript', 'application/x-sh'])]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $launchScript;

    #[ORM\OneToMany(targetEntity: 'App\Entity\NetworkInterface', mappedBy: 'device', cascade: ['persist', 'remove'])]
    #[Serializer\Groups(['api_get_device', 'export_lab', 'api_get_lab_template'])]
    private $networkInterfaces;

    #[Assert\NotNull]
    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'eth'])]
    #[Serializer\Groups(['api_get_device', 'export_lab', 'api_get_lab_template'])]
    private $networkInterfaceTemplate = "eth";

    #[ORM\ManyToMany(targetEntity: 'App\Entity\Lab', mappedBy: 'devices')]
    #[Serializer\Groups(['api_get_device'])]
    private $labs;

    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['api_get_device', 'export_lab', 'worker', 'api_get_lab_instance', 'api_get_lab_template'])]
    private $type;

    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['api_get_device', 'export_lab', 'worker', 'api_get_lab_template'])]
    private $nbCpu;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Serializer\Groups(['api_get_device', 'export_lab', 'worker', 'api_get_lab_template'])]
    private $nbCore;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Serializer\Groups(['api_get_device', 'export_lab', 'worker', 'api_get_lab_template'])]
    private $nbSocket;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Serializer\Groups(['api_get_device', 'export_lab', 'worker', 'api_get_lab_template'])]
    private $nbThread;

    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['api_get_device', 'export_lab', 'worker', 'api_get_lab_template', 'api_get_lab_instance', 'sandbox'])]
    private int $virtuality;

     #[Assert\Ip(version: 4)]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Serializer\Groups(['api_get_device', 'export_lab', 'worker', 'api_get_lab_template'])]
    private $ip;

    #[Assert\Range(min: 0, max: 65536)]
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Serializer\XmlAttribute]
    #[Serializer\Groups(['api_get_device', 'export_lab', 'worker', 'api_get_lab_template'])]
    private $port;

    #[ORM\OneToOne(targetEntity: 'App\Entity\PduOutletDevice', mappedBy: 'device')]
    #[Serializer\Groups(['worker', 'api_get_device'])]
    private $outlet;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Hypervisor')]
    #[Serializer\Groups(['api_get_device', 'api_get_device_instance', 'api_get_lab_instance', 'export_lab', 'worker', 'sandbox', 'api_get_lab_template'])]
    private $hypervisor;

    #[Assert\NotNull]
    #[Assert\Valid]
    #[ORM\ManyToOne(targetEntity: 'App\Entity\OperatingSystem')]
    #[Serializer\Groups(['api_get_device', 'export_lab', 'api_get_lab_instance', 'worker', 'api_get_lab_template'])]
    private $operatingSystem;

    #[ORM\OneToOne(targetEntity: 'App\Entity\NetworkInterface', cascade: ['persist', 'remove'])]
    private $controlInterface;

    #[Assert\NotNull]
    #[Assert\Valid]
    #[ORM\ManyToOne(targetEntity: 'App\Entity\Flavor')]
    #[Serializer\Groups(['api_get_device', 'export_lab', 'worker', 'api_get_lab_template'])]
    private $flavor;

    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['api_get_device', 'worker', 'api_get_lab_template'])]
    private $uuid;

    #[Assert\Type(type: '\DateTime')]
    #[ORM\Column(type: 'datetime')]
    #[Serializer\Groups(['api_get_device'])]
    private $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Serializer\Groups(['api_get_device'])]
    private $lastUpdated;

    #[Assert\NotNull]
    #[Assert\Valid]
    #[ORM\ManyToMany(targetEntity: 'App\Entity\ControlProtocolType', mappedBy: 'devices', cascade: ['persist'])]
    #[Serializer\Groups(['api_get_device', 'export_lab', 'worker', 'sandbox', 'api_get_lab_template'])]
    private $controlProtocolTypes;

    #[ORM\OneToOne(targetEntity: 'App\Entity\EditorData', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'editor_data_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Serializer\Groups(['api_get_device', 'export_lab', 'api_get_lab_template'])]
    private $editorData;

    #[Assert\NotNull]
    #[Assert\Type(type: 'boolean')]
    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    #[Serializer\Groups(['api_get_device'])]
    private $isTemplate;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Serializer\Groups(['api_get_device'])]
    private $delay = 0;


    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Serializer\Groups(['api_get_device', 'api_get_lab_template', 'export_lab'])]
    private $icon = "Server_Linux.png";

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Serializer\Groups(['api_get_device', 'api_get_lab_template', 'export_lab'])]
    private $template;


    #[ORM\Column(type: 'integer', nullable: true)]
    #[Serializer\Groups(['api_get_device'])]
    private $count;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Serializer\Groups(['api_get_device'])]
    private $postfix;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Serializer\Groups(['api_get_device'])]
    private $config = 0;

    #[ORM\Column(type: 'string', options: ['default' => ''])]
    #[Serializer\Groups(['api_get_device'])]
    private $config_data = "";

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Serializer\Groups(['api_get_device'])]
    private $status = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    #[Serializer\Groups(['api_get_device'])]
    private $ethernet = 1;

     #[ORM\ManyToOne(targetEntity: 'App\Entity\User', inversedBy: 'createdDevices')]
    #[Serializer\Groups(['api_get_device'])]
    private $author;

    public function __construct()
    {
        $this->networkInterfaces = new ArrayCollection();
        $this->uuid = (string) new Uuid();
        $this->createdAt = new \DateTime();
        $this->labs = new ArrayCollection();
        $this->controlProtocolTypes = new ArrayCollection();
        $this->editorData = new EditorData();
        $this->editorData->setDevice($this);
        /*$this->type = 'vm';
        $this->hypervisor = 'qemu';*/
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

    public function getNetworkInterfaceTemplate(): ?string
    {
        return $this->networkInterfaceTemplate;
    }

    public function setNetworkInterfaceTemplate(string $networkInterfaceTemplate): self
    {
        $this->networkInterfaceTemplate = $networkInterfaceTemplate;

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

    /**
     * @return Collection|controlProtocolType[]
     */
    public function getControlProtocolTypes()
    {
        return $this->controlProtocolTypes;
    }

    public function addControlProtocolType(ControlProtocolType $controlProtocolType): self
    {
        if (!$this->controlProtocolTypes->contains($controlProtocolType)) {
            $this->controlProtocolTypes[] = $controlProtocolType;
            $controlProtocolType->addDevice($this);
        }
        return $this;
    }

    public function removeControlProtocolType(ControlProtocolType $controlProtocolType): self
    {
        if ($this->controlProtocolTypes->contains($controlProtocolType)) {
            $this->controlProtocolTypes->removeElement($controlProtocolType);
            $controlProtocolType->removeDevice($this);
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

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function setPort(?int $port): self
    {
        $this->port = $port;

        return $this;
    }

    public function getOutlet(): ?PduOutletDevice
    {
        return $this->outlet;
    }

    public function setOutlet(?PduOutletDevice $outlet): self
    {
        if ($outlet == null) {
            if ($this->outlet != null) {
                $this->outlet->setDevice(null);
            }
        }
        else {
            $outlet->setDevice($this);
        }
        $this->outlet = $outlet;

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

    public function getDelay(): ?int
    {
        return $this->delay;
    }

    public function setDelay(int $delay): self
    {
        $this->delay = $delay;

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

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function setTemplate(string $template): self
    {
        $this->template = $template;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    public function getPostfix(): ?int
    {
        return $this->postfix;
    }

    public function setPostfix(int $postfix): self
    {
        $this->postfix = $postfix;

        return $this;
    }

    public function getConfig(): ?int
    {
        return $this->config;
    }

    public function setConfig(int $config): self
    {
        $this->config = $config;

        return $this;
    }

    public function getConfigData(): ?string
    {
        return $this->config_data;
    }

    public function setConfigData(string $config_data): self
    {
        $this->config_data = $config_data;

        return $this;
    }

    public function getStatus(): ?int
    {
        return $this->status;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getEthernet(): ?int
    {
        return $this->ethernet;
    }

    public function setEthernet(int $ethernet): self
    {
        $this->ethernet = $ethernet;

        return $this;
    }
}
