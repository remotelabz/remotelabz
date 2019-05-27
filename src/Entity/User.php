<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 */
class User implements UserInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Serializer\Groups({"primary_key"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=180, unique=true)
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"lab"})
     */
    private $email;

    /**
     * @ORM\Column(type="json")
     * @Serializer\Accessor(getter="getRoles")
     * @Serializer\XmlList(inline=false, entry="role")
     * @Serializer\Groups({"details"})
     */
    private $roles = [];

    /**
     * @var string The hashed password
     * @ORM\Column(type="string")
     * @Serializer\Exclude
     */
    private $password;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"lab"})
     */
    private $lastName;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"lab"})
     */
    private $firstName;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Course", inversedBy="users")
     * @Serializer\XmlList(inline=true, entry="course")
     * @Serializer\Groups({"courses"})
     */
    private $courses;

    /**
     * @ORM\Column(type="boolean")
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"lab", "details"})
     */
    private $enabled = true;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Lab", mappedBy="user")
     * @Serializer\XmlList(inline=true, entry="lab")
     */
    private $labs;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Instance", mappedBy="user")
     * @Serializer\XmlList(inline=true, entry="instance")
     * @Serializer\Groups({"instances"})
     */
    private $instances;

    public function __construct()
    {
        $this->courses = new ArrayCollection();
        $this->labs = new ArrayCollection();
        $this->instances = new ArrayCollection();
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
     * @Serializer\Groups({"lab", "details"})
     */
    public function getName(): ?string
    {
        return $this->firstName . " " . $this->lastName;
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
            $lab->setUser($this);
        }

        return $this;
    }

    public function removeLab(Lab $lab): self
    {
        if ($this->labs->contains($lab)) {
            $this->labs->removeElement($lab);
            // set the owning side to null (unless already changed)
            if ($lab->getUser() === $this) {
                $lab->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Instance[]
     */
    public function getInstances(): Collection
    {
        return $this->instances;
    }

    public function addInstance(Instance $instance): self
    {
        if (!$this->instances->contains($instance)) {
            $this->instances[] = $instance;
            $instance->setUser($this);
        }

        return $this;
    }

    public function removeInstance(Instance $instance): self
    {
        if ($this->instances->contains($instance)) {
            $this->instances->removeElement($instance);
            // set the owning side to null (unless already changed)
            if ($instance->getUser() === $this) {
                $instance->setUser(null);
            }
        }

        return $this;
    }
}
