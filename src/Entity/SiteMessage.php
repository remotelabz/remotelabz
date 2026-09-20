<?php

namespace App\Entity;

use App\Repository\SiteMessageRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Site message displayed to users.
 *
 * Two types:
 * - general: displayed on the login page (admin only)
 * - information: persistent banner displayed on the pages of targeted users (name or group)
 */
#[ORM\Entity(repositoryClass: SiteMessageRepository::class)]
#[ORM\Table(name: 'site_messages')]
class SiteMessage
{
    public const TYPE_GENERAL = 'general';
    public const TYPE_INFORMATION = 'information';

    /**
     * ChoiceType choices: label => value
     */
    public const TYPES = [
        'General (login page)' => self::TYPE_GENERAL,
        'Information (connected users)' => self::TYPE_INFORMATION,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[Assert\Choice(choices: [self::TYPE_GENERAL, self::TYPE_INFORMATION])]
    #[ORM\Column(type: 'string', length: 20)]
    private string $type = self::TYPE_INFORMATION;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $title = null;

    #[Assert\NotBlank]
    #[ORM\Column(type: 'text')]
    private string $message = '';

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $targetUserIds = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $targetGroupIds = null;

    /**
     * Transient entity collections used by the form (not persisted).
     *
     * @var User[]|null
     */
    private ?array $targetUsers = null;

    /**
     * @var Group[]|null
     */
    private ?array $targetGroups = null;

    /**
     * Transient: UTC offset (minutes) of the browser that submitted the form,
     * used to convert the local expiresAt value to UTC server-side.
     */
    private ?int $expiresAtOffset = null;

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $createdBy = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $expiresAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $updatedAt = null;

    #[Assert\Callback]
    public function validateType(ExecutionContextInterface $context): void
    {
        if ($this->type === self::TYPE_INFORMATION && (null === $this->title || '' === trim($this->title))) {
            $context->buildViolation('A title is required for information messages.')
                ->atPath('title')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function isGeneral(): bool
    {
        return $this->type === self::TYPE_GENERAL;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    public function getTargetUserIds(): ?array
    {
        return $this->targetUserIds;
    }

    public function setTargetUserIds(array|\Traversable|null $targetUserIds): self
    {
        $this->targetUserIds = $this->normalizeIds($targetUserIds);
        return $this;
    }

    /**
     * @return User[]|null
     */
    public function getTargetUsers(): ?array
    {
        return $this->targetUsers;
    }

    /**
     * @param User[]|\Traversable|null $targetUsers
     */
    public function setTargetUsers(array|\Traversable|null $targetUsers): self
    {
        $targetUsers = $targetUsers instanceof \Traversable ? $targetUsers->toArray() : $targetUsers;
        $this->targetUsers = $targetUsers;
        $this->targetUserIds = $this->normalizeIds($targetUsers);
        return $this;
    }

    public function getTargetGroupIds(): ?array
    {
        return $this->targetGroupIds;
    }

    public function setTargetGroupIds(array|\Traversable|null $targetGroupIds): self
    {
        $this->targetGroupIds = $this->normalizeIds($targetGroupIds);
        return $this;
    }

    /**
     * @return Group[]|null
     */
    public function getTargetGroups(): ?array
    {
        return $this->targetGroups;
    }

    /**
     * @param Group[]|\Traversable|null $targetGroups
     */
    public function setTargetGroups(array|\Traversable|null $targetGroups): self
    {
        $targetGroups = $targetGroups instanceof \Traversable ? $targetGroups->toArray() : $targetGroups;
        $this->targetGroups = $targetGroups;
        $this->targetGroupIds = $this->normalizeIds($targetGroups);
        return $this;
    }

    private function normalizeIds(array|\Traversable|null $ids): ?array
    {
        if (null === $ids) {
            return null;
        }
        if ($ids instanceof \Traversable) {
            $ids = $ids->toArray();
        }
        $ids = array_map(function ($id): ?int {
            if (is_object($id) && method_exists($id, 'getId')) {
                return $id->getId();
            }
            return null === $id ? null : (int) $id;
        }, $ids);
        $ids = array_values(array_filter($ids, fn ($id): bool => null !== $id));
        return $ids ?: null;
    }

    /**
     * A message without any target applies to all users.
     */
    public function targetsEveryone(): bool
    {
        return $this->isGeneral()
            || (empty($this->targetUserIds) && empty($this->targetGroupIds));
    }

    public function targetsUser(int $userId, array $userGroupIds): bool
    {
        if ($this->targetsEveryone()) {
            return true;
        }
        if (!empty($this->targetUserIds) && in_array($userId, $this->targetUserIds, true)) {
            return true;
        }
        if (!empty($this->targetGroupIds)) {
            foreach ($this->targetGroupIds as $groupId) {
                if (in_array($groupId, $userGroupIds, true)) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getExpiresAt(): ?DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?DateTimeInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isExpired(): bool
    {
        return null !== $this->expiresAt && $this->expiresAt < new \DateTime();
    }

    public function getExpiresAtOffset(): ?int
    {
        return $this->expiresAtOffset;
    }

    public function setExpiresAtOffset($expiresAtOffset): self
    {
        $this->expiresAtOffset = is_numeric($expiresAtOffset) ? (int) $expiresAtOffset : null;
        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
