<?php

namespace App\Entity;

use App\Repository\DeviceTypeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeviceTypeRepository::class)]
#[ORM\Table(name: 'tbl_cot_01_tipos_dispositivos')]
class DeviceType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $tipo = null;

    #[ORM\Column]
    private ?int $intervalo = null;

    #[ORM\Column]
    private ?bool $mostrar = true;

    #[ORM\Column(type: 'smallint')]
    private ?int $metodoMonitoreo = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 1])]
    private ?int $consultar = 1;

    #[ORM\Column(length: 25, options: ['default' => 'fa-newspaper-o'])]
    private ?string $icono = 'fa-newspaper-o';

    #[ORM\Column(type: 'smallint')]
    private ?int $concesionaria = null;

    // TODO(human): Add relationships with Device, Alarms, and other COT entities
    // Consider OneToMany relationship with Device entity
    // Consider relationship with Concessionaires if we create that entity

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTipo(): ?string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): static
    {
        $this->tipo = $tipo;
        return $this;
    }

    public function getIntervalo(): ?int
    {
        return $this->intervalo;
    }

    public function setIntervalo(int $intervalo): static
    {
        $this->intervalo = $intervalo;
        return $this;
    }

    public function isMostrar(): ?bool
    {
        return $this->mostrar;
    }

    public function setMostrar(bool $mostrar): static
    {
        $this->mostrar = $mostrar;
        return $this;
    }

    public function getMetodoMonitoreo(): ?int
    {
        return $this->metodoMonitoreo;
    }

    public function setMetodoMonitoreo(int $metodoMonitoreo): static
    {
        $this->metodoMonitoreo = $metodoMonitoreo;
        return $this;
    }

    public function getConsultar(): ?int
    {
        return $this->consultar;
    }

    public function setConsultar(int $consultar): static
    {
        $this->consultar = $consultar;
        return $this;
    }

    public function getIcono(): ?string
    {
        return $this->icono;
    }

    public function setIcono(string $icono): static
    {
        $this->icono = $icono;
        return $this;
    }

    public function getConcesionaria(): ?int
    {
        return $this->concesionaria;
    }

    public function setConcesionaria(int $concesionaria): static
    {
        $this->concesionaria = $concesionaria;
        return $this;
    }
}