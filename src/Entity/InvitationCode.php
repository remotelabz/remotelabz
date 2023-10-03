<?php

namespace App\Entity;

use App\Entity\Lab;
use Doctrine\ORM\Mapping as ORM;
use App\Instance\InstanciableInterface;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use App\Utils\Uuid;

/**
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="IDX_LAB_MAIL", columns={"lab_id", "mail"})})
 * @ORM\Entity
 * @UniqueEntity(fields={"mail","lab"})
 */
class InvitationCode implements UserInterface, PasswordAuthenticatedUserInterface, InstancierInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Serializer\Groups({"api_invitation_codes", "worker"})
     *
     * @var int
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=8, unique=true)
     * @Serializer\Groups({"api_invitation_codes"})
     *
     * @var string
     */
    private $code;

    /**
     * @ORM\Column(type="string", length=180)
     * @Serializer\Groups({"api_invitation_codes", "worker"})
     * @Assert\Email
     * 
     * @var string
     */
    private $mail;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Lab", inversedBy="invitationCodes")
     * @Serializer\Groups({"api_invitation_codes"})
     */
    private $lab;

    /**
     * @ORM\Column(type="datetime")
     * @Serializer\Groups({"api_invitation_codes"})
     */
    private $expiryDate;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\Groups({"api_invitation_codes", "worker"})
     */
    private $uuid;

    public function __construct()
    {
        $this->uuid = (string) new Uuid();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getMail(): string
    {
        return $this->mail;
    }

    public function setMail(string $mail): self
    {
        $this->mail = $mail;

        return $this;
    }

    public function getLab(): ?Lab
    {
        return $this->lab;
    }

    public function setLab(Lab $lab): self
    {
        $this->lab = $lab;

        return $this;
    }

    public function getExpiryDate(): \DateTimeInterface
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(\DateTimeInterface $expiryDate): self
    {
        $this->expiryDate = $expiryDate;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername(): string
    {
        return (string) $this->mail;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->mail;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        //$roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles = ['ROLE_GUEST'];

        return array_unique($roles);
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
        return (string) $this->code;
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
        return 'guest';
    }

    public function getName(): string
    {
        return $this->mail;
    }
}