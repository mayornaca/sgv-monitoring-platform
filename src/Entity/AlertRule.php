<?php

namespace App\Entity;

use App\Repository\AlertRuleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlertRuleRepository::class)]
#[ORM\Table(name: 'alert_rules')]
#[ORM\Index(columns: ['source_type'], name: 'idx_alert_rule_source_type')]
#[ORM\Index(columns: ['alert_type'], name: 'idx_alert_rule_alert_type')]
#[ORM\Index(columns: ['active'], name: 'idx_alert_rule_active')]
class AlertRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $sourceType = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $alertType = null;

    #[ORM\Column(type: Types::JSON)]
    private array $channels = [];

    #[ORM\Column(type: Types::JSON)]
    private array $escalationTimes = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $conditions = null;

    #[ORM\Column(length: 20, options: ['default' => 'medium'])]
    private ?string $priority = 'medium';

    #[ORM\Column(options: ['default' => true])]
    private ?bool $active = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column]
    private ?int $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $updatedBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getSourceType(): ?string
    {
        return $this->sourceType;
    }

    public function setSourceType(?string $sourceType): static
    {
        $this->sourceType = $sourceType;
        return $this;
    }

    public function getAlertType(): ?string
    {
        return $this->alertType;
    }

    public function setAlertType(?string $alertType): static
    {
        $this->alertType = $alertType;
        return $this;
    }

    public function getChannels(): array
    {
        return $this->channels;
    }

    public function setChannels(array $channels): static
    {
        $this->channels = $channels;
        return $this;
    }

    public function addChannel(string $channel): static
    {
        if (!in_array($channel, $this->channels)) {
            $this->channels[] = $channel;
        }
        return $this;
    }

    public function removeChannel(string $channel): static
    {
        $this->channels = array_values(array_filter($this->channels, fn($c) => $c !== $channel));
        return $this;
    }

    public function getEscalationTimes(): array
    {
        return $this->escalationTimes;
    }

    public function setEscalationTimes(array $escalationTimes): static
    {
        $this->escalationTimes = $escalationTimes;
        return $this;
    }

    public function getConditions(): ?array
    {
        return $this->conditions;
    }

    public function setConditions(?array $conditions): static
    {
        $this->conditions = $conditions;
        return $this;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;
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

    public function setCreatedBy(int $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?int $updatedBy): static
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }

    // Helper methods
    public function shouldEscalateAt(int $minutes): bool
    {
        return in_array($minutes, $this->escalationTimes);
    }

    public function getChannelsForEscalationLevel(int $level): array
    {
        // Return different channels based on escalation level
        // Level 0 = immediate, Level 1 = first escalation, etc.
        return match($level) {
            0 => ['browser', 'email'],
            1 => ['sms', 'whatsapp'],
            2 => ['push', 'slack'],
            default => $this->channels
        };
    }

    public function matchesAlert(string $sourceType = null, string $alertType = null): bool
    {
        $sourceMatches = $this->sourceType === null || $this->sourceType === $sourceType;
        $typeMatches = $this->alertType === null || $this->alertType === $alertType;
        
        return $sourceMatches && $typeMatches;
    }

    public function getEscalationTimeForLevel(int $level): ?int
    {
        return $this->escalationTimes[$level] ?? null;
    }
}