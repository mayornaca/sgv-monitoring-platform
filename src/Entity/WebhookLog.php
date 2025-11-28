<?php

namespace App\Entity;

use App\Repository\WebhookLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WebhookLogRepository::class)]
#[ORM\Table(name: 'webhook_log')]
#[ORM\Index(columns: ['source', 'created_at'], name: 'idx_source_created')]
#[ORM\Index(columns: ['processing_status'], name: 'idx_status')]
#[ORM\Index(columns: ['concession_code'], name: 'idx_concession')]
#[ORM\Index(columns: ['created_at'], name: 'idx_created_at')]
#[ORM\Index(columns: ['meta_message_id'], name: 'idx_meta_message_id')]
#[ORM\Index(columns: ['related_entity_type', 'related_entity_id'], name: 'idx_related_entity')]
class WebhookLog
{
    public const SOURCE_ALERTMANAGER = 'alertmanager';
    public const SOURCE_GRAFANA = 'grafana';
    public const SOURCE_PROMETHEUS = 'prometheus';
    public const SOURCE_WHATSAPP_STATUS = 'whatsapp_status';
    public const SOURCE_WHATSAPP_MESSAGE = 'whatsapp_message';
    public const SOURCE_WHATSAPP_ERROR = 'whatsapp_error';
    public const SOURCE_UNKNOWN = 'unknown';

    public const STATUS_RECEIVED = 'received';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $source = null;

    #[ORM\Column(length: 255)]
    private ?string $endpoint = null;

    #[ORM\Column(length: 10)]
    private ?string $method = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $headers = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $rawPayload = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $parsedData = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 20)]
    private ?string $processingStatus = self::STATUS_RECEIVED;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $processingResult = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $retryCount = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $processedAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $concessionCode = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $relatedEntityType = null;

    #[ORM\Column(nullable: true)]
    private ?int $relatedEntityId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metaMessageId = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->processingStatus = self::STATUS_RECEIVED;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;
        return $this;
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function setEndpoint(string $endpoint): static
    {
        $this->endpoint = $endpoint;
        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(string $method): static
    {
        $this->method = $method;
        return $this;
    }

    public function getHeaders(): ?array
    {
        return $this->headers;
    }

    public function setHeaders(?array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    public function getRawPayload(): ?string
    {
        return $this->rawPayload;
    }

    public function setRawPayload(string $rawPayload): static
    {
        $this->rawPayload = $rawPayload;
        return $this;
    }

    public function getParsedData(): ?array
    {
        return $this->parsedData;
    }

    public function setParsedData(?array $parsedData): static
    {
        $this->parsedData = $parsedData;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getProcessingStatus(): ?string
    {
        return $this->processingStatus;
    }

    public function setProcessingStatus(string $processingStatus): static
    {
        $this->processingStatus = $processingStatus;
        return $this;
    }

    public function getProcessingResult(): ?array
    {
        return $this->processingResult;
    }

    public function setProcessingResult(?array $processingResult): static
    {
        $this->processingResult = $processingResult;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getProcessedAt(): ?\DateTimeInterface
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeInterface $processedAt): static
    {
        $this->processedAt = $processedAt;
        return $this;
    }

    public function getConcessionCode(): ?string
    {
        return $this->concessionCode;
    }

    public function setConcessionCode(?string $concessionCode): static
    {
        $this->concessionCode = $concessionCode;
        return $this;
    }

    public function getRelatedEntityType(): ?string
    {
        return $this->relatedEntityType;
    }

    public function setRelatedEntityType(?string $relatedEntityType): static
    {
        $this->relatedEntityType = $relatedEntityType;
        return $this;
    }

    public function getRelatedEntityId(): ?int
    {
        return $this->relatedEntityId;
    }

    public function setRelatedEntityId(?int $relatedEntityId): static
    {
        $this->relatedEntityId = $relatedEntityId;
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

    /**
     * Correlaciona el webhook con una entidad relacionada
     */
    public function setRelatedEntity(string $type, int $id): static
    {
        $this->relatedEntityType = $type;
        $this->relatedEntityId = $id;
        return $this;
    }

    /**
     * Marca el webhook como procesado exitosamente
     */
    public function markAsCompleted(?array $result = null): static
    {
        $this->processingStatus = self::STATUS_COMPLETED;
        $this->processedAt = new \DateTime();
        if ($result !== null) {
            $this->processingResult = $result;
        }
        return $this;
    }

    /**
     * Marca el webhook como fallido
     */
    public function markAsFailed(string $errorMessage, ?array $result = null): static
    {
        $this->processingStatus = self::STATUS_FAILED;
        $this->processedAt = new \DateTime();
        $this->errorMessage = $errorMessage;
        if ($result !== null) {
            $this->processingResult = $result;
        }
        return $this;
    }

    /**
     * Marca el webhook como en procesamiento
     */
    public function markAsProcessing(): static
    {
        $this->processingStatus = self::STATUS_PROCESSING;
        return $this;
    }

    /**
     * Verifica si el webhook puede ser reintentado
     */
    public function canRetry(int $maxRetries = 3): bool
    {
        return $this->processingStatus === self::STATUS_FAILED && $this->retryCount < $maxRetries;
    }

    /**
     * Obtiene el payload parseado como array
     */
    public function getRawPayloadAsArray(): ?array
    {
        if ($this->rawPayload === null) {
            return null;
        }

        $decoded = json_decode($this->rawPayload, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /**
     * Obtiene un resumen corto para mostrar en listados
     */
    public function getShortSummary(): string
    {
        $data = $this->parsedData ?? $this->getRawPayloadAsArray();

        if ($data === null) {
            return 'Invalid payload';
        }

        switch ($this->source) {
            case self::SOURCE_ALERTMANAGER:
            case self::SOURCE_PROMETHEUS:
            case self::SOURCE_GRAFANA:
                $alertCount = isset($data['alerts']) ? count($data['alerts']) : 0;
                return sprintf('%d alert(s)', $alertCount);

            case self::SOURCE_WHATSAPP_STATUS:
                $statusCount = 0;
                foreach ($data['entry'] ?? [] as $entry) {
                    foreach ($entry['changes'] ?? [] as $change) {
                        $statusCount += count($change['value']['statuses'] ?? []);
                    }
                }
                return sprintf('%d status update(s)', $statusCount);

            case self::SOURCE_WHATSAPP_MESSAGE:
                $messageCount = 0;
                foreach ($data['entry'] ?? [] as $entry) {
                    foreach ($entry['changes'] ?? [] as $change) {
                        $messageCount += count($change['value']['messages'] ?? []);
                    }
                }
                return sprintf('%d message(s)', $messageCount);

            default:
                return 'Unknown format';
        }
    }
}
