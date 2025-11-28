<?php

namespace App\Entity;

use App\Repository\NotificationLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationLogRepository::class)]
#[ORM\Table(name: 'notification_logs')]
#[ORM\Index(columns: ['alert_id'], name: 'idx_notification_alert')]
#[ORM\Index(columns: ['channel'], name: 'idx_notification_channel')]
#[ORM\Index(columns: ['status'], name: 'idx_notification_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_notification_created')]
class NotificationLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $alertId = null;

    #[ORM\Column(length: 50)]
    private ?string $channel = null;

    #[ORM\Column(length: 255)]
    private ?string $recipient = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $retryCount = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $sentAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deliveredAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $readAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $externalId = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->status = 'pending';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAlertId(): ?int
    {
        return $this->alertId;
    }

    public function setAlertId(int $alertId): static
    {
        $this->alertId = $alertId;
        return $this;
    }

    public function getChannel(): ?string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): static
    {
        $this->channel = $channel;
        return $this;
    }

    public function getRecipient(): ?string
    {
        return $this->recipient;
    }

    public function setRecipient(string $recipient): static
    {
        $this->recipient = $recipient;
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

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;
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

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getRetryCount(): ?int
    {
        return $this->retryCount;
    }

    public function setRetryCount(int $retryCount): static
    {
        $this->retryCount = $retryCount;
        return $this;
    }

    public function incrementRetryCount(): static
    {
        $this->retryCount++;
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

    public function getSentAt(): ?\DateTimeInterface
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeInterface $sentAt): static
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getDeliveredAt(): ?\DateTimeInterface
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeInterface $deliveredAt): static
    {
        $this->deliveredAt = $deliveredAt;
        return $this;
    }

    public function getReadAt(): ?\DateTimeInterface
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeInterface $readAt): static
    {
        $this->readAt = $readAt;
        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): static
    {
        $this->externalId = $externalId;
        return $this;
    }

    // Helper methods
    public function markAsSending(): static
    {
        $this->status = 'sending';
        return $this;
    }

    public function markAsSent(?string $externalId = null): static
    {
        $this->status = 'sent';
        $this->sentAt = new \DateTime();
        if ($externalId) {
            $this->externalId = $externalId;
        }
        return $this;
    }

    public function markAsDelivered(): static
    {
        $this->status = 'delivered';
        $this->deliveredAt = new \DateTime();
        return $this;
    }

    public function markAsRead(): static
    {
        $this->status = 'read';
        $this->readAt = new \DateTime();
        return $this;
    }

    public function markAsFailed(string $errorMessage): static
    {
        $this->status = 'failed';
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function canRetry(int $maxRetries = 3): bool
    {
        return $this->status === 'failed' && $this->retryCount < $maxRetries;
    }

    public function isSuccessful(): bool
    {
        return in_array($this->status, ['sent', 'delivered', 'read']);
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'sending']);
    }

    public function getDeliveryTime(): ?int
    {
        if (!$this->sentAt || !$this->deliveredAt) {
            return null;
        }
        return $this->deliveredAt->getTimestamp() - $this->sentAt->getTimestamp();
    }

    public function getChannelIcon(): string
    {
        return match($this->channel) {
            'email' => 'fa-envelope',
            'sms' => 'fa-mobile',
            'whatsapp' => 'fa-whatsapp',
            'push' => 'fa-bell',
            'slack' => 'fa-slack',
            'browser' => 'fa-browser',
            default => 'fa-paper-plane'
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'sent', 'delivered', 'read' => 'green',
            'sending', 'pending' => 'yellow',
            'failed' => 'red',
            default => 'gray'
        };
    }
}