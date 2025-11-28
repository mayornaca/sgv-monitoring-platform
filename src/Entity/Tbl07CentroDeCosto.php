<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Tbl07CentroDeCosto
 *
 * @ORM\HasLifecycleCallbacks()
 */
#[ORM\Table(name: "tbl_07_centro_de_costo")]
#[ORM\Entity]
class Tbl07CentroDeCosto
{
    /**
     * @var integer
     */
    #[ORM\Column(name: "id_centro_de_costo", type: "integer", nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private $idCentroDeCosto;

    /**
     * @var string
     *
     * #[ORM\Column(name="nombre_centro_de_costo", type="string", length=30, nullable=false)]
     *      */
    private $nombreCentroDeCosto;

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
     * Get idCentroDeCosto
     *
     * @return integer
     */
    public function getIdCentroDeCosto()
    {
        return $this->idCentroDeCosto;
    }

    /**
     * String representation for EasyAdmin
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->nombreCentroDeCosto ?? 'Sin nombre';
    }

    /**
     * Set nombreCentroDeCosto
     *
     * @param string $nombreCentroDeCosto
     * @return Tbl07CentroDeCosto
     */
    public function setNombreCentroDeCosto($nombreCentroDeCosto)
    {
        $this->nombreCentroDeCosto = $nombreCentroDeCosto;

        return $this;
    }

    /**
     * Get nombreCentroDeCosto
     *
     * @return string 
     */
    public function getNombreCentroDeCosto()
    {
        return $this->nombreCentroDeCosto;
    }

    //-   -   -   -   -   -   -   -   -   -   -   -   -   -

    /**
     * Set regStatus
     *
     * @param boolean $regStatus
     * @return Tbl07CentroDeCosto
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
     * @return Tbl07CentroDeCosto
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
     * @return Tbl07CentroDeCosto
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
     * @return Tbl07CentroDeCosto
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
     * @return Tbl07CentroDeCosto
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
     * @return Tbl07CentroDeCosto
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
     * @return Tbl07CentroDeCosto
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
