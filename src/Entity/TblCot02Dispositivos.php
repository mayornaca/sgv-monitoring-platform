<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * TblCot02Dispositivos
 */
#[ORM\Table(name: "tbl_cot_02_dispositivos")]
#[ORM\Entity]
class TblCot02Dispositivos
{
    ///**
    // * #[ORM\OneToMany(targetEntity="App\Entity\Tbl30DetallesComprasCombustibles", mappedBy="TblCot02Dispositivos", cascade={"persist", "remove"})]
    // *     // */
   // protected $Tbl30DetallesComprasCombustibles;

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
    #[ORM\Column(name: "id_externo", type: "string", length: 50, nullable: true)]
    private $idExterno;

    /**
     * @var \TblCot01TiposDispositivos
     */
    #[ORM\ManyToOne(targetEntity: TblCot01TiposDispositivos::class)]
    #[ORM\JoinColumn(name: "id_tipo", referencedColumnName: "id")]
    private $idTipo;

    /**
     * @var string
     */
    #[ORM\Column(name: "ip", type: "string", length: 15, nullable: true)]
    private $ip;

    /**
     * @var string
     */
    #[ORM\Column(name: "nombre", type: "string", length: 255, nullable: false)]
    private $nombre;

    /**
     * @var string
     */
    #[ORM\Column(name: "descripcion", type: "string", length: 15, nullable: true)]
    private $descripcion;

    /**
     * @var integer
     */
    #[ORM\Column(name: "orden", type: "integer", nullable: false)]
    private $orden;

    /**
     * @var float
     */
    #[ORM\Column(name: "km", type: "float", precision: 5, scale: 2, nullable: true)]
    private $km;

    /**
     * @var \TblCot04Ejes
     */
    #[ORM\ManyToOne(targetEntity: TblCot04Ejes::class)]
    #[ORM\JoinColumn(name: "eje", referencedColumnName: "id")]
    private $eje;

    /**
     * @var \TblCot05Tramos
     */
    #[ORM\ManyToOne(targetEntity: TblCot05Tramos::class)]
    #[ORM\JoinColumn(name: "tramo", referencedColumnName: "id")]
    private $tramo;

    /**
     * @var string
     */
    #[ORM\Column(name: "orientacion", type: "string", length: 2, nullable: true)]
    private $orientacion;

    /**
     * @var smallint
     */
    #[ORM\Column(name: "estado", type: "smallint", nullable: true)]
    private $estado;

    /**
     * @var integer
     */
    #[ORM\Column(name: "n_fallos", type: "integer", nullable: true)]
    private $nFallos;
    
    /**
     * @var smallint
     */
    #[ORM\Column(name: "supervisado", type: "smallint", nullable: true)]
    private $supervisado;

    /**
     * @var \Tbl06Concesionaria
     *
     */
    #[ORM\ManyToOne(targetEntity: "App\Entity\Tbl06Concesionaria")]
    #[ORM\JoinColumn(name: "concesionaria", referencedColumnName: "id_concesionaria")]
    private $concesionaria;

    /**
     * @var string
     */
    #[ORM\Column(name: "atributos", type: "text", nullable: true)]
    private $atributos;

    /**
     * @var boolean
     */
    #[ORM\Column(name: "reg_status", type: "boolean", nullable: false)]
    private $regStatus = '1';

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: "created_at", type: "datetime", nullable: false)]
    private $createdAt;

    /**
     * @var integer
     */
    #[ORM\Column(name: "created_by", type: "integer", nullable: false)]
    private $createdBy;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: "updated_at", type: "datetime", nullable: false)]
    private $updatedAt;

    /**
     * @var integer
     */
    #[ORM\Column(name: "updated_by", type: "integer", nullable: false)]
    private $updatedBy;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: "deleted_restored_at", type: "datetime", nullable: false)]
    private $deletedRestoredAt;

    /**
     * @var integer
     */
    #[ORM\Column(name: "deleted_restored_by", type: "integer", nullable: false)]
    private $deletedRestoredBy;
    /*  -   -   -   -   -   -   -   -   -   -   -   -   -   -   -   -   -   -   -   -    */
    /**
     * Constructor
     */
    public function __construct()
    {
        //$this->Tbl30DetallesComprasCombustibles = new ArrayCollection();
    }
    /*  -   -   -   -   -   -   -   -   -   -   -   -   -   -   -   -   -   -   -   -    */
    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return \TblCot01TiposDispositivos
     */
    public function getIdTipo()
    {
        return $this->idTipo;
    }

    /**
     * @param \TblCot01TiposDispositivos $idTipo
     */
    public function setIdTipo($idTipo)
    {
        $this->idTipo = $idTipo;
    }

    /**
     * @return string
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * @param string $ip
     */
    public function setIp($ip)
    {
        $this->ip = $ip;
    }
    
    /**
     * Set nombre
     *
     * @param string $nombre
     *
     * @return TblCot02Dispositivos
     */
    public function setNombre($nombre)
    {
        $this->nombre = $nombre;

        return $this;
    }

    /**
     * Get nombre
     *
     * @return string
     */
    public function getNombre()
    {
        return $this->nombre;
    }

    /**
     * @return string
     */
    public function getDescripcion()
    {
        return $this->descripcion;
    }

    /**
     * @param string $descripcion
     */
    public function setDescripcion($descripcion)
    {
        $this->descripcion = $descripcion;
    }

    /**
     * @return float
     */
    public function getKm()
    {
        return $this->km;
    }

    /**
     * @param float $km
     */
    public function setKm($km)
    {
        $this->km = $km;
    }

 /*   /**
     * @return smallint
     */
    /*public function getEje()
    {
        return $this->eje;
    }*/

 /*   /**
     * @param smallint $eje
     */
 /*   public function setEje($eje)
    {
        $this->eje = $eje;
    }
*/
    /**
     * Set eje
     *
     * @param \App\Entity\TblCot04Ejes $eje
     * @return TblCot02Dispositivos
     */
    public function setEje(\App\Entity\TblCot04Ejes $eje = null)
    {
        $this->eje = $eje;

        return $this;
    }

    /**
     * Get eje
     *
     * @return \App\Entity\TblCot04Ejes
     */
    public function getEje()
    {
        return $this->eje;
    }

    /**
     * Set tramo
     *
     * @param \App\Entity\TblCot05Tramos $tramo
     * @return TblCot02Dispositivos
     */
    public function setTramo(\App\Entity\TblCot05Tramos $tramo = null)
    {
        $this->tramo = $tramo;

        return $this;
    }

    /**
     * Get tramo
     *
     * @return \App\Entity\TblCot05Tramos
     */
    public function getTramo()
    {
        return $this->tramo;
    }

    /**
     * @return string
     */
    public function getOrientacion()
    {
        return $this->orientacion;
    }

    /**
     * @param string $orientacion
     */
    public function setOrientacion($orientacion)
    {
        $this->orientacion = $orientacion;
    }

    /**
     * Set estado
     *
     * @param smallint $estado
     *
     * @return TblCot02Dispositivos
     */
    public function setEstado($estado)
    {
        $this->estado = $estado;

        return $this;
    }

    /**
     * Get estado
     *
     * @return smallint
     */
    public function getEstado()
    {
        return $this->estado;
    }

    /**
     * Set supervisado
     *
     * @param smallint $supervisado
     *
     * @return TblCot02Dispositivos
     */
    public function setSupervisado($supervisado)
    {
        $this->supervisado = $supervisado;

        return $this;
    }

    /**
     * Get supervisado
     *
     * @return smallint
     */
    public function getSupervisado()
    {
        return $this->supervisado;
    }
    
    /**
     * Set concesionaria
     *
     * @param \App\Entity\Tbl06Concesionaria $concesionaria
     * @return TblCot02Dispositivos
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

    /**
     * Set atributos
     *
     * @param string $atributos
     *
     * @return TblCot02Dispositivos
     */
    public function setAtributos($atributos)
    {
        $this->atributos = $atributos;

        return $this;
    }

    /**
     * Get atributos
     *
     * @return string
     */
    public function getAtributos()
    {
        return $this->atributos;
    }

    /**
     * Set regStatus
     *
     * @param boolean $regStatus
     *
     * @return TblCot02Dispositivos
     */
    public function setRegStatus($regStatus)
    {
        $this->regStatus = $regStatus;

        return $this;
    }

    /**
     * Get regStatus
     *
     * @return boolean
     */
    public function getRegStatus()
    {
        return $this->regStatus;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     *
     * @return TblCot02Dispositivos
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set createdBy
     *
     * @param integer $createdBy
     *
     * @return TblCot02Dispositivos
     */
    public function setCreatedBy($createdBy)
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    /**
     * Get createdBy
     *
     * @return integer
     */
    public function getCreatedBy()
    {
        return $this->createdBy;
    }

    /**
     * Set updatedAt
     *
     * @param \DateTime $updatedAt
     *
     * @return TblCot02Dispositivos
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Set updatedBy
     *
     * @param integer $updatedBy
     *
     * @return TblCot02Dispositivos
     */
    public function setUpdatedBy($updatedBy)
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    /**
     * Get updatedBy
     *
     * @return integer
     */
    public function getUpdatedBy()
    {
        return $this->updatedBy;
    }

    /**
     * Set deletedRestoredAt
     *
     * @param \DateTime $deletedRestoredAt
     *
     * @return TblCot02Dispositivos
     */
    public function setDeletedRestoredAt($deletedRestoredAt)
    {
        $this->deletedRestoredAt = $deletedRestoredAt;

        return $this;
    }

    /**
     * Get deletedRestoredAt
     *
     * @return \DateTime
     */
    public function getDeletedRestoredAt()
    {
        return $this->deletedRestoredAt;
    }

    /**
     * Set deletedRestoredBy
     *
     * @param integer $deletedRestoredBy
     *
     * @return TblCot02Dispositivos
     */
    public function setDeletedRestoredBy($deletedRestoredBy)
    {
        $this->deletedRestoredBy = $deletedRestoredBy;

        return $this;
    }

    /**
     * Get deletedRestoredBy
     *
     * @return integer
     */
    public function getDeletedRestoredBy()
    {
        return $this->deletedRestoredBy;
    }

    //======================================================================================================================

    /**
     * @return string
     */
    public function getIdExterno()
    {
        return $this->idExterno;
    }

    /**
     * @param string $idExterno
     */
    public function setIdExterno($idExterno)
    {
        $this->idExterno = $idExterno;
    }

    /**
     * @return int
     */
    public function getNFallos()
    {
        return $this->nFallos;
    }

    /**
     * @param int $nFallos
     */
    public function setNFallos($nFallos)
    {
        $this->nFallos = $nFallos;
    }

    /**
     * @return int
     */
    public function getOrden()
    {
        return $this->orden;
    }

    /**
     * @param int $orden
     */
    public function setOrden($orden)
    {
        $this->orden = $orden;
    }


//======================================================================================================================


}
