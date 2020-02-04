<?php

namespace App\Entity;

use UnexpectedValueException;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
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
     * @Serializer\Groups({"primary_key", "group_explore"})
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
     * @ORM\OneToMany(targetEntity="App\Entity\LabInstance", mappedBy="activity")
     */
    private $labInstances;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="createdActivities")
     */
    private $author;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $lastUpdated;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\User")
     */
    private $authorizedUsers;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Group", inversedBy="activities")
     * @ORM\JoinColumn(nullable=false)
     */
    private $_group;

    const VPN_ACCESS = "vpn";
    const HTTP_ACCESS = "http";

    public function __construct()
    {
        $this->labInstances = new ArrayCollection();
        $this->authorizedUsers = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->lastUpdated = new \DateTime();
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

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;

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

    public function getLastUpdated(): ?\DateTimeInterface
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(?\DateTimeInterface $lastUpdated): self
    {
        $this->lastUpdated = $lastUpdated;

        return $this;
    }

    /**
     * A collection of user-by-user authorizations.
     * 
     * @return Collection|User[]
     */
    public function getAuthorizedUsers(): Collection
    {
        return $this->authorizedUsers;
    }

    public function addAuthorizedUser(User $authorizedUser): self
    {
        if (!$this->authorizedUsers->contains($authorizedUser)) {
            $this->authorizedUsers[] = $authorizedUser;
        }

        return $this;
    }

    public function removeAuthorizedUser(User $authorizedUser): self
    {
        if ($this->authorizedUsers->contains($authorizedUser)) {
            $this->authorizedUsers->removeElement($authorizedUser);
        }

        return $this;
    }

    public function getGroup(): ?Group
    {
        return $this->_group;
    }

    public function setGroup(?Group $_group): self
    {
        $this->_group = $_group;

        return $this;
    }
}
