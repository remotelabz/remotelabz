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

/**
 * @ORM\Table(uniqueConstraints={@ORM\UniqueConstraint(name="IDX_LAB_MAIL", columns={"lab_id", "mail"})})
 * @ORM\Entity
 * @UniqueEntity(fields={"mail","lab"})
 */
class InvitationCode
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Serializer\Groups({"api_invitation_codes"})
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
     * @Serializer\Groups({"api_invitation_codes"})
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

    public function __construct()
    {}

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

}