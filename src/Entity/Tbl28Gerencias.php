<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: "tbl_28_gerencias")]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class Tbl28Gerencias
{
    #[ORM\Column(name: "id_gerencia", type: "integer")]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $idGerencia = null;

    #[ORM\Column(name: "nombre_gerencia", type: "string", length: 50)]
    private ?string $nombreGerencia = null;

    #[ORM\Column(name: "reg_status", type: "boolean", nullable: true)]
    private ?bool $regStatus = null;

    #[ORM\Column(name: "created_at", type: "datetime", nullable: true)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(name: "created_by", type: "integer", nullable: true)]
    private ?int $createdBy = null;

    #[ORM\Column(name: "updated_at", type: "datetime", nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\Column(name: "updated_by", type: "integer", nullable: true)]
    private ?int $updatedBy = null;

    #[ORM\Column(name: "deleted_restored_at", type: "datetime", nullable: true)]
    private ?\DateTime $deletedRestoredAt = null;

    #[ORM\Column(name: "deleted_restored_by", type: "integer", nullable: true)]
    private ?int $deletedRestoredBy = null;

    public function getIdGerencia(): ?int
    {
        return $this->idGerencia;
    }

    public function __toString(): string
    {
        return $this->nombreGerencia ?? 'Sin nombre';
    }

    public function setNombreGerencia(?string $nombreGerencia): self
    {
        $this->nombreGerencia = $nombreGerencia;
        return $this;
    }

    public function getNombreGerencia(): ?string
    {
        return $this->nombreGerencia;
    }

    public function setRegStatus(?bool $regStatus): self
    {
        $this->regStatus = $regStatus;
        return $this;
    }

    public function getRegStatus(): ?bool
    {
        return $this->regStatus;
    }

    public function setCreatedAt(?\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedBy(?int $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedBy(?int $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }

    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
    }

    public function setDeletedRestoredAt(?\DateTime $deletedRestoredAt): self
    {
        $this->deletedRestoredAt = $deletedRestoredAt;
        return $this;
    }

    public function getDeletedRestoredAt(): ?\DateTime
    {
        return $this->deletedRestoredAt;
    }

    public function setDeletedRestoredBy(?int $deletedRestoredBy): self
    {
        $this->deletedRestoredBy = $deletedRestoredBy;
        return $this;
    }

    public function getDeletedRestoredBy(): ?int
    {
        return $this->deletedRestoredBy;
    }
}
