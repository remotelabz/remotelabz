<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\NetworkRepository")
 */
class Network
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $cidr;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\IP", mappedBy="network")
     */
    private $ips;

    public function __construct(string $cidr = null)
    {
        $this->cidr = $cidr;
        $this->ips = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCidr(): ?string
    {
        return $this->cidr;
    }

    public function setCidr(string $cidr): self
    {
        $this->cidr = $cidr;

        return $this;
    }

    /**
     * @return Collection|IP[]
     */
    public function getIps(): Collection
    {
        return $this->ips;
    }

    public function addIp(IP $ip): self
    {
        if (!$this->ips->contains($ip)) {
            $this->ips[] = $ip;
            $ip->setNetwork($this);
        }

        return $this;
    }

    public function removeIp(IP $ip): self
    {
        if ($this->ips->contains($ip)) {
            $this->ips->removeElement($ip);
            // set the owning side to null (unless already changed)
            if ($ip->getNetwork() === $this) {
                $ip->setNetwork(null);
            }
        }

        return $this;
    }
}
