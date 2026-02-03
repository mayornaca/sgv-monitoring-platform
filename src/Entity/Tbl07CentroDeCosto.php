<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: "tbl_07_centro_de_costo")]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class Tbl07CentroDeCosto
{
    #[ORM\Column(name: "id_centro_de_costo", type: "integer")]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $idCentroDeCosto = null;

    #[ORM\Column(name: "nombre_centro_de_costo", type: "string", length: 30)]
    private ?string $nombreCentroDeCosto = null;

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

    public function getIdCentroDeCosto(): ?int
    {
        return $this->idCentroDeCosto;
    }

    public function __toString(): string
    {
        return $this->nombreCentroDeCosto ?? 'Sin nombre';
    }

    public function setNombreCentroDeCosto(?string $nombreCentroDeCosto): self
    {
        $this->nombreCentroDeCosto = $nombreCentroDeCosto;
        return $this;
    }

    public function getNombreCentroDeCosto(): ?string
    {
        return $this->nombreCentroDeCosto;
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
