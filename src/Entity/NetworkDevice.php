<?php

namespace App\Entity;

//use App\Utils\Uuid;
use Doctrine\ORM\Mapping as ORM;
use App\Instance\InstanciableInterface;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Lab;

/**
 * @ORM\Entity(repositoryClass="App\Repository\NetworkDeviceRepository")
 */
class NetworkDevice implements InstanciableInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Serializer\Groups({"api_get_network"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\Groups({"api_get_network"})
     * @Assert\Type(type="string")
     */
    private $name;

    /**
     * @ORM\Column(type="integer")
     * @Serializer\Groups({"api_get_network"})
     * @Assert\Type(type="integer")
     */
    private $count;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Serializer\Groups({"api_get_network"})
     * @Assert\Type(type="string")
     */
    private $type;

    /**
     * @ORM\Column(type="integer")
     * @Serializer\Groups({"api_get_network"})
     */
    private $top;

    /**
     * @ORM\Column(type="integer")
     * @Serializer\Groups({"api_get_network"})
     */
    private $leftPosition;

    /**
     * @ORM\Column(type="integer")
     * @Serializer\Groups({"api_get_network"})
     */
    private $visibility;

    /**
     * @ORM\Column(type="integer")
     * @Serializer\Groups({"api_get_network"})
     */
    private $postfix;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Lab", inversedBy="networks", cascade={"persist"})
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     */
    private $lab;


    public function __construct()
    {
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    public function getTop(): ?int
    {
        return $this->top;
    }

    public function setTop(int $top): self
    {
        $this->top = $top;

        return $this;
    }
    public function getLeftPosition(): ?int
    {
        return $this->leftPosition;
    }

    public function setLeftPosition(int $leftPosition): self
    {
        $this->leftPosition = $leftPosition;

        return $this;
    }
    public function getVisibility(): ?int
    {
        return $this->visibility;
    }

    public function setVisibility(int $visibility): self
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getPostfix(): ?int
    {
        return $this->postfix;
    }

    public function setPostfix(int $postfix): self
    {
        $this->postfix = $postfix;

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