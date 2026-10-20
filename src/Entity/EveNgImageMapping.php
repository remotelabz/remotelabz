<?php

namespace App\Entity;

use App\Repository\EveNgImageMappingRepository;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Memorise la correspondance entre un identifiant d'image EVE-NG (attribut
 * `image` d'un <node> du fichier .unl) et un OperatingSystem RemoteLabz.
 *
 * Cette table rend les imports suivants "one-click" : des qu'un admin a resolu
 * une image (reutilisation, upload, URL, VM vierge...), la decision est
 * reutilisee et proposee automatiquement pour les imports ulterieurs.
 */
#[ORM\Entity(repositoryClass: EveNgImageMappingRepository::class)]
#[ORM\Table(name: 'eve_ng_image_mapping')]
#[UniqueEntity('eveImage')]
class EveNgImageMapping
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['api_eve_ng_image_mapping'])]
    private ?int $id = null;

    /**
     * Identifiant d'image EVE-NG, tel qu'il apparait dans le .unl
     * (ex. "vios-adventerprisek9-m.spa.159-3.M3").
     */
    #[Assert\NotBlank]
    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Serializer\Groups(['api_eve_ng_image_mapping'])]
    private ?string $eveImage = null;

    #[ORM\ManyToOne(targetEntity: OperatingSystem::class)]
    #[ORM\JoinColumn(name: 'operating_system_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Serializer\Groups(['api_eve_ng_image_mapping'])]
    private ?OperatingSystem $operatingSystem = null;

    #[ORM\Column(type: 'datetime')]
    #[Serializer\Groups(['api_eve_ng_image_mapping'])]
    private \DateTimeInterface $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Serializer\Groups(['api_eve_ng_image_mapping'])]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEveImage(): ?string
    {
        return $this->eveImage;
    }

    public function setEveImage(string $eveImage): self
    {
        $this->eveImage = $eveImage;

        return $this;
    }

    public function getOperatingSystem(): ?OperatingSystem
    {
        return $this->operatingSystem;
    }

    public function setOperatingSystem(?OperatingSystem $operatingSystem): self
    {
        $this->operatingSystem = $operatingSystem;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }
}
