<?php

namespace App\Entity;

use App\Utils\Uuid;
use App\Entity\User;
use App\Entity\TextObject;
use App\Entity\Picture;
use App\Entity\InvitationCode;
use Doctrine\ORM\Mapping as ORM;
use App\Instance\InstanciableInterface;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: 'App\Repository\LabRepository')]
class Lab implements InstanciableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['api_get_lab', 'api_get_lab_template', 'api_get_device', 'api_get_lab_instance', 'api_groups', 'api_get_group', 'api_addlab', 'sandbox', 'api_get_lab_template', 'api_get_booking'])]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['api_get_lab', 'api_get_lab_template', 'api_get_lab_instance', 'export_lab', 'worker', 'api_addlab', 'sandbox', 'api_get_booking'])]
    private $name;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Serializer\Groups(['api_get_lab', 'api_get_lab_template', 'export_lab'])]
    private $shortDescription;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Serializer\Groups(['api_get_lab', 'api_get_lab_template', 'export_lab'])]
    private $description;

    #[ORM\Column(type: 'boolean', options: ['default' => 0])]
    #[Serializer\Groups(['api_get_lab', 'api_get_lab_template', 'export_lab'])]
    private $isTemplate;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Serializer\Groups(['api_get_lab', 'export_lab'])]
    private $tasks;

    #[ORM\Column(type: 'string', length: 10, options: ['default' => 1])]
    #[Serializer\Groups(['api_get_lab', 'export_lab'])]
    private $version = "1";

    #[ORM\Column(type: 'integer', options: ['default' => 300])]
    #[Serializer\Groups(['api_get_lab', 'export_lab'])]
    private $scripttimeout = 300;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Serializer\Groups(['api_get_lab', 'export_lab'])]
    private $locked = 0;

    #[ORM\JoinTable(name: 'lab_device')]
    #[ORM\JoinColumn(name: 'lab_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'device_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\ManyToMany(targetEntity: 'App\Entity\Device', inversedBy: 'labs', cascade: ['persist'])]
    private $devices;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\User', inversedBy: 'createdLabs')]
    #[Serializer\Groups(['api_get_lab', 'api_get_lab_instance'])]
    private $author;

    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['api_get_lab', 'worker', 'api_get_lab_instance', 'sandbox'])]
    private string $uuid;

     #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['api_get_lab', 'export_lab', 'worker', 'api_get_lab_template', 'api_get_booking'])]
    private int $virtuality;

    #[ORM\Column(type: 'datetime')]
    #[Serializer\Groups(['api_get_lab'])]
    private $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Serializer\Groups(['api_get_lab'])]
    private $lastUpdated;

    #[ORM\Column(type: 'boolean')]
    #[Serializer\Groups([])]
    private $isInternetAuthorized = false;

    #[ORM\ManyToMany(targetEntity: Group::class, inversedBy: 'labs')]
    private $groups;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Serializer\Exclude]
    private $banner;

    /**
     *
     * @var Collection|TextObject[]
     */
    #[ORM\OneToMany(targetEntity: 'App\Entity\TextObject', mappedBy: 'lab')]
    #[ORM\JoinColumn(nullable: true)]
    #[Serializer\Groups(['api_get_lab', 'api_get_lab_instance', 'api_get_lab_template', 'export_lab'])]
    private $textobjects;

    /**
     *
     * @var Collection|Picture[]
     */
    #[ORM\OneToMany(targetEntity: 'App\Entity\Picture', mappedBy: 'lab')]
    #[ORM\JoinColumn(nullable: true)]
    #[Serializer\Groups(['api_get_lab', 'api_get_lab_instance', 'api_get_lab_template'])]
    private $pictures;

    /**
     *
     * @var Collection|Booking[]
     */
    #[ORM\OneToMany(targetEntity: 'App\Entity\Booking', mappedBy: 'lab')]
    #[Serializer\Groups(['api_get_lab'])]
    private $bookings;

    #[ORM\Column(type: 'boolean')]
    #[Serializer\Groups(['api_get_lab', 'api_get_lab_template', 'export_lab'])]
    private $hasTimer = false;

    /**
     * @var string A "H:i:s" formatted value
     */
    #[Assert\Time]
    #[ORM\Column(type: 'string', nullable: true)]
    #[Serializer\Groups(['api_get_lab', 'api_get_lab_template', 'export_lab'])]
    private $timer;
  
    #[ORM\OneToMany(targetEntity: 'App\Entity\InvitationCode', mappedBy: 'lab', cascade: ['persist', 'remove'])]
    #[Serializer\Groups([])]
    private $invitationCodes;

    public function __construct()
    {
        $this->devices = new ArrayCollection();
        $this->connexions = new ArrayCollection();
        $this->activities = new ArrayCollection();
        $this->groups = new ArrayCollection();
        $this->uuid = (string) new Uuid();
        $this->createdAt = new \DateTime();
        $this->lastUpdated = new \DateTime();
        $this->textobjects = new ArrayCollection();
        $this->pictures = new ArrayCollection();
        $this->isTemplate = 0;
        $this->virtuality = 1;
    }

    public static function create(): self
    {
        return new static();
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

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): self
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getIsTemplate(): ?bool
    {
        return $this->isTemplate;
    }

    public function setIsTemplate(bool $isTemplate): self
    {
        $this->isTemplate = $isTemplate;

        return $this;
    }

    public function getTasks(): ?string
    {
        return $this->tasks;
    }

    public function setTasks(?string $tasks): self
    {
        $this->tasks = $tasks;

        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getScripttimeout(): ?int
    {
        return $this->scripttimeout;
    }

    public function setScripttimeout(?int $scripttimeout): self
    {
        $this->scripttimeout = $scripttimeout;

        return $this;
    }

    public function getLocked(): ?int
    {
        return $this->locked;
    }

    public function setLocked(?int $locked): self
    {
        $this->locked = $locked;

        return $this;
    }

    /**y
     * @return Collection|Device[]
     */
    public function getDevices()
    {
        return $this->devices;
    }

    public function addDevice(Device $device): self
    {
        if (!$this->devices->contains($device)) {
            $this->devices[] = $device;
            $device->addLab($this);
        }

        return $this;
    }

    public function removeDevice(Device $device): self
    {
        if ($this->devices->contains($device)) {
            $this->devices->removeElement($device);
            $device->removeLab($this);
        }

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

    public function getVirtuality(): ?int
    {
        return $this->virtuality;
    }

    public function setVirtuality(int $virtuality): self
    {
        $this->virtuality = $virtuality;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getLastUpdated(): ?\DateTimeInterface
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(?\DateTimeInterface $lastUpdated): self
    {
        $this->lastUpdated = $lastUpdated;

        return $this;
    }

    public function isInternetAuthorized(): bool
    {
        return $this->isInternetAuthorized;
    }

    public function setIsInternetAuthorized(bool $isInternetAuthorized): self
    {
        $this->isInternetAuthorized = $isInternetAuthorized;

        return $this;
    }

    /*public function getGroup(): ?Group
    {
        return $this->_group;
    }

    public function setGroup(?Group $_group): self
    {
        $this->_group = $_group;

        return $this;
    }*/

    /**
     * @return Collection|Group[]
     */
    public function getGroups()
    {
        return $this->groups;
    }

    public function addGroup(Group $group): self
    {
        if (!$this->groups->contains($group)) {
            $this->groups[] = $group;
            $group->addLab($this);
        }
        return $this;
    }

    public function removeGroup(Group $group): self
    {
        if ($this->groups->contains($group)) {
            $this->groups->removeElement($group);
        }

        return $this;
    }

    public function getBanner(): ?string
    {
        return $this->banner;
    }

    public function setBanner(?string $banner): self
    {
        $this->banner = $banner;

        return $this;
    }

     /**
     * @return Collection|TextObject[]
     */
    public function getTextobjects()
    {
        return $this->textobjects;
    }

    public function addTextobject(TextObject $textobject): self
    {
        if (!$this->textobjects->contains($textobject)) {
            $this->textobjects[] = $textobject;
            $textobject->setLab($this);
        }

        return $this;
    }

    public function removeTextobject(TextObject $textobject): self
    {
        if ($this->textobjects->contains($textobject)) {
            $this->textobjects->removeElement($textobject);
            // set the owning side to null (unless already changed)
            if ($textobject->getLab() === $this) {
                $textobject->setLab(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Picture[]
     */
    public function getPictures()
    {
        return $this->pictures;
    }

    public function addPicture(Picture $picture): self
    {
        if (!$this->pictures->contains($picture)) {
            $this->pictures[] = $picture;
            $picture->setLab($this);
           }

        return $this;
    }

    public function getTimer(): ?string
    {
        return $this->timer;
    }

    public function setTimer(?string $timer): self
    {
        $this->timer = $timer;
      
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
            $booking->setLab($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): self
    {
        if ($this->bookings->contains($booking)) {
            $this->bookings->removeElement($booking);
            // set the owning side to null (unless already changed)
            if ($booking->getLab() === $this) {
                $booking->setLab(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|InvitationCode[]
     */
    public function getInvitationCodes()
    {
        return $this->invitationCodes;
    }

    public function addInvitationCode(InvitationCode $invitationCode): self
    {
        if (!$this->invitationCodes->contains($invitationCode)) {
            $this->invitationCodes[] = $invitationCode;
            $invitationCode->setLab($this);
        }

        return $this;
    }

    public function removePicture(Picture $textobject): self
    {
        if ($this->pictures->contains($picture)) {
            $this->pictures->removeElement($picture);
            // set the owning side to null (unless already changed)
            if ($picture->getLab() === $this) {
                $picture->setLab(null);
              }
        }

        return $this;
    }

    public function getHasTimer(): bool
    {
        return $this->hasTimer;
    }

    public function setHasTimer(bool $hasTimer): self
    {
        $this->hasTimer = $hasTimer;
      
        return $this;
    }
  
    public function removeInvitationCode(InvitationCode $invitationCode): self
    {
        if ($this->invitationCodes->contains($invitationCode)) {
            $this->invitationCodes->removeElement($invitationCode);
            // set the owning side to null (unless already changed)
            if ($invitationCode->getLab() === $this) {
                $invitationCode->setLab(null);
            }
        }

        return $this;
    }

}
