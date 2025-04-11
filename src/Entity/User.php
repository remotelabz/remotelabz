<?php

namespace App\Entity;

use App\Utils\Uuid;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\VersionStrategy\JsonManifestVersionStrategy;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: 'App\Repository\UserRepository')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, InstancierInterface
{
    /**
     *
     * @var int
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['api_users', 'api_get_user', 'api_get_lab', 'api_get_group', 'api_groups', 'api_get_lab_instance', 'api_get_device_instance', 'worker', 'sandbox', 'api_get_booking'])]
    private $id;

    /**
     *
     * @var string
     */
    #[ORM\Column(type: 'string', length: 180, unique: true)]
    #[Serializer\Groups(['api_users', 'api_get_user', 'api_get_group', 'worker'])]
    private $email;

    /**
     *
     * @var array|string[]
     */
    #[ORM\Column(type: 'json')]
    #[Serializer\Accessor(getter: 'getRoles')]
    #[Serializer\Groups(['api_users', 'api_get_user', 'api_get_lab_instance'])]
    private $roles = [];

    /**
     *
     * @var string The hashed password
     */
    #[ORM\Column(type: 'string')]
    #[Serializer\Exclude]
    private $password;

    /**
     *
     * @var string
     */
    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['api_users', 'api_get_user'])]
    private $lastName;

    /**
     *
     * @var string
     */
    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['api_users', 'api_get_user'])]
    private $firstName;

    /**
     *
     * @var bool
     */
    #[ORM\Column(type: 'boolean')]
    #[Serializer\Groups(['api_users', 'api_get_user'])]
    private $enabled = true;

    /**
     *
     * @var Collection|LabInstance[]
     */
    #[ORM\OneToMany(targetEntity: 'App\Entity\LabInstance', mappedBy: 'user')]
    #[Serializer\Groups(['api_get_user'])]
    private $labInstances;

    /**
     *
     * @var Collection|Booking[]
     */
    #[ORM\OneToMany(targetEntity: 'App\Entity\Booking', mappedBy: 'user')]
    #[Serializer\Groups(['api_get_user'])]
    private $bookings;

    /**
     * @var Collection|Lab[]
     */
    #[ORM\OneToMany(targetEntity: 'App\Entity\Lab', mappedBy: 'author')]
    private $createdLabs;

    /**
     * @var Collection|Device[]
     */
    #[ORM\OneToMany(targetEntity: 'App\Entity\Device', mappedBy: 'author')]
    private $createdDevices;

    /**
     *
     * @var string
     */
    #[ORM\Column(type: 'string', nullable: true)]
    #[Serializer\Exclude]
    private $profilePictureFilename;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Serializer\Groups(['api_users', 'api_get_user'])]
    private $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Serializer\Groups(['api_users', 'api_get_user'])]
    private $lastActivity;

    #[ORM\OneToMany(targetEntity: 'App\Entity\GroupUser', mappedBy: 'user', cascade: ['persist'])]
    #[Serializer\Exclude]
    private $_groups;

    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['api_get_lab', 'api_users', 'api_get_user', 'api_get_lab_instance', 'api_get_device_instance', 'worker', 'sandbox'])]
    private string $uuid;

    /**
     *
     * @var bool
     */
    #[ORM\Column(type: 'boolean')]
    #[Serializer\Groups(['api_get_user'])]
    private $isShibbolethUser = false;

    public function __construct()
    {
        $this->courses = new ArrayCollection();
        $this->labInstances = new ArrayCollection();
        $this->createdLabs = new ArrayCollection();
        $this->createdDevices = new ArrayCollection();
        $this->createdActivities = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->ownedGroups = new ArrayCollection();
        $this->_groups = new ArrayCollection();
        $this->uuid = (string) new Uuid();
        $this->roles = ["ROLE_USER"];
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
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
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

    public function getHighestRole(): string
    {
        if (in_array('ROLE_SUPER_ADMINISTRATOR', $this->roles)) return 'ROLE_SUPER_ADMINISTRATOR';
        if (in_array('ROLE_ADMINISTRATOR', $this->roles)) return 'ROLE_ADMINISTRATOR';
        if (in_array('ROLE_TEACHER_EDITOR', $this->roles)) return 'ROLE_TEACHER_EDITOR';
        if (in_array('ROLE_TEACHER', $this->roles)) return 'ROLE_TEACHER';
        return 'ROLE_USER';
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function isAdministrator(): bool
    {
        return in_array('ROLE_SUPER_ADMINISTRATOR', $this->roles) || in_array('ROLE_ADMINISTRATOR', $this->roles);
    }

    public function isEditor(): bool
    {
        return in_array('ROLE_TEACHER_EDITOR', $this->roles);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    /**
     * @see PasswordAuthenticatedUserInterface
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
    public function getSalt(): ?string
    {
        // not needed when using the "bcrypt" algorithm in security.yaml
        return null;
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

    #[Serializer\VirtualProperty]
    #[Serializer\XmlAttribute]
    #[Serializer\Groups(['lab', 'details', 'start_lab', 'stop_lab', 'group_explore', 'instance_manager', 'group_users', 'user', 'groups', 'api_users', 'api_get_user', 'api_groups', 'api_get_group', 'api_get_lab', 'api_get_lab_instance'])]
    public function getName(): string
    {
        return $this->firstName.' '.$this->lastName;
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

    #[Serializer\VirtualProperty]
    #[Serializer\Groups(['user_instances', 'details'])]
    #[Serializer\XmlList(inline: false, entry: 'instances')]
    public function getInstances()
    {
        return $this->labInstances;
    }

    /**
     * @return Collection|LabInstance[]
     */
    public function getLabInstances()
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
     * @return Collection|Booking[]
     */
    public function getBookings()
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): self
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings[] = $booking;
            $booking->setUser($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): self
    {
        if ($this->bookings->contains($booking)) {
            $this->bookings->removeElement($booking);
            // set the owning side to null (unless already changed)
            if ($booking->getUser() === $this) {
                $booking->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Lab[]
     */
    public function getCreatedLabs()
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
     * @return Collection|Device[]
     */
    public function getCreatedDevices()
    {
        return $this->createdDevices;
    }

    public function addCreatedDevices(Lab $createdDevice): self
    {
        if (!$this->createdDevices->contains($createdDevice)) {
            $this->createdDevices[] = $createdDevice;
            $createdDevice->setAuthor($this);
        }

        return $this;
    }

    public function removeCreatedDevices(Lab $createdDevice): self
    {
        if ($this->createdDevices->contains($createdDevice)) {
            $this->createdDevices->removeElement($createdDevice);
            // set the owning side to null (unless already changed)
            if ($createdDevice->getAuthor() === $this) {
                $createdDevice->setAuthor(null);
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
        if (null == $this->getProfilePictureFilename() || '' === $this->getProfilePictureFilename()) {
            return null;
        }

        $imagePath = 'uploads/user/avatar/'.$this->getId().'/'.$this->getProfilePictureFilename();

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
    #[Serializer\VirtualProperty]
    #[Serializer\SerializedName('groups')]
    #[Serializer\Groups(['api_get_lab', 'api_get_lab_instance', 'group_details', 'user', 'api_users', 'api_get_user'])]
    public function getGroups()
    {
        return $this->_groups;
    }

    /**
     * @return Collection|Group[]
     */
    public function getGroupsInfo()
    {
        return $this->_groups->map(function ($groupUser) {
            return $groupUser->getGroup();
        });
    }

    /** 
     * @return int[]
     */
    public function getGroupsId(): array
    {
        return $this->_groups->map(function ($groupUser) {
            return $groupUser->getGroup()->getId();
        })->toArray();
    }

    /**
     * @return array|Group[]
     */
    public function getTopLevelGroupEntries()
    {
        // Get all groups objects...
        $groups = $this->_groups->map(function ($groupUser) {
            /* @var Group $group */
            return $groupUser->getGroup();
        });

        $filtered = new ArrayCollection();

        // ...then filter out those with no parents
        foreach ($this->_groups as $group) {
            $parent = $group->getGroup()->getParent();

            if (null === $parent) {
                $filtered->add($group);
            }
        }

        return $filtered;
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

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getType(): string
    {
        return 'user';
    }

    public function isShibbolethUser(): ?bool
    {
        return $this->isShibbolethUser;
    }

    public function setIsShibbolethUser(bool $isShibbolethUser): self
    {
        $this->isShibbolethUser = $isShibbolethUser;

        return $this;
    }
}
