<?php

namespace App\Entity;

use App\Utils\Uuid;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity(repositoryClass="App\Repository\InstanceRepository")
 */
class Instance
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"primary_key"})
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Lab", inversedBy="instances")
     * @Serializer\Groups({"lab"})
     */
    private $lab;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Device", inversedBy="instances")
     * @Serializer\Groups({"lab"})
     */
    private $device;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\NetworkInterface", inversedBy="instances")
     * @Serializer\Groups({"lab"})
     */
    private $networkInterface;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"lab"})
     */
    private $uuid;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="instances")
     * @Serializer\Groups({"user"})
     */
    private $user;

    /**
     * @ORM\Column(type="boolean")
     * @Serializer\XmlAttribute
     * @Serializer\Groups({"lab"})
     */
    private $isStarted = false;

    public function __construct()
    {
        $this->uuid = (string) new Uuid();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDevice(): ?Device
    {
        return $this->device;
    }

    public function setDevice(?Device $device): self
    {
        $this->device = $device;

        return $this;
    }

    public function getNetworkInterface(): ?NetworkInterface
    {
        return $this->networkInterface;
    }

    public function setNetworkInterface(?NetworkInterface $networkInterface): self
    {
        $this->networkInterface = $networkInterface;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

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
