<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ActivityRepository")
 */
class Activity
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
     * @ORM\Column(type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Lab", inversedBy="activities")
     * @ORM\JoinColumn(nullable=true)
     */
    private $lab;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Course", inversedBy="activities")
     */
    private $courses;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Network", cascade={"persist", "remove"})
     */
    private $network;

    /**
     * @ORM\Column(type="boolean")
     */
    private $InternetAllowed = false;
    

    /**
     * @ORM\Column(type="boolean")
     */
    private $Interconnected = false;

    /**
     * @ORM\Column(type="boolean")
     */
    private $UsedAlone = true;

    /**
     * @ORM\Column(type="boolean")
     */
    private $UsedInGroup = false;

    /**
     * @ORM\Column(type="boolean")
     */
    private $UsedTogetherInCourse = false;
  

    const VPN_ACCESS = "vpn";
    const HTTP_ACCESS = "http";

    public function __construct()
    {
        $this->courses = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
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

    public function getAccessType(): ?string
    {
        return $this->accessType;
    }

    public function setAccessType(string $accessType): self
    {
        $this->accessType = $accessType;

        return $this;
    }

    /**
     * @return Collection|Course[]
     */
    public function getCourses(): Collection
    {
        return $this->courses;
    }

    public function addCourse(Course $course): self
    {
        if (!$this->courses->contains($course)) {
            $this->courses[] = $course;
        }

        return $this;
    }

    public function removeCourse(Course $course): self
    {
        if ($this->courses->contains($course)) {
            $this->courses->removeElement($course);
        }

        return $this;
    }

    public function getNetwork(): ?Network
    {
        return $this->network;
    }

    public function setNetwork(?Network $network): self
    {
        $this->network = $network;

        return $this;
    }

    public function getInternetAllowed(): ?bool
    {
        return $this->InternetAllowed;
    }

    public function setInternetAllowed(bool $InternetAllowed): self
    {
        $this->InternetAllowed = $InternetAllowed;

        return $this;
    }

    public function getInterconnected(): ?bool
    {
        return $this->Interconnected;
    }

    public function setInterconnected(bool $Interconnected): self
    {
        $this->Interconnected = $Interconnected;

        return $this;
    }

    public function getUsedAlone(): ?bool
    {
        return $this->UsedAlone;
    }

    public function setUsedAlone(bool $UsedAlone): self
    {
        $this->UsedAlone = $UsedAlone;

        return $this;
    }

    public function getUsedInGroup(): ?bool
    {
        return $this->UsedInGroup;
    }

    public function setUsedInGroup(bool $UsedInGroup): self
    {
        $this->UsedInGroup = $UsedInGroup;

        return $this;
    }

    public function getUsedTogetherInCourse(): ?bool
    {
        return $this->UsedTogetherInCourse;
    }

    public function setUsedTogetherInCourse(bool $UsedTogetherInCourse): self
    {
        $this->UsedTogetherInCourse = $UsedTogetherInCourse;

        return $this;
    }

    
}
