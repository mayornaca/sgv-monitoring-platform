<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Tbl17Proveedores
 */
#[ORM\Table(name: "tbl_17_proveedores")]
#[ORM\Entity]
class Tbl17Proveedores
{
    /**
     * @var integer
     */
    #[ORM\Column(name: "id_proveedor", type: "smallint", nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private $idProveedor;

    /**
     * @var string
     */
    #[ORM\Column(name: "razon_social", type: "string", length: 150, nullable: false)]
    private $razonSocial;

    /**
     * @var string
     */
    #[ORM\Column(name: "rut_proveedor", type: "string", length: 13, nullable: false)]
    private $rutProveedor;

    /**
     * @var string
     */
    #[ORM\Column(name: "direccion_proveedor", type: "string", length: 255, nullable: true)]
    private $direccionProveedor;

    /**
     * @var string
     */
    #[ORM\Column(name: "nombre_contacto", type: "string", length: 50, nullable: true)]
    private $nombreContacto;

    /**
     * @var string
     */
    #[ORM\Column(name: "correo_elec_contacto", type: "string", length: 150, nullable: true)]
    private $correoElecContacto;

    /**
     * @var boolean
     */
    #[ORM\Column(name: "reg_status", type: "boolean", options: ["default" => true])]
    private $regStatus = true;

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

    //--------------------------------------------------------------------------------

    /**
     * Get idProveedor
     *
     * @return integer 
     */
    public function getIdProveedor()
    {
        return $this->idProveedor;
    }

    /**
     * Set razonSocial
     *
     * @param string $razonSocial
     * @return Tbl17Proveedores
     */
    public function setRazonSocial($razonSocial)
    {
        $this->razonSocial = $razonSocial;

        return $this;
    }

    /**
     * Get razonSocial
     *
     * @return string 
     */
    public function getRazonSocial()
    {
        return $this->razonSocial;
    }

    /**
     * Set rutProveedor
     *
     * @param string $rutProveedor
     * @return Tbl17Proveedores
     */
    public function setRutProveedor($rutProveedor)
    {
        $this->rutProveedor = $rutProveedor;

        return $this;
    }

    /**
     * Get rutProveedor
     *
     * @return string 
     */
    public function getRutProveedor()
    {
        return $this->rutProveedor;
    }

    /**
     * Set direccionProveedor
     *
     * @param string $direccionProveedor
     * @return Tbl17Proveedores
     */
    public function setDireccionProveedor($direccionProveedor)
    {
        $this->direccionProveedor = $direccionProveedor;

        return $this;
    }

    /**
     * Get direccionProveedor
     *
     * @return string 
     */
    public function getDireccionProveedor()
    {
        return $this->direccionProveedor;
    }

    /**
     * Set nombreContacto
     *
     * @param string $nombreContacto
     * @return Tbl17Proveedores
     */
    public function setNombreContacto($nombreContacto)
    {
        $this->nombreContacto = $nombreContacto;

        return $this;
    }

    /**
     * Get nombreContacto
     *
     * @return string 
     */
    public function getNombreContacto()
    {
        return $this->nombreContacto;
    }

    /**
     * Set correoElecContacto
     *
     * @param string $correoElecContacto
     * @return Tbl17Proveedores
     */
    public function setCorreoElecContacto($correoElecContacto)
    {
        $this->correoElecContacto = $correoElecContacto;

        return $this;
    }

    /**
     * Get correoElecContacto
     *
     * @return string 
     */
    public function getCorreoElecContacto()
    {
        return $this->correoElecContacto;
    }

    //-   -   -   -   -   -   -   -   -   -   -   -   -   -

    /**
     * Set regStatus
     *
     * @param boolean $regStatus
     * @return Tbl17Proveedores
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
     * @return Tbl17Proveedores
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
     * @return Tbl17Proveedores
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
     * @return Tbl17Proveedores
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
     * @return Tbl17Proveedores
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
     * @return Tbl17Proveedores
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
     * @return Tbl17Proveedores
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
