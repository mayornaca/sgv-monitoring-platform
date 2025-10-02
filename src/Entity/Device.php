<?php

namespace App\Entity;

use App\Repository\DeviceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\TblCot04Ejes;
use App\Entity\TblCot05Tramos;

#[ORM\Entity(repositoryClass: DeviceRepository::class)]
#[ORM\Table(name: 'tbl_cot_02_dispositivos')]
#[ORM\Index(columns: ['id_tipo'], name: 'idx_device_type')]
#[ORM\Index(columns: ['concesionaria'], name: 'idx_device_concessionaire')]
#[ORM\Index(columns: ['eje'], name: 'idx_device_axis')]
#[ORM\Index(columns: ['tramo'], name: 'idx_device_section')]
#[ORM\Index(columns: ['estado'], name: 'idx_device_status')]
#[ORM\HasLifecycleCallbacks]
class Device
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $idExterno = null;

    #[ORM\ManyToOne(targetEntity: DeviceType::class)]
    #[ORM\JoinColumn(name: 'id_tipo', referencedColumnName: 'id')]
    private ?DeviceType $idTipo = null;

    #[ORM\Column(length: 15, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $orden = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 3, nullable: true)]
    private ?string $km = null;

    #[ORM\ManyToOne(targetEntity: TblCot04Ejes::class)]
    #[ORM\JoinColumn(name: 'eje', referencedColumnName: 'id')]
    private ?TblCot04Ejes $eje = null;

    #[ORM\ManyToOne(targetEntity: TblCot05Tramos::class)]
    #[ORM\JoinColumn(name: 'tramo', referencedColumnName: 'id')]
    private ?TblCot05Tramos $tramo = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $orientacion = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 1])]
    private ?int $estado = 1;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $nFallos = 0;

    #[ORM\Column(options: ['default' => 1])]
    private ?bool $supervisado = true;

    #[ORM\ManyToOne(targetEntity: Tbl06Concesionaria::class)]
    #[ORM\JoinColumn(name: 'concesionaria', referencedColumnName: 'id_concesionaria')]
    private ?Tbl06Concesionaria $concesionaria = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $atributos = null;

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
    private ?\DateTimeInterface $deletedRestoredAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $deletedRestoredBy = null;

    #[ORM\Column(length: 20, options: ['default' => '0'])]
    private ?string $critical = '0';

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // Getters and setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdExterno(): ?string
    {
        return $this->idExterno;
    }

    public function setIdExterno(?string $idExterno): static
    {
        $this->idExterno = $idExterno;
        return $this;
    }

    public function getIdTipo(): ?DeviceType
    {
        return $this->idTipo;
    }

    public function setIdTipo(?DeviceType $idTipo): static
    {
        $this->idTipo = $idTipo;
        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): static
    {
        $this->ip = $ip;
        return $this;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;
        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion;
        return $this;
    }

    public function getOrden(): ?int
    {
        return $this->orden;
    }

    public function setOrden(int $orden): static
    {
        $this->orden = $orden;
        return $this;
    }

    public function getKm(): ?string
    {
        return $this->km;
    }

    public function setKm(?string $km): static
    {
        $this->km = $km;
        return $this;
    }

    public function getEje(): ?TblCot04Ejes
    {
        return $this->eje;
    }

    public function setEje(?TblCot04Ejes $eje): static
    {
        $this->eje = $eje;
        return $this;
    }

    public function getTramo(): ?TblCot05Tramos
    {
        return $this->tramo;
    }

    public function setTramo(?TblCot05Tramos $tramo): static
    {
        $this->tramo = $tramo;
        return $this;
    }

    public function getOrientacion(): ?string
    {
        return $this->orientacion;
    }

    public function setOrientacion(?string $orientacion): static
    {
        $this->orientacion = $orientacion;
        return $this;
    }

    public function getEstado(): ?int
    {
        return $this->estado;
    }

    public function setEstado(int $estado): static
    {
        $this->estado = $estado;
        return $this;
    }

    public function getNFallos(): ?int
    {
        return $this->nFallos;
    }

    public function setNFallos(int $nFallos): static
    {
        $this->nFallos = $nFallos;
        return $this;
    }

    public function isSupervisado(): ?bool
    {
        return $this->supervisado;
    }

    public function setSupervisado(bool $supervisado): static
    {
        $this->supervisado = $supervisado;
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

    public function getAtributos(): ?string
    {
        return $this->atributos;
    }

    public function setAtributos(?string $atributos): static
    {
        $this->atributos = $atributos;
        return $this;
    }

    public function getAtributosAsArray(): ?array
    {
        return $this->atributos ? json_decode($this->atributos, true) : null;
    }

    public function setAtributosFromArray(?array $atributos): static
    {
        $this->atributos = $atributos ? json_encode($atributos) : null;
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

    public function getCritical(): ?string
    {
        return $this->critical;
    }

    public function setCritical(string $critical): static
    {
        $this->critical = $critical;
        return $this;
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->estado === 1 && $this->regStatus === true;
    }

    public function isCritical(): bool
    {
        return $this->critical !== '0' && $this->critical !== null;
    }

    public function getStatusLabel(): string
    {
        return match($this->estado) {
            0 => 'Inactivo',
            1 => 'Activo',
            2 => 'Mantenimiento',
            3 => 'Fuera de servicio',
            default => 'Desconocido'
        };
    }
}