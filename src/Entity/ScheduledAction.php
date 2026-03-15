<?php

namespace App\Entity;

use App\Repository\ScheduledActionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Représente une action planifiée (start / stop / reset / leave)
 * à exécuter sur toutes les instances d'un lab pour un groupe donné,
 * à une date et heure précises.
 *
 * Le runner (commande Symfony appelée par cron chaque minute) interroge
 * cette table, exécute les actions dont scheduledAt <= NOW et status = 'pending',
 * puis met à jour le status en 'done' ou 'failed'.
 */
#[ORM\Entity(repositoryClass: ScheduledActionRepository::class)]
#[ORM\Table(name: 'scheduled_action')]
#[ORM\Index(columns: ['status', 'scheduled_at'], name: 'idx_status_scheduled_at')]
class ScheduledAction
{
    // ── Statuts possibles ────────────────────────────────────────────────────
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE    = 'done';
    public const STATUS_FAILED  = 'failed';

    // ── Actions possibles ────────────────────────────────────────────────────
    public const ACTION_START = 'start';
    public const ACTION_STOP  = 'stop';
    public const ACTION_RESET = 'reset';
    public const ACTION_LEAVE = 'leave';

    public const ACTIONS  = [self::ACTION_START, self::ACTION_STOP, self::ACTION_RESET, self::ACTION_LEAVE];
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_RUNNING, self::STATUS_DONE, self::STATUS_FAILED];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * UUID publique (utilisé dans les routes API).
     */
    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $uuid;

    /**
     * Lab concerné par l'action planifiée.
     */
    #[ORM\ManyToOne(targetEntity: Lab::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Lab $lab;

    /**
     * Groupe cible. Si null, l'action s'applique à toutes les instances du lab.
     */
    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Group $group = null;

    /**
     * Action à effectuer : start | stop | reset | leave
     */
    #[ORM\Column(type: 'string', length: 16)]
    private string $action;

    /**
     * Date et heure auxquelles l'action doit être déclenchée.
     */
    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $scheduledAt;

    /**
     * Date et heure auxquelles l'action a effectivement été exécutée.
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $executedAt = null;

    /**
     * Statut courant : pending | running | done | failed
     */
    #[ORM\Column(type: 'string', length: 16)]
    private string $status = self::STATUS_PENDING;

    /**
     * Message d'erreur si status = failed.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    /**
     * Utilisateur ayant créé la planification.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    /**
     * Date de création de l'entrée.
     */
    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    /**
     * Résumé de l'exécution stocké en JSON :
     * [{ labInstanceUuid, deviceUuid, name, status }, ...]
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $executionReport = null;

    public function __construct()
    {
        $this->uuid      = Uuid::v4()->toRfc4122();
        $this->createdAt = new \DateTimeImmutable();
    }

    // ── Getters / Setters ────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getUuid(): string { return $this->uuid; }

    public function getLab(): Lab { return $this->lab; }
    public function setLab(Lab $lab): static { $this->lab = $lab; return $this; }

    public function getGroup(): ?Group { return $this->group; }
    public function setGroup(?Group $group): static { $this->group = $group; return $this; }

    public function getAction(): string { return $this->action; }
    public function setAction(string $action): static
    {
        if (!in_array($action, self::ACTIONS, true)) {
            throw new \InvalidArgumentException("Invalid action '$action'. Must be one of: " . implode(', ', self::ACTIONS));
        }
        $this->action = $action;
        return $this;
    }

    public function getScheduledAt(): \DateTimeInterface { return $this->scheduledAt; }
    public function setScheduledAt(\DateTimeInterface $scheduledAt): static { $this->scheduledAt = $scheduledAt; return $this; }

    public function getExecutedAt(): ?\DateTimeInterface { return $this->executedAt; }
    public function setExecutedAt(?\DateTimeInterface $executedAt): static { $this->executedAt = $executedAt; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid status '$status'.");
        }
        $this->status = $status;
        return $this;
    }

    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $errorMessage): static { $this->errorMessage = $errorMessage; return $this; }

    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $createdBy): static { $this->createdBy = $createdBy; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function getExecutionReport(): ?array { return $this->executionReport; }
    public function setExecutionReport(?array $executionReport): static { $this->executionReport = $executionReport; return $this; }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isPending(): bool { return $this->status === self::STATUS_PENDING; }
    public function isRunning(): bool { return $this->status === self::STATUS_RUNNING; }
    public function isDone(): bool    { return $this->status === self::STATUS_DONE; }
    public function isFailed(): bool  { return $this->status === self::STATUS_FAILED; }
    public function isDue(): bool     { return $this->isPending() && $this->scheduledAt <= new \DateTimeImmutable(); }
}