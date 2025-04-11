<?php

namespace App\Entity;

use App\Instance\InstanciableInterface;
use App\Utils\Uuid;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: 'App\Repository\NetworkInterfaceRepository')]
class NetworkInterface implements InstanciableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['api_get_network_interface', 'api_get_device_instance', 'api_get_device', 'api_get_lab_instance'])]
    private $id;

    #[ORM\Column(type: 'string', length: 255, options: ['default' => 'tap'])]
    #[Serializer\Groups(['api_get_network_interface', 'export_lab', 'worker', 'api_get_lab_template'])]
    private $type = 'tap';

    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['api_get_network_interface', 'api_get_device', 'export_lab', 'worker', 'api_get_lab_template'])]
    private $name;

    #[ORM\OneToOne(targetEntity: 'App\Entity\NetworkSettings', cascade: ['persist', 'remove'])]
    private $settings;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Device', inversedBy: 'networkInterfaces', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    #[Serializer\Groups(['api_get_network_interface'])]
    private $device;

    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['api_get_network_interface', 'api_get_device', 'worker'])]
    private $uuid;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Serializer\Groups(['api_get_network_interface', 'api_get_device', 'export_lab', 'worker', 'api_get_lab_template'])]
    private $vlan;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Serializer\Groups(['api_get_network_interface', 'api_get_device', 'export_lab', 'worker', 'api_get_lab_template'])]
    private $connection;

     #[Assert\Choice(['Straight', 'Bezier', 'Flowchart'])]
    #[ORM\Column(type: 'string', nullable: true, options: ['default' => null])]
    #[Serializer\Groups(['api_get_network_interface', 'api_get_device', 'export_lab', 'worker', 'api_get_lab_template'])]
    private $connectorType;

    #[ORM\Column(type: 'string', nullable: true, options: ['default' => null])]
    #[Serializer\Groups(['api_get_network_interface', 'api_get_device', 'export_lab', 'worker', 'api_get_lab_template'])]
    private $connectorLabel;

    #[Assert\NotNull]
    #[Assert\Type(type: 'boolean')]
    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    #[Serializer\Groups(['api_get_network_interface'])]
    private bool $isTemplate;

    const TYPE_TAP = 'tap';
    const TYPE_OVS = 'ovs';

    public function __construct()
    {
        $this->uuid = (string) new Uuid();
        $this->connection = 0;
        $this->isTemplate=false;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSettings(): ?NetworkSettings
    {
        return $this->settings;
    }

    public function setSettings(?NetworkSettings $settings): self
    {
        $this->settings = $settings;

        return $this;
    }

    #[Serializer\VirtualProperty]
    #[Serializer\Groups([])]
    public function getAccessType(): ?string
    {
        return $this->settings->getProtocol();
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

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(?string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getVlan(): ?int
    {
        return $this->vlan;
    }

    public function setVlan(?int $vlan): self
    {
        $this->vlan = $vlan;

        return $this;
    }

    public function getConnection(): ?int
    {
        return $this->connection;
    }

    public function setConnection(?int $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    public function getConnectorType(): ?string
    {
        return $this->connectorType;
    }

    public function setConnectorType(?string $connectorType): self
    {
        $this->connectorType = $connectorType;

        return $this;
    }

    public function getConnectorLabel(): ?string
    {
        return $this->connectorLabel;
    }

    public function setConnectorLabel(?string $connectorLabel): self
    {
        $this->connectorLabel = $connectorLabel;

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
