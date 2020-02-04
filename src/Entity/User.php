<?php

namespace App\Entity;

use App\Utils\Uuid;
use Doctrine\ORM\Mapping as ORM;
use emberlabs\GravatarLib\Gravatar;
use Symfony\Component\Asset\Package;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUserInterface;
use Symfony\Component\Asset\VersionStrategy\JsonManifestVersionStrategy;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 */
class User implements UserInterface, InstancierInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Serializer\Groups({"primary_key", "group_tree", "group_explore"})
     * @var int
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=180, unique=true)
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"lab", "start_lab", "stop_lab", "group_tree", "group_explore"})
     * @var string
     */
    private $email;

    /**
     * @ORM\Column(type="json")
     * @Serializer\Accessor(getter="getRoles")
     * @Serializer\XmlList(inline=false, entry="role")
     * @Serializer\Groups({"details"})
     * @var array|string[]
     */
    private $roles = [];

    /**
     * @ORM\Column(type="string")
     * @Serializer\Exclude
     * @var string The hashed password
     */
    private $password;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"lab", "start_lab", "stop_lab"})
     * @var string
     */
    private $lastName;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"lab", "start_lab", "stop_lab"})
     * @var string
     */
    private $firstName;

    /**
     * @ORM\Column(type="boolean")
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"lab", "details"})
     * @var bool
     */
    private $enabled = true;


    /**
     * @ORM\OneToMany(targetEntity="App\Entity\LabInstance", mappedBy="user")
     * @Serializer\XmlList(inline=true, entry="instance")
     * @Serializer\Groups({"instances"})
     * @var Collection|LabInstance[]
     */
    private $labInstances;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\DeviceInstance", mappedBy="user")
     * @Serializer\XmlList(inline=true, entry="instance")
     * @Serializer\Groups({"instances"})
     * @var Collection|DeviceInstance[]
     */
    private $deviceInstances;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\NetworkInterfaceInstance", mappedBy="user")
     * @Serializer\XmlList(inline=true, entry="instance")
     * @Serializer\Groups({"instances"})
     * @var Collection|NetworkInterfaceInstance[]
     */
    private $networkInterfaceInstances;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Lab", mappedBy="author")
     * @var Collection|Lab[]
     */
    private $createdLabs;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Activity", mappedBy="author")
     * @var Collection|Activity[]
     */
    private $createdActivities;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Serializer\Exclude
     * @var string
     */
    private $profilePictureFilename;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $createdAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $lastActivity; 

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\GroupUser", mappedBy="user", cascade={"persist"})
     */
    private $_groups;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $uuid;

    public function __construct()
    {
        $this->courses = new ArrayCollection();
        $this->labs = new ArrayCollection();
        $this->labInstances = new ArrayCollection();
        $this->deviceInstances = new ArrayCollection();
        $this->networkInterfaceInstances = new ArrayCollection();
        $this->createdLabs = new ArrayCollection();
        $this->createdActivities = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->ownedGroups = new ArrayCollection();
        $this->_groups = new ArrayCollection();
        $this->uuid = (string) new Uuid();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    // public function getHighestRole(): string
    // {
    //     if (in_array('ROLE_SUPER_ADMINISTRATOR', $this->roles)) return 'ROLE_ADMINISTRATOR';
    //     if (in_array('ROLE_ADMINISTRATOR', $this->roles)) return 'ROLE_ADMINISTRATOR';
    //     if (in_array('ROLE_TEACHER', $this->roles)) return 'ROLE_TEACHER';
    //     return 'ROLE_USER';
    // }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    /**
     * @see UserInterface
     */
    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getSalt()
    {
        // not needed when using the "bcrypt" algorithm in security.yaml
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"lab", "details", "start_lab", "stop_lab", "group_explore"})
     */
    public function getName(): ?string
    {
        return $this->firstName . " " . $this->lastName;
    }

    public function getEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function isEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

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
        }

        return $this;
    }

    public function removeLab(Lab $lab): self
    {
        if ($this->labs->contains($lab)) {
            $this->labs->removeElement($lab);
        }

        return $this;
    }

    /**
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({"lab", "instances", "details"})
     * @Serializer\XmlList(inline=false, entry="instances")
     */
    public function getInstances(): Collection
    {
        return new ArrayCollection(
            array_merge(
                $this->labInstances->toArray(),
                $this->deviceInstances->toArray(),
                $this->networkInterfaceInstances->toArray()
            )
        );
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
            $labInstance->setUser($this);
        }

        return $this;
    }

    public function removeLabInstance(LabInstance $labInstance): self
    {
        if ($this->labInstances->contains($labInstance)) {
            $this->labInstances->removeElement($labInstance);
            // set the owning side to null (unless already changed)
            if ($labInstance->getUser() === $this) {
                $labInstance->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|DeviceInstance[]
     */
    public function getDeviceInstances(): Collection
    {
        return $this->deviceInstances;
    }

    public function addDeviceInstance(DeviceInstance $deviceInstance): self
    {
        if (!$this->deviceInstances->contains($deviceInstance)) {
            $this->deviceInstances[] = $deviceInstance;
            $deviceInstance->setUser($this);
        }

        return $this;
    }

    public function removeDeviceInstance(DeviceInstance $deviceInstance): self
    {
        if ($this->deviceInstances->contains($deviceInstance)) {
            $this->deviceInstances->removeElement($deviceInstance);
            // set the owning side to null (unless already changed)
            if ($deviceInstance->getUser() === $this) {
                $deviceInstance->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|NetworkInterfaceInstance[]
     */
    public function getNetworkInterfaceInstances(): Collection
    {
        return $this->networkInterfaceInstances;
    }

    public function addNetworkInterfaceInstance(NetworkInterfaceInstance $networkInterfaceInstance): self
    {
        if (!$this->networkInterfaceInstances->contains($networkInterfaceInstance)) {
            $this->networkInterfaceInstances[] = $networkInterfaceInstance;
            $networkInterfaceInstance->setUser($this);
        }

        return $this;
    }

    public function removeNetworkInterfaceInstance(NetworkInterfaceInstance $networkInterfaceInstance): self
    {
        if ($this->networkInterfaceInstances->contains($networkInterfaceInstance)) {
            $this->networkInterfaceInstances->removeElement($networkInterfaceInstance);
            // set the owning side to null (unless already changed)
            if ($networkInterfaceInstance->getUser() === $this) {
                $networkInterfaceInstance->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Lab[]
     */
    public function getCreatedLabs(): Collection
    {
        return $this->createdLabs;
    }

    public function addCreatedLab(Lab $createdLab): self
    {
        if (!$this->createdLabs->contains($createdLab)) {
            $this->createdLabs[] = $createdLab;
            $createdLab->setAuthor($this);
        }

        return $this;
    }

    public function removeCreatedLab(Lab $createdLab): self
    {
        if ($this->createdLabs->contains($createdLab)) {
            $this->createdLabs->removeElement($createdLab);
            // set the owning side to null (unless already changed)
            if ($createdLab->getAuthor() === $this) {
                $createdLab->setAuthor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Activities[]
     */
    public function getCreatedActivities(): Collection
    {
        return $this->createdActivities;
    }

    public function addCreatedActivity(Activity $createdActivity): self
    {
        if (!$this->createdActivitiess->contains($createdActivity)) {
            $this->createdActivitiess[] = $createdActivity;
            $createdActivity->setAuthor($this);
        }

        return $this;
    }

    public function removeCreatedActivity(Activity $createdActivity): self
    {
        if ($this->createdActivitiess->contains($createdActivity)) {
            $this->createdActivitiess->removeElement($createdActivity);
            // set the owning side to null (unless already changed)
            if ($createdActivity->getAuthor() === $this) {
                $createdActivity->setAuthor(null);
            }
        }

        return $this;
    }

    public function getProfilePictureFilename(): ?string
    {
        return $this->profilePictureFilename;
    }

    public function setProfilePictureFilename($profilePictureFilename): self
    {
        $this->profilePictureFilename = $profilePictureFilename;

        return $this;
    }

    public function getProfilePicture(): ?string
    {
        if ($this->getProfilePictureFilename() == null || $this->getProfilePictureFilename() === "") {
            return null;

            // $package = new Package(new JsonManifestVersionStrategy(__DIR__.'/../../public/build/manifest.json'));
            
            // return $package->getUrl('build/images/faces/default-user-image.png');
        }

        $imagePath = 'uploads/user/avatar/' . $this->getId() . '/' . $this->getProfilePictureFilename();
        
        return $imagePath;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getLastActivity(): ?\DateTimeInterface
    {
        return $this->lastActivity;
    }

    public function setLastActivity(?\DateTimeInterface $lastActivity): self
    {
        $this->lastActivity = $lastActivity;

        return $this;
    }

    /**
     * @return Collection|Group[]
     */
    public function getGroups(): Collection
    {
        return $this->_groups;
    }

    public function isMemberOf(Group $group): bool
    {
        return $group->hasUser($this);
    }

    public function addGroup(Group $group): self
    {
        if (!$this->_groups->contains($group)) {
            $this->_groups[] = $group;
            $group->addUser($this);
        }

        return $this;
    }

    public function removeGroup(Group $group): self
    {
        if ($this->_groups->contains($group)) {
            $this->_groups->removeElement($group);
            $group->removeUser($this);
        }

        return $this;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }
}
