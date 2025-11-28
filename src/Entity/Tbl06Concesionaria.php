<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * Tbl06Concesionaria
 *
 * @UniqueEntity(
 *     fields={"nombre"},
 *     errorPath="nombre",
 *     message="Este nombre de concesionaria ya ha sido registrado, por favor intente con otro valor."
 * )
 *
 * @ORM\HasLifecycleCallbacks()
 */
// JN 21-12-2016 se quita de las anotaciones de  Tbl06Concesionaria
// * @UniqueEntity(
// *     fields={"rutConcesionaria"},
// *     errorPath="rutConcesionaria",
// *     message="Este RUT de concesionaria ya ha sido registrado, por favor intente con otro valor."
// * )
#[ORM\Table(name: "tbl_06_concesionaria")]
#[ORM\Entity(repositoryClass: "App\Repository\Tbl06ConcesionariaRepository")]
class Tbl06Concesionaria
{
    /**
     * @var smallint
     */
    #[ORM\Column(name: "id_concesionaria", type: "smallint", nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private $idConcesionaria;

    /**
     * @var string
     */
    #[ORM\Column(name: "nombre", type: "string", length: 50, nullable: false, unique: true)]
    private $nombre;

    /**
     * @var string
     *
     * #[ORM\Column(name="rut_concesionaria", type="string", length=10, nullable=false)]
     *      */
    private $rutConcesionaria;// JN 21-12-2016 se elimina (, unique=true)

    /**
     * @var string
     *
     * #[ORM\Column(name="direccion_concesionaria", type="text", length=65535, nullable=false)]
     *      */
    private $direccionConcesionaria;

    /**
     * @var \Tbl14Personal
     *
     */
    #[ORM\ManyToOne(targetEntity: "App\Entity\Tbl14Personal")]
    #[ORM\JoinColumn(name: "encargado", referencedColumnName: "id_personal")]
    private $encargado;

    /**
     * #[ORM\Column(type="string")]
     *
     *      *///    private $logo;

    /**
     * @var boolean
     *
     * #[ORM\Column(name: "reg_status", type: "boolean")]
     */
    private $regStatus;

    /**
     * @var \DateTime
     *
     * #[ORM\Column(name: "created_at", type: "datetime")]
     */
    private $createdAt;

    /**
     * @var integer
     *
     * #[ORM\Column(name: "created_by", type: "integer", nullable: false)]
     */
    private $createdBy;

    /**
     * @var \DateTime
     *
     * #[ORM\Column(name: "updated_at", type: "datetime")]
     */
    private $updatedAt;

    /**
     * @var integer
     *
     * #[ORM\Column(name: "updated_by", type: "integer", nullable: true)]
     */
    private $updatedBy;

    /**
     * @var \DateTime
     *
     * #[ORM\Column(name: "deleted_restored_at", type: "datetime")]
     */
    private $deletedRestoredAt;

    /**
     * @var integer
     *
     * #[ORM\Column(name: "deleted_restored_by", type: "integer", nullable: true)]
     */
    private $deletedRestoredBy;

    //--------------------------------------------------------------------------------

    /**
     * Get idConcesionaria
     *
     * @return boolean
     */
    public function getIdConcesionaria()
    {
        return $this->idConcesionaria;
    }

    /**
     * String representation for EasyAdmin
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->nombre ?? 'Sin nombre';
    }

    /**
     * Set nombre
     *
     * @param string $nombre
     * @return Tbl06Concesionaria
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
     * Set rutConcesionaria
     *
     * @param string $rutConcesionaria
     * @return Tbl06Concesionaria
     */
    public function setRutConcesionaria($rutConcesionaria)
    {
        $this->rutConcesionaria = $rutConcesionaria;

        return $this;
    }

    /**
     * Get rutConcesionaria
     *
     * @return string 
     */
    public function getRutConcesionaria()
    {
        return $this->rutConcesionaria;
    }

    /**
     * Set direccionConcesionaria
     *
     * @param string $direccionConcesionaria
     * @return Tbl06Concesionaria
     */
    public function setDireccionConcesionaria($direccionConcesionaria)
    {
        $this->direccionConcesionaria = $direccionConcesionaria;

        return $this;
    }

    /**
     * Get direccionConcesionaria
     *
     * @return string 
     */
    public function getDireccionConcesionaria()
    {
        return $this->direccionConcesionaria;
    }

    /**
     * Set encargado
     *
     * @param \sgv\DashboardBundle\Entity\Tbl14Personal $encargado
     * @return Tbl06Concesionaria
     */
    public function setEncargado(\sgv\DashboardBundle\Entity\Tbl14Personal $encargado = null)
    {
        $this->encargado = $encargado;

        return $this;
    }

    /**
     * Get encargado
     *
     * @return \sgv\DashboardBundle\Entity\Tbl14Personal
     */
    public function getEncargado()
    {
        return $this->encargado;
    }

    public function getLogo()
    {
        return $this->logo;
    }

    public function setLogo($logo)
    {
        $this->logo = $logo;

        return $this;
    }
    //-   -   -   -   -   -   -   -   -   -   -   -   -   -

    /**
     * Set regStatus
     *
     * @param boolean $regStatus
     * @return Tbl06Concesionaria
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
     * @return Tbl06Concesionaria
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
     * @return Tbl06Concesionaria
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
     * @return Tbl06Concesionaria
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
     * @return Tbl06Concesionaria
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
     * @return Tbl06Concesionaria
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
     * @return Tbl06Concesionaria
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

}
