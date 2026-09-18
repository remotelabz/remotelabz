<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\IpReputationRepository')]
class IpReputation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 45, unique: true)]
    private $ip;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $abuse_score;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $total_reports;

    #[ORM\Column(type: 'string', length: 2, nullable: true)]
    private $country_code;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $last_checked_at;

    #[ORM\Column(type: 'datetime')]
    private $created_at;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(string $ip): self
    {
        $this->ip = $ip;
        return $this;
    }

    public function getAbuseScore(): ?int
    {
        return $this->abuse_score;
    }

    public function setAbuseScore(?int $abuse_score): self
    {
        $this->abuse_score = $abuse_score;
        return $this;
    }

    public function getTotalReports(): ?int
    {
        return $this->total_reports;
    }

    public function setTotalReports(?int $total_reports): self
    {
        $this->total_reports = $total_reports;
        return $this;
    }

    public function getCountryCode(): ?string
    {
        return $this->country_code;
    }

    public function setCountryCode(?string $country_code): self
    {
        $this->country_code = $country_code;
        return $this;
    }

    public function getLastCheckedAt(): ?\DateTimeInterface
    {
        return $this->last_checked_at;
    }

    public function setLastCheckedAt(?\DateTimeInterface $last_checked_at): self
    {
        $this->last_checked_at = $last_checked_at;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }
}
