<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Filesystem\Filesystem;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DeviceRepository")
 */
class Device
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
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $brand;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $model;

    /**
     * @ORM\Column(type="integer")
     */
    private $launchOrder;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     *
     * @Assert\File(mimeTypes={ "text/x-shellscript", "application/x-sh" })
     */
    private $launchScript;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\NetworkInterface", mappedBy="device")
     */
    private $networkInterfaces;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\POD", inversedBy="devices")
     */
    private $pod;

    public function __construct()
    {
        $this->networkInterfaces = new ArrayCollection();
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
    public function getNetworkInterfaces(): Collection
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

    public function getPod(): ?POD
    {
        return $this->pod;
    }

    public function setPod(?POD $pod): self
    {
        $this->pod = $pod;

        return $this;
    }
}
