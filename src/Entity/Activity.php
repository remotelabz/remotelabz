<?php

namespace App\Entity;

use UnexpectedValueException;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

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
    private $internetAllowed = false;
    

    /**
     * @ORM\Column(type="boolean")
     */
    private $interconnected = false;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\Choice({"alone", "group", "course"})
     */
    private $scope;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LabInstance", mappedBy="activity")
     */
    private $labInstances;

    const VPN_ACCESS = "vpn";
    const HTTP_ACCESS = "http";
    const SCOPE_SINGLE_USER = 'alone';
    const SCOPE_GROUP = 'group';
    const SCOPE_COURSE = 'course';

    public function __construct()
    {
        $this->courses = new ArrayCollection();
        $this->labInstances = new ArrayCollection();
        $this->scope = 'alone';
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
        return $this->internetAllowed;
    }

    public function setInternetAllowed(bool $internetAllowed): self
    {
        $this->internetAllowed = $internetAllowed;

        return $this;
    }

    public function getinterconnected(): ?bool
    {
        return $this->interconnected;
    }

    public function setInterconnected(bool $interconnected): self
    {
        $this->interconnected = $interconnected;

        return $this;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function setScope(string $scope): self
    {
        if (in_array($scope, ['alone', 'group', 'course'])) {
            $this->scope = $scope;
        } else {
            throw new UnexpectedValueException("'" . $scope . "' is not a correct value for Activity::scope. Must be one of 'alone', 'group' or 'course'.");
        }

        return $this;
    }

    /**
     * @return Collection|LabInstance[]
     */
    public function getLabInstances(): Collection
    {
        return $this->labInstances;
    }

    public function addLabInstance(LabInstance $labInstance): self
    {
        if (!$this->labInstances->contains($labInstance)) {
            $this->labInstances[] = $labInstance;
            $labInstance->setActivity($this);
        }

        return $this;
    }

    public function removeLabInstance(LabInstance $labInstance): self
    {
        if ($this->labInstances->contains($labInstance)) {
            $this->labInstances->removeElement($labInstance);
            // set the owning side to null (unless already changed)
            if ($labInstance->getActivity() === $this) {
                $labInstance->setActivity(null);
            }
        }

        return $this;
    }

    
}
