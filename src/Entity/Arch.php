<?php

namespace App\Entity;

use App\Repository\ArchRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: ArchRepository::class)]
class Arch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
    * @var string|null
    */
    #[Assert\Type(type: 'string')]
    #[Assert\NotBlank(message: 'Architecture is required')]
    #[Assert\Choice(
        choices: ['x86', 'x86_64', 'arm', 'arm64'],
        message: 'Choose a valid architecture: x86, x86_64, arm, or arm64'
    )]
    #[ORM\Column(type: 'string', length: 20, nullable: false)]
    #[Serializer\Groups(['api_get_device', 'export_lab', 'api_get_lab_template', 'worker'])]
    private ?string $name = "x86_64";

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
