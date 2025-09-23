<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: "tbl_cot_06_alarmas_dispositivos")]
#[ORM\Entity]
class TblCot06AlarmasDispositivos
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(name: "id", type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Alarms::class)]
    #[ORM\JoinColumn(name: "id_alarma", referencedColumnName: "id_alarma")]
    private ?Alarms $idAlarma = null;

    #[ORM\ManyToOne(targetEntity: TblCot02Dispositivos::class)]
    #[ORM\JoinColumn(name: "id_dispositivo", referencedColumnName: "id")]
    private ?TblCot02Dispositivos $idDispositivo = null;

    #[ORM\Column(name: "estado", type: "smallint", nullable: true)]
    private ?int $estado = null;

    #[ORM\ManyToOne(targetEntity: Tbl06Concesionaria::class)]
    #[ORM\JoinColumn(name: "concesionaria", referencedColumnName: "id_concesionaria")]
    private ?Tbl06Concesionaria $concesionaria = null;

    #[ORM\Column(name: "reg_status", type: "boolean", nullable: false)]
    private bool $regStatus = true;

    #[ORM\Column(name: "created_at", type: "datetime", nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: "created_by", type: "integer", nullable: true)]
    private ?int $createdBy = null;

    #[ORM\Column(name: "updated_at", type: "datetime", nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(name: "updated_by", type: "integer", nullable: true)]
    private ?int $updatedBy = null;

    #[ORM\Column(name: "closed_at", type: "datetime", nullable: true)]
    private ?\DateTimeInterface $closedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "closed_by", referencedColumnName: "id", nullable: true)]
    private ?User $closedBy = null;

    #[ORM\Column(name: "closing_comment", type: "string", length: 255, nullable: true)]
    private ?string $closingComment = null;

    #[ORM\Column(name: "deleted_restored_at", type: "datetime", nullable: true)]
    private ?\DateTimeInterface $deletedRestoredAt = null;

    #[ORM\Column(name: "deleted_restored_by", type: "integer", nullable: true)]
    private ?int $deletedRestoredBy = null;

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdAlarma(): ?Alarms
    {
        return $this->idAlarma;
    }

    public function setIdAlarma(?Alarms $idAlarma): self
    {
        $this->idAlarma = $idAlarma;
        return $this;
    }

    public function getIdDispositivo(): ?TblCot02Dispositivos
    {
        return $this->idDispositivo;
    }

    public function setIdDispositivo(?TblCot02Dispositivos $idDispositivo): self
    {
        $this->idDispositivo = $idDispositivo;
        return $this;
    }

    public function getEstado(): ?int
    {
        return $this->estado;
    }

    public function setEstado(?int $estado): self
    {
        $this->estado = $estado;
        return $this;
    }

    public function getConcesionaria(): ?Tbl06Concesionaria
    {
        return $this->concesionaria;
    }

    public function setConcesionaria(?Tbl06Concesionaria $concesionaria): self
    {
        $this->concesionaria = $concesionaria;
        return $this;
    }

    public function isRegStatus(): bool
    {
        return $this->regStatus;
    }

    public function setRegStatus(bool $regStatus): self
    {
        $this->regStatus = $regStatus;
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

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?int $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?int $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }

    public function getClosedAt(): ?\DateTimeInterface
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeInterface $closedAt): self
    {
        $this->closedAt = $closedAt;
        return $this;
    }

    public function getClosedBy(): ?User
    {
        return $this->closedBy;
    }

    public function setClosedBy(?User $closedBy): self
    {
        $this->closedBy = $closedBy;
        return $this;
    }

    public function getClosingComment(): ?string
    {
        return $this->closingComment;
    }

    public function setClosingComment(?string $closingComment): self
    {
        $this->closingComment = $closingComment;
        return $this;
    }

    public function getDeletedRestoredAt(): ?\DateTimeInterface
    {
        return $this->deletedRestoredAt;
    }

    public function setDeletedRestoredAt(?\DateTimeInterface $deletedRestoredAt): self
    {
        $this->deletedRestoredAt = $deletedRestoredAt;
        return $this;
    }

    public function getDeletedRestoredBy(): ?int
    {
        return $this->deletedRestoredBy;
    }

    public function setDeletedRestoredBy(?int $deletedRestoredBy): self
    {
        $this->deletedRestoredBy = $deletedRestoredBy;
        return $this;
    }
}