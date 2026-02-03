<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: "tbl_18_dispositivos_gps")]
#[ORM\Entity]
class Tbl18DispositivosGps
{
    #[ORM\Column(name: "id_dispositivo_gps", type: "smallint")]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $idDispositivoGps = null;

    #[ORM\Column(name: "imei", type: "string", length: 15, nullable: true)]
    private ?string $imei = null;

    #[ORM\Column(name: "nro_celular_sim", type: "string", length: 10, nullable: true)]
    private ?string $nroCelularSim = null;

    #[ORM\Column(name: "estado", type: "boolean", nullable: true)]
    private ?bool $estado = null;

    #[ORM\Column(name: "marca", type: "string", nullable: true)]
    private ?string $marca = null;

    #[ORM\Column(name: "modelo", type: "string", nullable: true)]
    private ?string $modelo = null;

    #[ORM\Column(name: "fecha_hora_posicion", type: "datetime", nullable: true)]
    private ?\DateTime $fechaHoraPosicion = null;

    #[ORM\Column(name: "latitud", type: "float", nullable: true)]
    private ?float $latitud = null;

    #[ORM\Column(name: "longitud", type: "float", nullable: true)]
    private ?float $longitud = null;

    #[ORM\Column(name: "altitud", type: "smallint", nullable: true)]
    private ?int $altitud = null;

    #[ORM\Column(name: "velocidad", type: "smallint", nullable: true)]
    private ?int $velocidad = null;

    #[ORM\Column(name: "curso", type: "string", length: 4, nullable: true)]
    private ?string $curso = null;

    #[ORM\Column(name: "gps_info_alarm", type: "string", length: 30, nullable: true)]
    private ?string $gpsInfoAlarm = null;

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

    public function getIdDispositivoGps(): ?int
    {
        return $this->idDispositivoGps;
    }

    public function setImei(?string $imei): self
    {
        $this->imei = $imei;
        return $this;
    }

    public function getImei(): ?string
    {
        return $this->imei;
    }

    public function setNroCelularSim(?string $nroCelularSim): self
    {
        $this->nroCelularSim = $nroCelularSim;
        return $this;
    }

    public function getNroCelularSim(): ?string
    {
        return $this->nroCelularSim;
    }

    public function setEstado(?bool $estado): self
    {
        $this->estado = $estado;
        return $this;
    }

    public function getEstado(): ?bool
    {
        return $this->estado;
    }

    public function setMarca(?string $marca): self
    {
        $this->marca = $marca;
        return $this;
    }

    public function getMarca(): ?string
    {
        return $this->marca;
    }

    public function setModelo(?string $modelo): self
    {
        $this->modelo = $modelo;
        return $this;
    }

    public function getModelo(): ?string
    {
        return $this->modelo;
    }

    public function setFechaHoraPosicion(?\DateTime $fechaHoraPosicion): self
    {
        $this->fechaHoraPosicion = $fechaHoraPosicion;
        return $this;
    }

    public function getFechaHoraPosicion(): ?\DateTime
    {
        return $this->fechaHoraPosicion;
    }

    public function setLatitud(?float $latitud): self
    {
        $this->latitud = $latitud;
        return $this;
    }

    public function getLatitud(): ?float
    {
        return $this->latitud;
    }

    public function setLongitud(?float $longitud): self
    {
        $this->longitud = $longitud;
        return $this;
    }

    public function getLongitud(): ?float
    {
        return $this->longitud;
    }

    public function getPosition(): string
    {
        return $this->latitud . "," . $this->longitud;
    }

    public function setAltitud(?int $altitud): self
    {
        $this->altitud = $altitud;
        return $this;
    }

    public function getAltitud(): ?int
    {
        return $this->altitud;
    }

    public function setVelocidad(?int $velocidad): self
    {
        $this->velocidad = $velocidad;
        return $this;
    }

    public function getVelocidad(): ?int
    {
        return $this->velocidad;
    }

    public function setCurso(?string $curso): self
    {
        $this->curso = $curso;
        return $this;
    }

    public function getCurso(): ?string
    {
        return $this->curso;
    }

    public function setGpsInfoAlarm(?string $gpsInfoAlarm): self
    {
        $this->gpsInfoAlarm = $gpsInfoAlarm;
        return $this;
    }

    public function getGpsInfoAlarm(): ?string
    {
        return $this->gpsInfoAlarm;
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
