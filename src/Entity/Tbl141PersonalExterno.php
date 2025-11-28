<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Tbl141PersonalExterno - Personal Externo (Contratistas)
 *
 * @ORM\Table(name="tbl_14_1_personal_externo")
 * @ORM\Entity(repositoryClass="App\Repository\Tbl141PersonalExternoRepository")
 */
#[ORM\Entity]
#[ORM\Table(name: 'tbl_14_1_personal_externo')]
class Tbl141PersonalExterno
{
    /**
     * @var int
     */
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id_personal', type: 'integer')]
    private $idPersonal;

    /**
     * @var string
     */
    #[ORM\Column(name: 'nombres', type: 'string', length: 50)]
    private $nombres;

    /**
     * @var string
     */
    #[ORM\Column(name: 'apellidos', type: 'string', length: 50)]
    private $apellidos;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'RUT', type: 'string', length: 13, nullable: true)]
    private $rut;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'fono', type: 'string', length: 13, nullable: true)]
    private $fono;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'cargo', type: 'string', length: 30, nullable: true)]
    private $cargo;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'correo_electronico', type: 'string', length: 200, nullable: true)]
    private $correoElectronico;

    /**
     * @var int
     */
    #[ORM\Column(name: 'id_proveedor', type: 'smallint')]
    private $idProveedor;

    /**
     * @var bool
     */
    #[ORM\Column(name: 'reg_status', type: 'boolean', options: ['default' => true])]
    private $regStatus = true;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private $createdAt;

    /**
     * @var int
     */
    #[ORM\Column(name: 'created_by', type: 'integer')]
    private $createdBy;

    /**
     * @var \DateTime|null
     */
    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true)]
    private $updatedAt;

    /**
     * @var int|null
     */
    #[ORM\Column(name: 'updated_by', type: 'integer', nullable: true)]
    private $updatedBy;

    /**
     * @var \DateTime|null
     */
    #[ORM\Column(name: 'deleted_restored_at', type: 'datetime', nullable: true)]
    private $deletedRestoredAt;

    /**
     * @var int|null
     */
    #[ORM\Column(name: 'deleted_restored_by', type: 'integer', nullable: true)]
    private $deletedRestoredBy;

    // Getters and Setters

    public function getIdPersonal(): ?int
    {
        return $this->idPersonal;
    }

    public function getNombres(): ?string
    {
        return $this->nombres;
    }

    public function setNombres(string $nombres): self
    {
        $this->nombres = $nombres;
        return $this;
    }

    public function getApellidos(): ?string
    {
        return $this->apellidos;
    }

    public function setApellidos(string $apellidos): self
    {
        $this->apellidos = $apellidos;
        return $this;
    }

    public function getRut(): ?string
    {
        return $this->rut;
    }

    public function setRut(?string $rut): self
    {
        $this->rut = $rut;
        return $this;
    }

    public function getFono(): ?string
    {
        return $this->fono;
    }

    public function setFono(?string $fono): self
    {
        $this->fono = $fono;
        return $this;
    }

    public function getCargo(): ?string
    {
        return $this->cargo;
    }

    public function setCargo(?string $cargo): self
    {
        $this->cargo = $cargo;
        return $this;
    }

    public function getCorreoElectronico(): ?string
    {
        return $this->correoElectronico;
    }

    public function setCorreoElectronico(?string $correoElectronico): self
    {
        $this->correoElectronico = $correoElectronico;
        return $this;
    }

    public function getIdProveedor(): ?int
    {
        return $this->idProveedor;
    }

    public function setIdProveedor(int $idProveedor): self
    {
        $this->idProveedor = $idProveedor;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
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
