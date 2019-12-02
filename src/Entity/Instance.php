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
     * @Serializer\Groups({"lab", "start_lab", "stop_lab"})
     */
    protected $uuid;

    /**
     * @ORM\Column(type="boolean")
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"lab", "start_lab", "stop_lab"})
     */
    protected $isStarted = false;

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
}
