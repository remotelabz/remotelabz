<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * An explicit share rule between two labs inside a same group.
 *
 * A rule (lab = S, sharedWith = L, group = G) means that the instances of S in G
 * and the instances of L in G are mutually reachable (see SharedLabSecurityManager).
 * The rule is not mirrored: (L, S, G) is not needed.
 */
#[ORM\Entity(repositoryClass: 'App\Repository\LabShareRepository')]
#[ORM\Table(name: 'lab_share')]
#[ORM\UniqueConstraint(name: 'uniq_share', columns: ['lab_id', 'shared_with_id', 'group_id'])]
class LabShare
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Serializer\Groups(['api_get_lab', 'api_get_lab_template', 'api_groups', 'api_get_group'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Lab::class, inversedBy: 'shares')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Lab $lab = null;

    #[ORM\ManyToOne(targetEntity: Lab::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Lab $sharedWith = null;

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Group $group = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

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

    public function getSharedWith(): ?Lab
    {
        return $this->sharedWith;
    }

    public function setSharedWith(?Lab $sharedWith): self
    {
        $this->sharedWith = $sharedWith;

        return $this;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function setGroup(?Group $group): self
    {
        $this->group = $group;

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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    // The related Lab and Group are exposed as scalars only: serializing them
    // would recursively serialize their own shares/labs (JMS has no cycle handling).

    #[Serializer\VirtualProperty]
    #[Serializer\Groups(['api_get_lab', 'api_get_lab_template', 'api_groups', 'api_get_group'])]
    public function getSharedWithId(): ?int
    {
        return $this->sharedWith?->getId();
    }

    #[Serializer\VirtualProperty]
    #[Serializer\Groups(['api_get_lab', 'api_get_lab_template', 'api_groups', 'api_get_group'])]
    public function getSharedWithUuid(): ?string
    {
        return $this->sharedWith?->getUuid();
    }

    #[Serializer\VirtualProperty]
    #[Serializer\Groups(['api_get_lab', 'api_get_lab_template', 'api_groups', 'api_get_group'])]
    public function getSharedWithName(): ?string
    {
        return $this->sharedWith?->getName();
    }

    #[Serializer\VirtualProperty]
    #[Serializer\Groups(['api_get_lab', 'api_get_lab_template', 'api_groups', 'api_get_group'])]
    public function getGroupId(): ?int
    {
        return $this->group?->getId();
    }

    #[Serializer\VirtualProperty]
    #[Serializer\Groups(['api_get_lab', 'api_get_lab_template', 'api_groups', 'api_get_group'])]
    public function getGroupUuid(): ?string
    {
        return $this->group?->getUuid();
    }
}
