<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * TblCot01TiposDispositivos
 */
#[ORM\Table(name: "tbl_cot_01_tipos_dispositivos")]
#[ORM\Entity]
class TblCot01TiposDispositivos
{
    /**
     * @var integer
     */
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(name: "id", type: "integer")]
    private $id;

    /**
     * @var string
     */
    #[ORM\Column(name: "tipo", type: "string", length: 50, nullable: false)]
    private $tipo;

    /**
     * @var integer
     */
    #[ORM\Column(name: "intervalo", type: "integer", nullable: false)]
    private $intervalo;

    /**
     * @var boolean
     */
    #[ORM\Column(name: "mostrar", type: "boolean", nullable: false)]
    private $mostrar = '1';

    /**
     * @var smallint
     */
    #[ORM\Column(name: "metodo_monitoreo", type: "smallint", nullable: true)]
    private $metodoMonitoreo;

    /**
     * @var smallint
     */
    #[ORM\Column(name: "consultar", type: "smallint", nullable: true)]
    private $consultar;

    /**
     * @var string
     */
    #[ORM\Column(name: "icono", type: "string", length: 25, nullable: true)]
    private $icono;

    /**
     * @var \Tbl06Concesionaria
     */
    #[ORM\ManyToOne(targetEntity: Tbl06Concesionaria::class)]
    #[ORM\JoinColumn(name: "concesionaria", referencedColumnName: "id_concesionaria")]
    private $concesionaria;

    /*      -       -       -       -       -       -       -       -       -       -*/

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getTipo()
    {
        return $this->tipo;
    }

    /**
     * @param string $tipo
     */
    public function setTipo($tipo)
    {
        $this->tipo = $tipo;
    }

    /**
     * @return int
     */
    public function getIntervalo()
    {
        return $this->intervalo;
    }

    /**
     * @param int $intervalo
     */
    public function setIntervalo($intervalo)
    {
        $this->intervalo = $intervalo;
    }

    /**
     * @return bool
     */
    public function isMostrar()
    {
        return $this->mostrar;
    }

    /**
     * @param bool $mostrar
     */
    public function setMostrar($mostrar)
    {
        $this->mostrar = $mostrar;
    }

    /**
     * @return smallint
     */
    public function getMetodoMonitoreo()
    {
        return $this->metodoMonitoreo;
    }

    /**
     * @param smallint $metodoMonitoreo
     */
    public function setMetodoMonitoreo($metodoMonitoreo)
    {
        $this->metodoMonitoreo = $metodoMonitoreo;
    }

    /**
     * @return smallint
     */
    public function getConsultar()
    {
        return $this->consultar;
    }

    /**
     * @param smallint $consultar
     */
    public function setConsultar($consultar)
    {
        $this->consultar = $consultar;
    }

    /**
     * @return string
     */
    public function getIcono()
    {
        return $this->icono;
    }

    /**
     * @param string $icono
     */
    public function setIcono($icono)
    {
        $this->icono = $icono;
    }

    /**
     * Set concesionaria
     *
     * @param \App\Entity\Tbl06Concesionaria $concesionaria
     * @return TblCot01TiposDispositivos
     */
    public function setConcesionaria(\App\Entity\Tbl06Concesionaria $concesionaria = null)
    {
        $this->concesionaria = $concesionaria;

        return $this;
    }

    /**
     * Get concesionaria
     *
     * @return \App\Entity\Tbl06Concesionaria
     */
    public function getConcesionaria()
    {
        return $this->concesionaria;
    }

}