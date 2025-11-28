<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'tbl_13_licencias_de_conducir')]
class Tbl13LicenciasDeConducir
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id_licencia', type: Types::SMALLINT)]
    private ?int $idLicencia = null;

    #[ORM\Column(name: 'nombre_licencia', type: Types::STRING, length: 50)]
    private ?string $nombreLicencia = null;

    #[ORM\Column(name: 'alias_licencia', type: Types::STRING, length: 3)]
    private ?string $aliasLicencia = null;

    #[ORM\Column(name: 'duracion_licencia', type: Types::SMALLINT)]
    private ?int $duracionLicencia = null;

    #[ORM\Column(name: 'tipo_licencia', type: Types::STRING, length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['Profesional', 'No Profesional', 'Especiales'])]
    private ?string $tipoLicencia = null;

    #[ORM\Column(name: 'compendio', type: Types::STRING, length: 30)]
    private ?string $compendio = null;

    #[ORM\Column(name: 'reg_status', type: Types::BOOLEAN)]
    private ?bool $regStatus = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(name: 'created_by', type: Types::INTEGER)]
    private ?int $createdBy = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\Column(name: 'updated_by', type: Types::INTEGER, nullable: true)]
    private ?int $updatedBy = null;

    #[ORM\Column(name: 'deleted_restored_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $deletedRestoredAt = null;

    #[ORM\Column(name: 'deleted_restored_by', type: Types::INTEGER, nullable: true)]
    private ?int $deletedRestoredBy = null;

    // Getters and Setters

    public function getIdLicencia(): ?int
    {
        return $this->idLicencia;
    }

    public function setNombreLicencia(?string $nombreLicencia): static
    {
        $this->nombreLicencia = $nombreLicencia;
        return $this;
    }

    public function getNombreLicencia(): ?string
    {
        return $this->nombreLicencia;
    }

    public function setAliasLicencia(?string $aliasLicencia): static
    {
        $this->aliasLicencia = $aliasLicencia;
        return $this;
    }

    public function getAliasLicencia(): ?string
    {
        return $this->aliasLicencia;
    }

    public function setDuracionLicencia(?int $duracionLicencia): static
    {
        $this->duracionLicencia = $duracionLicencia;
        return $this;
    }

    public function getDuracionLicencia(): ?int
    {
        return $this->duracionLicencia;
    }

    public function setTipoLicencia(?string $tipoLicencia): static
    {
        $this->tipoLicencia = $tipoLicencia;
        return $this;
    }

    public function getTipoLicencia(): ?string
    {
        return $this->tipoLicencia;
    }

    public function setCompendio(?string $compendio): static
    {
        $this->compendio = $compendio;
        return $this;
    }

    public function getCompendio(): ?string
    {
        return $this->compendio;
    }

    public function setRegStatus(?bool $regStatus): static
    {
        $this->regStatus = $regStatus;
        return $this;
    }

    public function getRegStatus(): ?bool
    {
        return $this->regStatus;
    }

    public function setCreatedAt(?\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedBy(?int $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedBy(?int $updatedBy): static
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }

    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
    }

    public function setDeletedRestoredAt(?\DateTime $deletedRestoredAt): static
    {
        $this->deletedRestoredAt = $deletedRestoredAt;
        return $this;
    }

    public function getDeletedRestoredAt(): ?\DateTime
    {
        return $this->deletedRestoredAt;
    }

    public function setDeletedRestoredBy(?int $deletedRestoredBy): static
    {
        $this->deletedRestoredBy = $deletedRestoredBy;
        return $this;
    }

    public function getDeletedRestoredBy(): ?int
    {
        return $this->deletedRestoredBy;
    }
}
