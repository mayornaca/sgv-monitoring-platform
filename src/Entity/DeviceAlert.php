<?php

namespace App\Entity;

use App\Repository\DeviceAlertRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeviceAlertRepository::class)]
#[ORM\Table(name: 'tbl_cot_06_alarmas_dispositivos')]
#[ORM\Index(columns: ['id_dispositivo'], name: 'idx_alert_device')]
#[ORM\Index(columns: ['estado'], name: 'idx_alert_status')]
#[ORM\Index(columns: ['concesionaria'], name: 'idx_alert_concessionaire')]
#[ORM\Index(columns: ['created_at'], name: 'idx_alert_created')]
#[ORM\HasLifecycleCallbacks]
class DeviceAlert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Alarms::class)]
    #[ORM\JoinColumn(name: 'id_alarma', referencedColumnName: 'id_alarma')]
    private ?Alarms $idAlarma = null;

    #[ORM\ManyToOne(targetEntity: Device::class)]
    #[ORM\JoinColumn(name: 'id_dispositivo', referencedColumnName: 'id')]
    private ?Device $idDispositivo = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $estado = null;

    #[ORM\ManyToOne(targetEntity: Tbl06Concesionaria::class)]
    #[ORM\JoinColumn(name: 'concesionaria', referencedColumnName: 'id_concesionaria')]
    private ?Tbl06Concesionaria $concesionaria = null;

    #[ORM\Column(options: ['default' => 1])]
    private ?bool $regStatus = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column]
    private ?int $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $updatedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $closedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $closedBy = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $closingComment = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedRestoredAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $deletedRestoredBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->estado = false; // false = active alert, true = resolved
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdAlarma(): ?Alarms
    {
        return $this->idAlarma;
    }

    public function setIdAlarma(?Alarms $idAlarma): static
    {
        $this->idAlarma = $idAlarma;
        return $this;
    }

    public function getIdDispositivo(): ?Device
    {
        return $this->idDispositivo;
    }

    public function setIdDispositivo(?Device $idDispositivo): static
    {
        $this->idDispositivo = $idDispositivo;
        return $this;
    }

    public function isEstado(): ?bool
    {
        return $this->estado;
    }

    public function setEstado(bool $estado): static
    {
        $this->estado = $estado;
        return $this;
    }

    public function getConcesionaria(): ?Tbl06Concesionaria
    {
        return $this->concesionaria;
    }

    public function setConcesionaria(?Tbl06Concesionaria $concesionaria): static
    {
        $this->concesionaria = $concesionaria;
        return $this;
    }

    public function isRegStatus(): ?bool
    {
        return $this->regStatus;
    }

    public function setRegStatus(bool $regStatus): static
    {
        $this->regStatus = $regStatus;
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

    public function getClosedAt(): ?\DateTimeInterface
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeInterface $closedAt): static
    {
        $this->closedAt = $closedAt;
        return $this;
    }

    public function getClosedBy(): ?int
    {
        return $this->closedBy;
    }

    public function setClosedBy(?int $closedBy): static
    {
        $this->closedBy = $closedBy;
        return $this;
    }

    public function getClosingComment(): ?string
    {
        return $this->closingComment;
    }

    public function setClosingComment(?string $closingComment): static
    {
        $this->closingComment = $closingComment;
        return $this;
    }

    public function getDeletedRestoredAt(): ?\DateTimeInterface
    {
        return $this->deletedRestoredAt;
    }

    public function setDeletedRestoredAt(?\DateTimeInterface $deletedRestoredAt): static
    {
        $this->deletedRestoredAt = $deletedRestoredAt;
        return $this;
    }

    public function getDeletedRestoredBy(): ?int
    {
        return $this->deletedRestoredBy;
    }

    public function setDeletedRestoredBy(?int $deletedRestoredBy): static
    {
        $this->deletedRestoredBy = $deletedRestoredBy;
        return $this;
    }

    // Helper methods for the notification system
    public function isActive(): bool
    {
        return $this->estado === false && $this->regStatus === true && $this->closedAt === null;
    }

    public function isResolved(): bool
    {
        return $this->estado === true || $this->closedAt !== null;
    }

    public function close(int $closedByUserId, string $comment = null): static
    {
        $this->estado = true;
        $this->closedAt = new \DateTime();
        $this->closedBy = $closedByUserId;
        $this->closingComment = $comment;
        return $this;
    }

    public function getAgeInMinutes(): int
    {
        $now = new \DateTime();
        $diff = $now->diff($this->createdAt);
        return ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
    }

    public function getPriorityLevel(): string
    {
        $age = $this->getAgeInMinutes();
        
        return match(true) {
            $age >= 60 => 'critical',    // 1+ hours unresolved
            $age >= 30 => 'high',        // 30+ minutes unresolved  
            $age >= 15 => 'medium',      // 15+ minutes unresolved
            default => 'low'             // Less than 15 minutes
        };
    }

    public function shouldEscalate(int $escalationTimeMinutes): bool
    {
        return $this->isActive() && $this->getAgeInMinutes() >= $escalationTimeMinutes;
    }
}