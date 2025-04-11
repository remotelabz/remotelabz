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

#[ORM\Entity(repositoryClass: 'App\Repository\TextObjectRepository')]
class TextObject implements InstanciableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['api_get_text_object', 'api_get_lab'])]
    private $id;

    #[Assert\NotBlank]
    #[Assert\Type(type: 'string')]
    #[ORM\Column(type: 'string', length: 255)]
    #[Serializer\Groups(['api_get_text_object', 'api_get_lab', 'api_get_lab_instance', 'api_get_lab_template', 'export_lab'])]
    private $name;

    #[Assert\NotBlank]
    #[Assert\Type(type: 'string')]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Serializer\Groups(['api_get_text_object', 'export_lab', 'api_get_lab', 'api_get_lab_instance', 'api_get_lab_template'])]
    private $type;

    #[Assert\NotBlank]
    #[Assert\Type(type: 'string')]
    #[ORM\Column(type: 'string', length: 1500, nullable: true)]
    #[Serializer\Groups(['api_get_text_object', 'export_lab', 'api_get_lab', 'api_get_lab_instance', 'api_get_lab_template'])]
    private $data;

    #[Assert\Type(type: 'array')]
    #[ORM\Column(type: 'string', nullable: true)]
    #[Serializer\Groups(['api_get_text_object', 'export_lab', 'api_get_lab', 'api_get_lab_instance', 'api_get_lab_template'])]
    private $newdata;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\Lab', inversedBy: 'textobjects', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Serializer\Groups(['api_get_text_object'])]
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

    public function getData(): ?string
    {
        return $this->data;
    }

    public function setData(string $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getNewdata(): ?int
    {
        return $this->newdata;
    }

    public function setNewdata(int $newdata): self
    {
        $this->newdata = $newdata;

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