<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * TblCot05Tramos
 */
#[ORM\Table(name: "tbl_cot_05_tramos")]
#[ORM\Entity]
class TblCot05Tramos
{
    /**
     * @var integer
     */
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    #[ORM\Column(name: "id", type: "smallint")]
    private $id;

    /**
     * @var string
     */
    #[ORM\Column(name: "nombre", type: "string", length: 50, nullable: false)]
    private $nombre;

    /**
     * @var string
     */
    #[ORM\Column(name: "km_ini", type: "decimal", precision: 15, scale: 3, nullable: false)]
    private $kmIni;

    /**
     * @var string
     */
    #[ORM\Column(name: "km_fin", type: "decimal", precision: 15, scale: 3, nullable: false)]
    private $kmFin;

    /**
     * @var \Tbl06Concesionaria
     */
    #[ORM\ManyToOne(targetEntity: Tbl06Concesionaria::class)]
    #[ORM\JoinColumn(name: "concesionaria", referencedColumnName: "id_concesionaria")]
    private $concesionaria;

    /**
     * @var integer
     */
    #[ORM\Column(name: "cod", type: "smallint")]
    private $cod;

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
    public function getNombre()
    {
        return $this->nombre;
    }

    /**
     * @param string $nombre
     */
    public function setNombre($nombre)
    {
        $this->nombre = $nombre;
    }

    /**
     * @return string
     */
    public function getKmIni()
    {
        return $this->kmIni;
    }

    /**
     * @param string $kmIni
     */
    public function setKmIni($kmIni)
    {
        $this->kmIni = $kmIni;
    }

    /**
     * @return string
     */
    public function getKmFin()
    {
        return $this->kmFin;
    }

    /**
     * @param string $kmFin
     */
    public function setKmFin($kmFin)
    {
        $this->kmFin = $kmFin;
    }

    /**
     * @return \Tbl06Concesionaria
     */
    public function getConcesionaria()
    {
        return $this->concesionaria;
    }

    /**
     * @param \Tbl06Concesionaria $concesionaria
     */
    public function setConcesionaria($concesionaria)
    {
        $this->concesionaria = $concesionaria;
    }

    /**
     * @return string
     */
    public function getCod()
    {
        return $this->cod;
    }

    /**
     * @param int $cod
     */
    public function setCod($cod)
    {
        $this->cod = $cod;
    }


}