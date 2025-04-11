<?php

namespace App\Entity;

use App\Utils\Uuid;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use App\Instance\InstanciableInterface;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: 'App\Repository\LabRepository')]
class Booking implements InstanciableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['api_get_lab', 'api_get_booking'])]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['api_get_lab', 'api_get_booking'])]
    private $name;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\User', inversedBy: 'createdLabs')]
    #[Serializer\Groups(['api_get_lab', 'api_get_booking'])]
    private $author;

    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['api_get_lab', 'api_get_booking'])]
    private string $uuid;

    #[ORM\Column(type: 'datetime')]
    #[Serializer\Groups(['api_get_lab', 'api_get_booking'])]
    private $startDate;

    #[ORM\Column(type: 'datetime')]
    #[Serializer\Groups(['api_get_lab', 'api_get_booking'])]
    private $endDate;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\User', inversedBy: 'bookings')]
    #[Serializer\Groups(['api_get_booking'])]
    protected $user;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Group', inversedBy: 'bookings')]
    #[Serializer\Groups(['api_get_booking'])]
    protected $_group;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Lab', inversedBy: 'bookings')]
    #[Serializer\Groups(['api_get_booking'])]
    protected $lab;

    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['api_get_lab', 'api_get_booking'])]
    protected $reservedFor = self::RESERVED_FOR_USER;
  
    public const RESERVED_FOR_USER  = 'user';
    public const RESERVED_FOR_GROUP = 'group';


    public function __construct()
    {
        $this->uuid = (string) new Uuid();
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

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;

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

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user)
    {
        $this->user = $user;

        return $this;
    }

    public function getGroup(): ?Group
    {
        return $this->_group;
    }

    public function setGroup(?Group $_group)
    {
        $this->_group = $_group;

        return $this;
    }

    public function getLab(): ?Lab
    {
        return $this->lab;
    }

    public function setLab(?Lab $lab)
    {
        $this->lab = $lab;

        return $this;
    }

    public function getReservedFor(): ?string
    {
        return $this->reservedFor;
    }

    public function setReservedFor(string $reservedFor)
    {
        $this->reservedFor = $reservedFor;

        return $this;
    }

    public function isReservedForUser(): bool
    {
        return self::RESERVED_FOR_USER === $this->reservedFor;
    }

    public function isReservedForGroup(): bool
    {
        return self::RESERVED_FOR_GROUP === $this->reservedFor;
    }

    /**
     * Return the owner entity.
     *
     * @return InstancierInterface
     */
    #[Serializer\VirtualProperty]
    #[Serializer\Groups(['api_get_lab', 'api_get_booking'])]
    public function getOwner(): InstancierInterface
    { 
        return $this->isReservedForUser() ? $this->getUser() : $this->getGroup();
    }

    public function setOwner(string $uuid)
    {
        $this->uuid = $uuid;

        return $this;
    }

}
