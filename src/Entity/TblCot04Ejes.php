<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * TblCot04Ejes
 */
#[ORM\Table(name: "tbl_cot_04_ejes")]
#[ORM\Entity]
class TblCot04Ejes
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
     * @var \Tbl06Concesionaria
     */
    #[ORM\ManyToOne(targetEntity: Tbl06Concesionaria::class)]
    #[ORM\JoinColumn(name: "concesionaria", referencedColumnName: "id_concesionaria")]
    private $concesionaria;

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

}