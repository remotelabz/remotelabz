<?php

namespace App\Entity;

use App\Utils\Uuid;
use Doctrine\ORM\Mapping as ORM;
use App\Instance\InstanciableInterface;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\MappedSuperclass
 */
class Instance implements InstanciableInterface
{
    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"lab", "start_lab", "stop_lab", "instance_manager", "instances"})
     */
    protected $uuid;

    /**
     * @ORM\Column(type="boolean")
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"lab", "start_lab", "stop_lab"})
     */
    protected $isStarted = false;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\Groups({"lab", "start_lab", "stop_lab", "instance_manager", "instances"})
     */
    protected $ownedBy = self::OWNED_BY_USER;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     */
    protected $user;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Group")
     */
    protected $_group;

    public const OWNED_BY_USER  = 'user';
    public const OWNED_BY_GROUP = 'group';

    public function __construct()
    {
        $this->uuid = (string) new Uuid();
    }

    public static function create()
    {
        return new static;
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

    public function belongsToCurrentUser($object, $context): bool
    {
        return $context->getAttribute('user') == $this->user;
    }

    public function isStarted(): ?bool
    {
        return $this->isStarted;
    }

    public function setStarted(bool $isStarted): self
    {
        $this->isStarted = $isStarted;

        return $this;
    }

    public static function belongsTo($user): bool
    {
        return $this->user == $user;
    }

    public function getOwnedBy(): ?string
    {
        return $this->ownedBy;
    }

    public function setOwnedBy(string $ownedBy)
    {
        $this->ownedBy = $ownedBy;

        return $this;
    }

    public function isOwnedByUser(): bool
    {
        return self::OWNED_BY_USER === $this->ownedBy;
    }

    public function isOwnedByGroup(): bool
    {
        return self::OWNED_BY_GROUP === $this->ownedBy;
    }

    /**
     * Return the owner entity.
     *
     * @return InstancierInterface
     * 
     * @Serializer\VirtualProperty()
     * @Serializer\Groups({"lab", "start_lab", "stop_lab", "instance_manager", "instances"})
     */
    public function getOwner(): InstancierInterface
    {
        return $this->isOwnedByUser() ? $this->getUser() : $this->getGroup();
    }

    public function setOwner(string $uuid): self
    {
        $this->uuid = $uuid;

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
