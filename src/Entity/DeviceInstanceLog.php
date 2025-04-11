<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

#[ORM\Entity(repositoryClass: DeviceInstanceLogRepository::class)]
class DeviceInstanceLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'text')]
    private $content;

    #[ORM\Column(type: 'datetime')]
    private $createdAt;

    #[ORM\ManyToOne(targetEntity: DeviceInstance::class, inversedBy: 'logs', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[Serializer\Exclude]
    private $deviceInstance;

    #[ORM\Column(type: 'string', length: 255)]
    private $type;

    #[ORM\Column(type: 'string', length: 255)]
    private string $scope;

    const SCOPE_PUBLIC = "public";
    const SCOPE_PRIVATE = "private";

    public function __construct() {
        $this->createdAt = new \DateTime();
        // log is private by default
        $this->scope = self::SCOPE_PRIVATE;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

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

    public function getDeviceInstance(): ?DeviceInstance
    {
        return $this->deviceInstance;
    }

    public function setDeviceInstance(?DeviceInstance $deviceInstance): self
    {
        $this->deviceInstance = $deviceInstance;

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

    public function getScope(): ?string
    {
        return $this->scope;
    }

    /**
     * Set scope for this log. Must be one of `DeviceInstanceLog::SCOPE_PUBLIC` or `DeviceInstanceLog::SCOPE_PRIVATE`.
     * @param string $scope 
     * @return \App\Entity\DeviceInstanceLog 
     */
    public function setScope(string $scope): self
    {
        $this->scope = $scope;

        return $this;
    }
}
