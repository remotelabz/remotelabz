<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: 'App\Repository\NetworkSettingsRepository')]
class NetworkSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['primary_key'])]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\XmlAttribute]
    #[Serializer\Groups(['lab', 'details', 'start_lab', 'stop_lab'])]
    private $name;

    #[Assert\Ip(version: 4)]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Serializer\XmlAttribute]
    #[Serializer\Groups(['lab', 'start_lab', 'stop_lab'])]
    private $ip;

    #[Assert\Ip(version: 6)]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Serializer\XmlAttribute]
    #[Serializer\Groups(['lab', 'start_lab', 'stop_lab'])]
    private $ipv6;

    #[Assert\Ip]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Serializer\XmlAttribute]
    #[Serializer\Groups(['lab', 'start_lab', 'stop_lab'])]
    private $gateway;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Serializer\XmlAttribute]
    #[Serializer\Groups(['network_interfaces', 'lab', 'start_lab', 'stop_lab'])]
    private $protocol;

    #[Assert\Range(min: 0, max: 65536)]
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Serializer\XmlAttribute]
    #[Serializer\Groups(['lab'])]
    private $port;

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

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getIpv6(): ?string
    {
        return $this->ipv6;
    }

    public function setIpv6(?string $ipv6): self
    {
        $this->ipv6 = $ipv6;

        return $this;
    }

    public function getPrefix4(): ?int
    {
        return $this->prefix4;
    }

    public function setPrefix4(?int $prefix4): self
    {
        $this->prefix4 = $prefix4;

        return $this;
    }

    public function getPrefix6(): ?int
    {
        return $this->prefix6;
    }

    public function setPrefix6(?int $prefix6): self
    {
        $this->prefix6 = $prefix6;

        return $this;
    }

    public function getGateway(): ?string
    {
        return $this->gateway;
    }

    public function setGateway(?string $gateway): self
    {
        $this->gateway = $gateway;

        return $this;
    }

    public function getProtocol(): ?string
    {
        return $this->protocol;
    }

    public function setProtocol(?string $protocol): self
    {
        $this->protocol = $protocol;

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
}
