<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Doctrine\Common\Collections\Collection;

/**
 * Tbl14Personal
 *
 * @UniqueEntity(
 *     fields={"rut"},
 *     errorPath="rut",
 *     message="Rut ya existe, no se ha grabado el registro."
 * )
 */
#[ORM\Table(name: "tbl_14_personal")]
#[ORM\Index(name: "id_superior_directo", columns: ["id_superior_directo"])]
#[ORM\Index(name: "id_area", columns: ["id_area"])]
#[ORM\Index(name: "id_centro_de_costo", columns: ["id_centro_de_costo"])]
#[ORM\Index(name: "id_concesionaria", columns: ["id_concesionaria"])]
#[ORM\Entity(repositoryClass: "App\Repository\Tbl14PersonalRepository")]
class Tbl14Personal
{
    #[ORM\OneToMany(targetEntity: "App\Entity\Tbl14Personal", mappedBy: "parent")]
    protected $children;
    //    //Object(Symfony\Component\Form\Form).data.Tbl24ConductoresAsignados[0].Vehiculos.Tbl24ConductoresAsignados[1].Tbl14Personal.fechaEmisionLicencia = null // Modified: removed vehicle reference

    #[ORM\ManyToOne(targetEntity: "App\Entity\Tbl14Personal", inversedBy: "children")]
    #[ORM\JoinColumn(name: "id_superior_directo", referencedColumnName: "id_personal")]
    private $parent;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    /**
     * @var integer
     */
    #[ORM\Column(name: "id_personal", type: "integer", nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private $idPersonal;

    /**
     * @var string
     */
    #[ORM\Column(name: "nombres", type: "string", nullable: false)]
    private $nombres;

    /**
     * @var string
     */
    #[ORM\Column(name: "apellidos", type: "string", nullable: false)]
    private $apellidos;

    /**
     * @var string
     */
    #[ORM\Column(name: "rut", type: "string", length: 12, nullable: false, unique: true)]
    private $rut;

    /**
     * @var string
     */
    #[ORM\Column(name: "correo_electronico", type: "string", length: 150, nullable: true)]
    private $correoElectronico;

    /**
     * @var boolean
     */
    #[ORM\Column(name: "autoriza_ot", type: "boolean", nullable: true)]
    private $autorizaOt;

    /**
     * @var string
     */
    #[ORM\Column(name: "fono", type: "string", length: 15, nullable: true)]
    private $fono;

    /**
     * @var string
     */
    #[ORM\Column(name: "anexo", type: "string", length: 10, nullable: true)]
    private $anexo;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: "fecha_emision_licencia", type: "date", nullable: true)]
    private $fechaEmisionLicencia;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: "fecha_vencimiento_licencia", type: "date", nullable: true)]
    private $fechaVencimientoLicencia;

    /**
     * @var \Tbl14Personal
     * NOTA: Esta relación está duplicada con $parent. Usar $parent en su lugar.
     * Mantenemos el campo solo para compatibilidad con código legacy.
     */
    private $idSuperiorDirecto;


    /**
     * @var string
     */
    #[ORM\Column(name: "licencias_conducir", type: "string", length: 30, nullable: true)]
    private $licenciasConducir;

    /**
     * @var \Tbl06Concesionaria
     */
    #[ORM\ManyToOne(targetEntity: "App\Entity\Tbl06Concesionaria")]
    #[ORM\JoinColumn(name: "id_concesionaria", referencedColumnName: "id_concesionaria")]
    private $idConcesionaria;

    /**
     * @var \Tbl07CentroDeCosto
     */
    #[ORM\ManyToOne(targetEntity: "App\Entity\Tbl07CentroDeCosto")]
    #[ORM\JoinColumn(name: "id_centro_de_costo", referencedColumnName: "id_centro_de_costo")]
    private $idCentroDeCosto;

    /**
     * @var \Tbl08Areas
     */
    #[ORM\ManyToOne(targetEntity: "App\Entity\Tbl08Areas")]
    #[ORM\JoinColumn(name: "id_area", referencedColumnName: "id_area")]
    private $idArea;

    /**
     * @var boolean
     */
    #[ORM\Column(name: "estado_licencia_conducir", type: "boolean", nullable: true)]
    private $estadoLicenciaConducir;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: "fecha_inicio_estado_licencia_conducir", type: "date", nullable: true)]
    private $fechaInicioEstadoLicenciaConducir;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: "fecha_termino_estado_licencia_conducir", type: "date", nullable: true)]
    private $fechaTerminoEstadoLicenciaConducir;

    /**
     * @var boolean
     */
    #[ORM\Column(name: "reg_status", type: "boolean", nullable: false)]
    private $regStatus;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: "created_at", type: "datetime", nullable: true)]
    private $createdAt;

    /**
     * @var integer
     */
    #[ORM\Column(name: "created_by", type: "integer", nullable: false)]
    private $createdBy;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: "updated_at", type: "datetime", nullable: true)]
    private $updatedAt;

    /**
     * @var integer
     */
    #[ORM\Column(name: "updated_by", type: "integer", nullable: true)]
    private $updatedBy;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: "deleted_restored_at", type: "datetime", nullable: true)]
    private $deletedRestoredAt;

    /**
     * @var integer
     */
    #[ORM\Column(name: "deleted_restored_by", type: "integer", nullable: true)]
    private $deletedRestoredBy;

    /**
     * @var string|null
     */
    #[ORM\Column(name: "photo", type: "string", length: 255, nullable: true)]
    private $photo;
    //--------------------------------------------------------------------------------

    /**
     * Get idPersonal
     *
     * @return integer
     */
    public function getIdPersonal()
    {
        return $this->idPersonal;
    }

    /**
     * Get id (alias for getIdPersonal for Doctrine compatibility)
     *
     * @return integer
     */
    public function getId()
    {
        return $this->idPersonal;
    }

    /**
     * Set rut
     *
     * @param string $rut
     * @return Tbl14Personal
     */
    public function setRut($rut)
    {
        $this->rut = $rut;

        return $this;
    }

    /**
     * Get rut
     *
     * @return string
     */
    public function getRut()
    {
        return $this->rut;
    }

    /**
     * Set nombres
     *
     * @param string $nombres
     * @return Tbl14Personal
     */
    public function setNombres($nombres)
    {
        $this->nombres = $nombres;

        return $this;
    }

    /**
     * Get nombres
     *
     * @return string
     */
    public function getNombres()
    {
        return $this->nombres;
    }

    /**
     * Get fullname
     *
     * @return string
     */
    public function getFullName()
    {
        return $this->nombres . " " . $this->apellidos;
    }

    /**
     * Get fullnamerut
     *
     * @return string
     */
    public function getFullNameRut()
    {
        return $this->nombres . " " . $this->apellidos . " " . $this->rut;
    }

    /**
     * Set apellidos
     *
     * @param string $apellidos
     * @return Tbl14Personal
     */
    public function setApellidos($apellidos)
    {
        $this->apellidos = $apellidos;

        return $this;
    }

    /**
     * Get apellidos
     *
     * @return string
     */
    public function getApellidos()
    {
        return $this->apellidos;
    }

    /**
     * Set correoElectronico
     *
     * @param string $correoElectronico
     * @return Tbl14Personal
     */
    public function setCorreoElectronico($correoElectronico)
    {
        $this->correoElectronico = $correoElectronico;

        return $this;
    }

    /**
     * Get correoElectronico
     *
     * @return string 
     */
    public function getCorreoElectronico()
    {
        return $this->correoElectronico;
    }

    /**
     * @return bool
     */
    public function isAutorizaOt()
    {
        return $this->autorizaOt;
    }

    /**
     * @param bool $autorizaOt
     */
    public function setAutorizaOt($autorizaOt)
    {
        $this->autorizaOt = $autorizaOt;
    }

    /**
     * @return string
     */
    public function getFono()
    {
        return $this->fono;
    }

    /**
     * @param string $fono
     */
    public function setFono($fono)
    {
        $this->fono = $fono;
    }

    /**
     * @return string
     */
    public function getAnexo()
    {
        return $this->anexo;
    }

    /**
     * @param string $anexo
     */
    public function setAnexo($anexo)
    {
        $this->anexo = $anexo;
    }


    /**
     * Set fechaEmisionLicencia
     *
     * @param \DateTime $fechaEmisionLicencia
     * @return Tbl14Personal
     */
    public function setFechaEmisionLicencia($fechaEmisionLicencia)
    {
        $this->fechaEmisionLicencia = $fechaEmisionLicencia;

        return $this;
    }

    /**
     * Get fechaEmisionLicencia
     *
     * @return \DateTime 
     */
    public function getFechaEmisionLicencia()
    {
        return $this->fechaEmisionLicencia;
    }

    /**
     * Set fechaVencimientoLicencia
     *
     * @param \DateTime $fechaVencimientoLicencia
     * @return Tbl14Personal
     */
    public function setFechaVencimientoLicencia($fechaVencimientoLicencia)
    {
        $this->fechaVencimientoLicencia = $fechaVencimientoLicencia;

        return $this;
    }

    /**
     * Get fechaVencimientoLicencia
     *
     * @return \DateTime 
     */
    public function getFechaVencimientoLicencia()
    {
        return $this->fechaVencimientoLicencia;
    }

    /**
     * Set idSuperiorDirecto
     *
     * @param integer $idSuperiorDirecto
     * @return Tbl14Personal
     */
    public function setIdSuperiorDirecto($idSuperiorDirecto)
    {
        $this->children[] = $idSuperiorDirecto;
        $idSuperiorDirecto->setParent($this);

        //$this->idSuperiorDirecto = $idSuperiorDirecto;

        return $this;
    }

    /**
     * Get idSuperiorDirecto
     *
     * @return integer 
     */
    public function getIdSuperiorDirecto()
    {
        return $this->idSuperiorDirecto;
    }


    /**
     * Set licenciasConducir
     *
     * @param string $licenciasConducir
     * @return Tbl14Personal
     */
    public function setLicenciasConducir($licenciasConducir)
    {
        $this->licenciasConducir = $licenciasConducir;

        return $this;
    }

    /**
     * Get licenciasConducir
     *
     * @return string
     */
    public function getLicenciasConducir()
    {
        return $this->licenciasConducir;
    }

    /**
     * Set idConcesionaria
     *
     * @param \App\Entity\Tbl06Concesionaria $idConcesionaria
     * @return Tbl14Personal
     */
    public function setIdConcesionaria(?\App\Entity\Tbl06Concesionaria $idConcesionaria = null)
    {
        $this->idConcesionaria = $idConcesionaria;

        return $this;
    }

    /**
     * Get idConcesionaria
     *
     * @return \App\Entity\Tbl06Concesionaria
     */
    public function getIdConcesionaria()
    {
        return $this->idConcesionaria;
    }

    /**
     * Set idCentroDeCosto
     *
     * @param \App\Entity\Tbl07CentroDeCosto $idCentroDeCosto
     * @return Tbl14Personal
     */
    public function setIdCentroDeCosto(?\App\Entity\Tbl07CentroDeCosto $idCentroDeCosto = null)
    {
        $this->idCentroDeCosto = $idCentroDeCosto;
        return $this;
    }

    /**
     * Get idCentroDeCosto
     *
     * @return \App\Entity\Tbl07CentroDeCosto
     */
    public function getIdCentroDeCosto()
    {
        return $this->idCentroDeCosto;
    }

    /**
     * Set idArea
     *
     * @param \App\Entity\Tbl08Areas $idArea
     * @return Tbl14Personal
     */
    public function setIdArea(?\App\Entity\Tbl08Areas $idArea = null)
    {
        $this->idArea = $idArea;

        return $this;
    }

    /**
     * Get idArea
     *
     * @return \App\Entity\Tbl08Areas
     */
    public function getIdArea()
    {
        return $this->idArea;
    }

    // Once you have that, accessing the parent and children should be straight forward
    // (they will be lazy-loaded in this example as soon as you try to access them). IE:

    public function getParent() {
        return $this->parent;
    }

    public function getChildren() {
        return $this->children;
    }
    // always use this to setup a new parent/child relationship
    // Siempre usar esto para configurar un nuevo / relación padre-hijo
    public function addChild(Tbl14Personal $child) {
        $this->children[] = $child;
        $child->setParent($this);
    }

    public function setParent(Tbl14Personal $parent) {
        $this->parent = $parent;
    }

//======================================================================================================================
    /**
     * Set estadoLicenciaConducir
     *
     * @param boolean $estadoLicenciaConducir
     * @return Tbl14Personal
     */
    public function setEstadoLicenciaConducir($estadoLicenciaConducir)
    {
        $this->estadoLicenciaConducir = $estadoLicenciaConducir;

        return $this;
    }

    /**
     * Get estadoLicenciaConducir
     *
     * @return boolean
     */
    public function getEstadoLicenciaConducir()
    {
        return $this->estadoLicenciaConducir;
    }

    /**
     * Set fechaInicioEstadoLicenciaConducir
     *
     * @param \DateTime $fechaInicioEstadoLicenciaConducir
     * @return Tbl14Personal
     */
    public function setFechaInicioEstadoLicenciaConducir($fechaInicioEstadoLicenciaConducir)
    {
        $this->fechaInicioEstadoLicenciaConducir = $fechaInicioEstadoLicenciaConducir;

        return $this;
    }

    /**
     * Get fechaInicioEstadoLicenciaConducir
     *
     * @return \DateTime
     */
    public function getFechaInicioEstadoLicenciaConducir()
    {
        return $this->fechaInicioEstadoLicenciaConducir;
    }

    /**
     * Set fechaTerminoEstadoLicenciaConducir
     *
     * @param \DateTime $fechaTerminoEstadoLicenciaConducir
     * @return Tbl14Personal
     */
    public function setFechaTerminoEstadoLicenciaConducir($fechaTerminoEstadoLicenciaConducir)
    {
        $this->fechaTerminoEstadoLicenciaConducir = $fechaTerminoEstadoLicenciaConducir;

        return $this;
    }

    /**
     * Get fechaTerminoEstadoLicenciaConducir
     *
     * @return \DateTime
     */
    public function getFechaTerminoEstadoLicenciaConducir()
    {
        return $this->fechaTerminoEstadoLicenciaConducir;
    }

    //-   -   -   -   -   -   -   -   -   -   -   -   -   -

    /**
     * Set regStatus
     *
     * @param boolean $regStatus
     * @return Tbl14Personal
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

    //-   -   -   -   -   -   -   -   -   -   -   -   -   -

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     * @return Tbl14Personal
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
     * @return Tbl14Personal
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

    //-   -   -   -   -   -   -   -   -   -   -   -   -   -

    /**
     * Set updatedAt
     *
     * @param \DateTime $updatedAt
     * @return Tbl14Personal
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
     * @return Tbl14Personal
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

    //-   -   -   -   -   -   -   -   -   -   -   -   -   -

    /**
     * Set deletedRestoredAt
     *
     * @param \DateTime $deletedRestoredAt
     * @return Tbl14Personal
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
     * @param integer deletedRestoredBy
     * @return Tbl14Personal
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

    public function getPhoto()
    {
        return $this->photo;
    }

    public function setPhoto($photo)
    {
        $this->photo = $photo;

        return $this;
    }

    /**
     * Método __toString() para EasyAdmin y otras representaciones
     *
     * @return string
     */
    public function __toString(): string
    {
        return sprintf('%s %s (RUT: %s)',
            $this->apellidos ?? '',
            $this->nombres ?? '',
            $this->rut ?? 'N/A'
        );
    }
}
