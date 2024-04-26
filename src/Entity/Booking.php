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

/**
 * @ORM\Entity(repositoryClass="App\Repository\LabRepository")
 */
class Booking implements InstanciableInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Serializer\Groups({"api_get_lab", "api_get_lab_template", "api_get_device", "api_get_lab_instance", "api_groups", "api_get_group","api_addlab","sandbox", "api_get_lab_template"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\Groups({"api_get_lab", "api_get_lab_template", "api_get_lab_instance", "export_lab", "worker","api_addlab","sandbox"})
     */
    private $name;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="createdLabs")
     * @Serializer\Groups({"api_get_lab", "api_get_lab_instance"})
     */
    private $author;

    /**
     * @ORM\Column(type="string", length=255)
     * @Serializer\Groups({"api_get_lab", "worker","api_get_lab_instance","sandbox"})
     */
    private $uuid;

    /**
     * @ORM\Column(type="datetime")
     * @Serializer\Groups({"api_get_lab"})
     */
    private $startDate;

    /**
     * @ORM\Column(type="datetime")
     * @Serializer\Groups({"api_get_lab"})
     */
    private $endDate;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="bookings")
     */
    protected $user;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Group", inversedBy="bookings")
     */
    protected $_group;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Lab", inversedBy="bookings")
     */
    protected $lab;
  

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

}
