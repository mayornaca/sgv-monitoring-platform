<?php

namespace App\Entity\Dashboard;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'vi_lista_llamada_sos')]
class ViListaLlamadasSos
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $sos = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $eje = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $calzada = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $km = null;

    #[ORM\Column(name: 'fecha_hora', type: 'string', length: 255, nullable: true)]
    private ?string $fechaHora = null;

    #[ORM\Column(name: 'fecha_utc', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $fechaUtc = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSos(): ?string
    {
        return $this->sos;
    }

    public function setSos(?string $sos): self
    {
        $this->sos = $sos;
        return $this;
    }

    public function getEje(): ?string
    {
        return $this->eje;
    }

    public function setEje(?string $eje): self
    {
        $this->eje = $eje;
        return $this;
    }

    public function getCalzada(): ?string
    {
        return $this->calzada;
    }

    public function setCalzada(?string $calzada): self
    {
        $this->calzada = $calzada;
        return $this;
    }

    public function getKm(): ?float
    {
        return $this->km;
    }

    public function setKm(?float $km): self
    {
        $this->km = $km;
        return $this;
    }

    public function getFechaHora(): ?string
    {
        return $this->fechaHora;
    }

    public function setFechaHora(?string $fechaHora): self
    {
        $this->fechaHora = $fechaHora;
        return $this;
    }

    public function getFechaUtc(): ?\DateTimeInterface
    {
        return $this->fechaUtc;
    }

    public function setFechaUtc(?\DateTimeInterface $fechaUtc): self
    {
        $this->fechaUtc = $fechaUtc;
        return $this;
    }
}