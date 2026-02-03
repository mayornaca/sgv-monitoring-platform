<?php

namespace App\Entity;

use App\Repository\AlertRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlertRepository::class)]
#[ORM\Table(name: 'alerts')]
#[ORM\Index(columns: ['source_type'], name: 'idx_alert_source_type')]
#[ORM\Index(columns: ['source_id'], name: 'idx_alert_source_id')]
#[ORM\Index(columns: ['status'], name: 'idx_alert_status')]
#[ORM\Index(columns: ['severity'], name: 'idx_alert_severity')]
#[ORM\Index(columns: ['created_at'], name: 'idx_alert_created')]
#[ORM\Index(columns: ['alert_type'], name: 'idx_alert_type')]
#[ORM\HasLifecycleCallbacks]
class Alert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $alertType = null;

    #[ORM\Column(length: 50)]
    private ?string $sourceType = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $sourceId = null;

    #[ORM\Column(length: 20)]
    private ?string $severity = null;

    #[ORM\Column(length: 20, options: ['default' => 'active'])]
    private ?string $status = 'active';

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $tags = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $workflowState = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $acknowledgedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $acknowledgedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $resolvedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $resolvedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $resolutionNotes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastEscalatedAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $escalationLevel = 0;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $notificationCount = 0;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        // Auto-update workflow state when status changes
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getAlertType(): ?string
    {
        return $this->alertType;
    }

    public function setAlertType(string $alertType): static
    {
        $this->alertType = $alertType;
        return $this;
    }

    public function getSourceType(): ?string
    {
        return $this->sourceType;
    }

    public function setSourceType(string $sourceType): static
    {
        $this->sourceType = $sourceType;
        return $this;
    }

    public function getSourceId(): ?string
    {
        return $this->sourceId;
    }

    public function setSourceId(?string $sourceId): static
    {
        $this->sourceId = $sourceId;
        return $this;
    }

    public function getSeverity(): ?string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): static
    {
        $this->severity = $severity;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getTags(): ?array
    {
        return $this->tags ?? [];
    }

    public function setTags(?array $tags): static
    {
        $this->tags = $tags;
        return $this;
    }

    public function addTag(string $tag): static
    {
        $tags = $this->getTags();
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->setTags($tags);
        }
        return $this;
    }

    public function getWorkflowState(): ?string
    {
        return $this->workflowState;
    }

    public function setWorkflowState(?string $workflowState): static
    {
        $this->workflowState = $workflowState;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?int $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getAcknowledgedAt(): ?\DateTimeInterface
    {
        return $this->acknowledgedAt;
    }

    public function setAcknowledgedAt(?\DateTimeInterface $acknowledgedAt): static
    {
        $this->acknowledgedAt = $acknowledgedAt;
        return $this;
    }

    public function getAcknowledgedBy(): ?int
    {
        return $this->acknowledgedBy;
    }

    public function setAcknowledgedBy(?int $acknowledgedBy): static
    {
        $this->acknowledgedBy = $acknowledgedBy;
        return $this;
    }

    public function getResolvedAt(): ?\DateTimeInterface
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeInterface $resolvedAt): static
    {
        $this->resolvedAt = $resolvedAt;
        return $this;
    }

    public function getResolvedBy(): ?int
    {
        return $this->resolvedBy;
    }

    public function setResolvedBy(?int $resolvedBy): static
    {
        $this->resolvedBy = $resolvedBy;
        return $this;
    }

    public function getResolutionNotes(): ?string
    {
        return $this->resolutionNotes;
    }

    public function setResolutionNotes(?string $resolutionNotes): static
    {
        $this->resolutionNotes = $resolutionNotes;
        return $this;
    }

    public function getLastEscalatedAt(): ?\DateTimeInterface
    {
        return $this->lastEscalatedAt;
    }

    public function setLastEscalatedAt(?\DateTimeInterface $lastEscalatedAt): static
    {
        $this->lastEscalatedAt = $lastEscalatedAt;
        return $this;
    }

    public function getEscalationLevel(): ?int
    {
        return $this->escalationLevel;
    }

    public function setEscalationLevel(int $escalationLevel): static
    {
        $this->escalationLevel = $escalationLevel;
        return $this;
    }

    public function incrementEscalationLevel(): static
    {
        $this->escalationLevel++;
        $this->lastEscalatedAt = new \DateTime();
        return $this;
    }

    public function getNotificationCount(): ?int
    {
        return $this->notificationCount;
    }

    public function setNotificationCount(int $notificationCount): static
    {
        $this->notificationCount = $notificationCount;
        return $this;
    }

    public function incrementNotificationCount(): static
    {
        $this->notificationCount++;
        return $this;
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledgedAt !== null;
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function acknowledge(int $userId): static
    {
        $this->acknowledgedAt = new \DateTime();
        $this->acknowledgedBy = $userId;
        $this->status = 'acknowledged';
        return $this;
    }

    public function resolve(int $userId, string $notes = null): static
    {
        $this->resolvedAt = new \DateTime();
        $this->resolvedBy = $userId;
        $this->resolutionNotes = $notes;
        $this->status = 'resolved';
        return $this;
    }

    public function getAgeInMinutes(): int
    {
        $now = new \DateTime();
        $diff = $now->diff($this->createdAt);
        return ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
    }

    public function getPriorityScore(): int
    {
        $baseScore = match($this->severity) {
            'critical' => 100,
            'high' => 75,
            'medium' => 50,
            'low' => 25,
            default => 25
        };

        // Add age factor
        $ageScore = min($this->getAgeInMinutes() / 10, 50); // Max 50 points for age
        
        // Add escalation factor
        $escalationScore = $this->escalationLevel * 20;

        return $baseScore + $ageScore + $escalationScore;
    }

    public function getSeverityColor(): string
    {
        return match($this->severity) {
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'blue',
            default => 'gray'
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'active' => 'red',
            'acknowledged' => 'yellow',
            'resolved' => 'green',
            'suppressed' => 'gray',
            default => 'gray'
        };
    }

    public function getTypeIcon(): string
    {
        return match($this->alertType) {
            'device_failure' => 'fa-exclamation-triangle',
            'service_down' => 'fa-server',
            'data_loss' => 'fa-database',
            'connectivity' => 'fa-wifi',
            'performance' => 'fa-tachometer-alt',
            'security' => 'fa-shield-alt',
            'system' => 'fa-cogs',
            default => 'fa-bell'
        };
    }
}