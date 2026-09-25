<?php

namespace App\Entity;

use App\Utils\Uuid;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\PracticalSubjectRepository;
use Doctrine\Common\Collections\Collection;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PracticalSubjectRepository::class)]
class PracticalSubject
{
    public const CONTENT_TYPE_MARKDOWN = 'markdown';
    public const CONTENT_TYPE_PDF = 'pdf';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['api_get_practical_subject', 'api_get_practical_subjects', 'api_get_lab', 'api_get_lab_instance', 'api_get_lab_template', 'sandbox'])]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['api_get_practical_subject', 'api_get_practical_subjects', 'api_get_lab', 'api_get_lab_instance', 'api_get_lab_template', 'sandbox'])]
    private string $uuid;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Serializer\Groups(['api_get_practical_subject', 'api_get_practical_subjects', 'api_get_lab', 'api_get_lab_instance', 'api_get_lab_template', 'sandbox'])]
    private $name;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Serializer\Groups(['api_get_practical_subject', 'api_get_practical_subjects'])]
    private $description;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'markdown'])]
    #[Serializer\Groups(['api_get_practical_subject', 'api_get_practical_subjects', 'api_get_lab', 'api_get_lab_instance', 'api_get_lab_template', 'sandbox'])]
    private $contentType = self::CONTENT_TYPE_MARKDOWN;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Serializer\Groups([])]
    private $pdfFilename;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\User')]
    #[Serializer\Groups([])]
    private $author;

    #[ORM\Column(type: 'datetime')]
    #[Serializer\Groups(['api_get_practical_subject', 'api_get_practical_subjects'])]
    private $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Serializer\Groups(['api_get_practical_subject', 'api_get_practical_subjects'])]
    private $lastUpdated;

    /**
     * @var Collection|Lab[]
     */
    #[ORM\ManyToMany(targetEntity: 'App\Entity\Lab', mappedBy: 'practicalSubjects')]
    #[Serializer\Groups([])]
    private $labs;

    public function __construct()
    {
        $this->uuid = (string) new Uuid();
        $this->createdAt = new \DateTime();
        $this->lastUpdated = new \DateTime();
        $this->labs = new ArrayCollection();
    }

    public static function create(): self
    {
        return new static();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        $this->lastUpdated = new \DateTime();

        return $this;
    }

    public function getContentType(): ?string
    {
        return $this->contentType;
    }

    public function setContentType(string $contentType): self
    {
        if (!in_array($contentType, [self::CONTENT_TYPE_MARKDOWN, self::CONTENT_TYPE_PDF], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid content type "%s".', $contentType));
        }
        $this->contentType = $contentType;
        $this->lastUpdated = new \DateTime();

        return $this;
    }

    public function getPdfFilename(): ?string
    {
        return $this->pdfFilename;
    }

    public function setPdfFilename(?string $pdfFilename): self
    {
        $this->pdfFilename = $pdfFilename;

        return $this;
    }

    public function isPdf(): bool
    {
        return self::CONTENT_TYPE_PDF === $this->contentType;
    }

    public function isMarkdown(): bool
    {
        return self::CONTENT_TYPE_MARKDOWN === $this->contentType;
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

    /**
     * @return Collection|Lab[]
     */
    public function getLabs()
    {
        return $this->labs;
    }

    public function addLab(Lab $lab): self
    {
        if (!$this->labs->contains($lab)) {
            $this->labs[] = $lab;
            $lab->addPracticalSubject($this);
        }

        return $this;
    }

    public function removeLab(Lab $lab): self
    {
        if ($this->labs->contains($lab)) {
            $this->labs->removeElement($lab);
            $lab->removePracticalSubject($this);
        }

        return $this;
    }
}
