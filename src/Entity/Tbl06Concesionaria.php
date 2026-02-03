<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\Tbl06ConcesionariaRepository;

#[ORM\Table(name: "tbl_06_concesionaria")]
#[ORM\Entity(repositoryClass: Tbl06ConcesionariaRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Tbl06Concesionaria
{
    #[ORM\Column(name: "id_concesionaria", type: "smallint")]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $idConcesionaria = null;

    #[ORM\Column(name: "nombre", type: "string", length: 50, unique: true)]
    private ?string $nombre = null;

    #[ORM\Column(name: "rut_concesionaria", type: "string", length: 10, nullable: true)]
    private ?string $rutConcesionaria = null;

    #[ORM\Column(name: "direccion_concesionaria", type: "text", nullable: true)]
    private ?string $direccionConcesionaria = null;

    #[ORM\ManyToOne(targetEntity: Tbl14Personal::class)]
    #[ORM\JoinColumn(name: "encargado", referencedColumnName: "id_personal")]
    private ?Tbl14Personal $encargado = null;

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

    public function getIdConcesionaria(): ?int
    {
        return $this->idConcesionaria;
    }

    public function __toString(): string
    {
        return $this->nombre ?? 'Sin nombre';
    }

    public function setNombre(?string $nombre): self
    {
        $this->nombre = $nombre;
        return $this;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setRutConcesionaria(?string $rutConcesionaria): self
    {
        $this->rutConcesionaria = $rutConcesionaria;
        return $this;
    }

    public function getRutConcesionaria(): ?string
    {
        return $this->rutConcesionaria;
    }

    public function setDireccionConcesionaria(?string $direccionConcesionaria): self
    {
        $this->direccionConcesionaria = $direccionConcesionaria;
        return $this;
    }

    public function getDireccionConcesionaria(): ?string
    {
        return $this->direccionConcesionaria;
    }

    public function setEncargado(?Tbl14Personal $encargado): self
    {
        $this->encargado = $encargado;
        return $this;
    }

    public function getEncargado(): ?Tbl14Personal
    {
        return $this->encargado;
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
