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

    /**
     * @ORM\Column(type="boolean")
     */
    private $IsInternetConnected;

    /**
     * @ORM\Column(type="boolean")
     */
    private $IsInterconnected;

    /**
     * @ORM\Column(type="boolean")
     */
    private $IsUsedAlone;

    /**
     * @ORM\Column(type="boolean")
     */
    private $IsUsedInGroup;

    /**
     * @ORM\Column(type="boolean")
     */
    private $IsUsedTogetherInCourse;

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
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({"lab"})
     * @Serializer\XmlAttribute
     */
    public function getUserId(): ?string
    {
        return $this->user->getId();
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

    public function getIsInternetConnected(): ?bool
    {
        return $this->IsInternetConnected;
    }

    public function setIsInternetConnected(bool $IsInternetConnected): self
    {
        $this->IsInternetConnected = $IsInternetConnected;

        return $this;
    }

    public function getIsInterconnected(): ?bool
    {
        return $this->IsInterconnected;
    }

    public function setIsInterconnected(bool $IsInterconnected): self
    {
        $this->IsInterconnected = $IsInterconnected;

        return $this;
    }

    public function getIsUsedAlone(): ?bool
    {
        return $this->IsUsedAlone;
    }

    public function setIsUsedAlone(bool $IsUsedAlone): self
    {
        $this->IsUsedAlone = $IsUsedAlone;

        return $this;
    }

    public function getIsUsedInGroup(): ?bool
    {
        return $this->IsUsedInGroup;
    }

    public function setIsUsedInGroup(bool $IsUsedInGroup): self
    {
        $this->IsUsedInGroup = $IsUsedInGroup;

        return $this;
    }

    public function getIsUsedTogetherInCourse(): ?bool
    {
        return $this->IsUsedTogetherInCourse;
    }

    public function setIsUsedTogetherInCourse(bool $IsUsedTogetherInCourse): self
    {
        $this->IsUsedTogetherInCourse = $IsUsedTogetherInCourse;

        return $this;
    }

    
}
