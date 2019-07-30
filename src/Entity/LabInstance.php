<?php

namespace App\Entity;

use App\Entity\Instance;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;

/**
 * @ORM\Entity(repositoryClass="App\Repository\LabInstanceRepository")
 * @Serializer\XmlRoot("instance")
 */
class LabInstance extends Instance
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"primary_key"})
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Lab", inversedBy="instances")
     * @Serializer\Groups({"lab"})
     */
    protected $lab;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="labInstances")
     * @Serializer\Groups({"user"})
     */
    protected $user;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLab(): ?Lab
    {
        return $this->lab;
    }

    public function setLab(?Lab $lab): self
    {
        $this->lab = $lab;

        return $this;
    }
    
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get device instances associated to this lab
     *
     * @return Collection|DeviceInstance[]
     */
    public function getDeviceInstances(): Collection
    {
        // $deviceInstances = $this->lab->getDeviceInstances()->filter(function(DeviceInstance $deviceInstance) {
        //     return $deviceInstance->getUser() == $this->user;
        // });

        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq("user", $this->user))
        ;

        $deviceInstances = $this->lab->getDeviceInstances()->matching($criteria);

        return $deviceInstances;
    }

    /**
     * Get network interface instances associated to this lab
     *
     * @return Collection|NetworkInterfaceInstance[]
     */
    public function getNetworkInterfaceInstances(): Collection
    {
        // $deviceInstances = $this->lab->getDeviceInstances()->filter(function(DeviceInstance $deviceInstance) {
        //     return $deviceInstance->getUser() == $this->user;
        // });

        $criteria = Criteria::create()
            ->where(Criteria::expr()->eq("user", $this->user))
        ;

        $networkInterfaceInstances = $this->lab->getNetworkInterfaceInstances()->matching($criteria);

        return $networkInterfaceInstances;
    }
}
