<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ConnexionRepository")
 */
class Connexion
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
    private $name;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\POD")
     */
    private $pod;

    /**
     * @ORM\Column(type="integer")
     */
    private $vlan1;

    /**
     * @ORM\Column(type="integer")
     */
    private $vlan2;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\NetworkInterface", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     * @Assert\NotIdenticalTo(propertyPath="networkInterface2")
     */
    private $networkInterface1;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\NetworkInterface", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     * @Assert\NotIdenticalTo(propertyPath="networkInterface1")
     */
    private $networkInterface2;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Lab", mappedBy="connexions")
     */
    private $labs;

    public function __construct()
    {
        $this->labs = new ArrayCollection();
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

    public function getPod(): ?POD
    {
        return $this->pod;
    }

    public function setPod(?POD $pod): self
    {
        $this->pod = $pod;

        return $this;
    }

    public function getVlan1(): ?int
    {
        return $this->vlan1;
    }

    public function setVlan1(int $vlan1): self
    {
        $this->vlan1 = $vlan1;

        return $this;
    }

    public function getVlan2(): ?int
    {
        return $this->vlan2;
    }

    public function setVlan2(int $vlan2): self
    {
        $this->vlan2 = $vlan2;

        return $this;
    }

    public function getNetworkInterface1(): ?NetworkInterface
    {
        return $this->networkInterface1;
    }

    public function setNetworkInterface1(NetworkInterface $networkInterface1): self
    {
        $this->networkInterface1 = $networkInterface1;

        return $this;
    }

    public function getNetworkInterface2(): ?NetworkInterface
    {
        return $this->networkInterface2;
    }

    public function setNetworkInterface2(NetworkInterface $networkInterface2): self
    {
        $this->networkInterface2 = $networkInterface2;

        return $this;
    }

    /**
     * @return Collection|Lab[]
     */
    public function getLabs(): Collection
    {
        return $this->labs;
    }

    public function addLab(Lab $lab): self
    {
        if (!$this->labs->contains($lab)) {
            $this->labs[] = $lab;
            $lab->addConnexion($this);
        }

        return $this;
    }

    public function removeLab(Lab $lab): self
    {
        if ($this->labs->contains($lab)) {
            $this->labs->removeElement($lab);
            $lab->removeConnexion($this);
        }

        return $this;
    }
}
