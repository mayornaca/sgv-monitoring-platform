<?php

namespace App\Entity\WhatsApp;

use App\Repository\WhatsApp\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'whatsapp_messages')]
#[ORM\Index(columns: ['meta_message_id'], name: 'idx_meta_message_id')]
#[ORM\Index(columns: ['estado'], name: 'idx_estado')]
#[ORM\Index(columns: ['created_at'], name: 'idx_created_at')]
#[ORM\HasLifecycleCallbacks]
class Message
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Recipient::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Recipient $recipient = null;

    #[ORM\ManyToOne(targetEntity: Template::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Template $template = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $mensajeTexto = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $parametros = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metaMessageId = null;

    #[ORM\Column(length: 20)]
    private string $estado = self::STATUS_PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metaResponse = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $retryCount = 0;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $context = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $phoneNumberUsed = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipient(): ?Recipient
    {
        return $this->recipient;
    }

    public function setRecipient(?Recipient $recipient): static
    {
        $this->recipient = $recipient;
        return $this;
    }

    public function getTemplate(): ?Template
    {
        return $this->template;
    }

    public function setTemplate(?Template $template): static
    {
        $this->template = $template;
        return $this;
    }

    public function getMensajeTexto(): ?string
    {
        return $this->mensajeTexto;
    }

    public function setMensajeTexto(?string $mensajeTexto): static
    {
        $this->mensajeTexto = $mensajeTexto;
        return $this;
    }

    public function getParametros(): ?array
    {
        return $this->parametros;
    }

    public function setParametros(?array $parametros): static
    {
        $this->parametros = $parametros;
        return $this;
    }

    public function getMetaMessageId(): ?string
    {
        return $this->metaMessageId;
    }

    public function setMetaMessageId(?string $metaMessageId): static
    {
        $this->metaMessageId = $metaMessageId;
        return $this;
    }

    public function getEstado(): string
    {
        return $this->estado;
    }

    public function setEstado(string $estado): static
    {
        $this->estado = $estado;

        // Auto-actualizar timestamps según el estado
        $now = new \DateTimeImmutable();
        match($estado) {
            self::STATUS_SENT => $this->sentAt = $this->sentAt ?? $now,
            self::STATUS_DELIVERED => $this->deliveredAt = $this->deliveredAt ?? $now,
            self::STATUS_READ => $this->readAt = $this->readAt ?? $now,
            default => null
        };

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

    public function getMetaResponse(): ?array
    {
        return $this->metaResponse;
    }

    public function setMetaResponse(?array $metaResponse): static
    {
        $this->metaResponse = $metaResponse;
        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeImmutable $deliveredAt): static
    {
        $this->deliveredAt = $deliveredAt;
        return $this;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeImmutable $readAt): static
    {
        $this->readAt = $readAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getRetryCount(): int
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

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): static
    {
        $this->context = $context;
        return $this;
    }

    public function getPhoneNumberUsed(): ?string
    {
        return $this->phoneNumberUsed;
    }

    public function setPhoneNumberUsed(?string $phoneNumberUsed): static
    {
        $this->phoneNumberUsed = $phoneNumberUsed;
        return $this;
    }

    /**
     * Retorna true si el mensaje fue entregado exitosamente
     */
    public function isDelivered(): bool
    {
        return in_array($this->estado, [self::STATUS_DELIVERED, self::STATUS_READ]);
    }

    /**
     * Retorna true si el mensaje falló
     */
    public function isFailed(): bool
    {
        return $this->estado === self::STATUS_FAILED;
    }

    /**
     * Retorna el tiempo transcurrido desde el envío hasta la entrega (en segundos)
     */
    public function getDeliveryTime(): ?int
    {
        if ($this->sentAt && $this->deliveredAt) {
            return $this->deliveredAt->getTimestamp() - $this->sentAt->getTimestamp();
        }
        return null;
    }

    /**
     * Obtiene el badge de color para el estado (para EasyAdmin)
     */
    public function getEstadoBadge(): string
    {
        return match($this->estado) {
            self::STATUS_PENDING => 'secondary',
            self::STATUS_SENT => 'info',
            self::STATUS_DELIVERED => 'success',
            self::STATUS_READ => 'primary',
            self::STATUS_FAILED => 'danger',
            default => 'secondary'
        };
    }

    public function __toString(): string
    {
        return sprintf(
            '#%d - %s (%s)',
            $this->id ?? 0,
            $this->recipient?->getNombre() ?? 'Sin destinatario',
            $this->estado
        );
    }
}
