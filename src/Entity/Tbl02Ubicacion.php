<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Tbl02Ubicacion - Ubicaciones para Permisos de Trabajo
 *
 * @ORM\Table(name="tbl_ot_02_ubicacion")
 * @ORM\Entity
 */
#[ORM\Entity]
#[ORM\Table(name: 'tbl_ot_02_ubicacion')]
class Tbl02Ubicacion
{
    /**
     * @var int
     */
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private $id;

    /**
     * @var int|null
     */
    #[ORM\Column(name: 'parent_id', type: 'integer', nullable: true)]
    private $parentId;

    /**
     * @var string
     */
    #[ORM\Column(name: 'nombre', type: 'string', length: 50)]
    private $nombre;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'descripcion', type: 'string', length: 250, nullable: true)]
    private $descripcion;

    /**
     * @var bool
     */
    #[ORM\Column(name: 'concesionaria', type: 'boolean')]
    private $concesionaria;

    /**
     * @var bool
     */
    #[ORM\Column(name: 'reg_status', type: 'boolean', options: ['default' => true])]
    private $regStatus = true;

    /**
     * @var int
     */
    #[ORM\Column(name: 'created_by', type: 'integer')]
    private $createdBy;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private $createdAt;

    /**
     * @var int|null
     */
    #[ORM\Column(name: 'updated_by', type: 'integer', nullable: true)]
    private $updatedBy;

    /**
     * @var \DateTime|null
     */
    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true)]
    private $updatedAt;

    /**
     * @var int|null
     */
    #[ORM\Column(name: 'deleted_restored_by', type: 'integer', nullable: true)]
    private $deletedRestoredBy;

    /**
     * @var \DateTime|null
     */
    #[ORM\Column(name: 'deleted_restored_at', type: 'datetime', nullable: true)]
    private $deletedRestoredAt;

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function setParentId(?int $parentId): self
    {
        $this->parentId = $parentId;
        return $this;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): self
    {
        $this->nombre = $nombre;
        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): self
    {
        $this->descripcion = $descripcion;
        return $this;
    }

    public function getConcesionaria(): ?bool
    {
        return $this->concesionaria;
    }

    public function setConcesionaria(bool $concesionaria): self
    {
        $this->concesionaria = $concesionaria;
        return $this;
    }

    public function getRegStatus(): ?bool
    {
        return $this->regStatus;
    }

    public function setRegStatus(bool $regStatus): self
    {
        $this->regStatus = $regStatus;
        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(int $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
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

    public function getDeletedRestoredAt(): ?\DateTimeInterface
    {
        return $this->deletedRestoredAt;
    }

    public function setDeletedRestoredAt(?\DateTimeInterface $deletedRestoredAt): self
    {
        $this->deletedRestoredAt = $deletedRestoredAt;
        return $this;
    }
}
