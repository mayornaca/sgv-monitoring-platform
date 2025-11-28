<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Tbl08Areas
 *
 * @ORM\HasLifecycleCallbacks()
 */
#[ORM\Table(name: "tbl_08_areas")]
#[ORM\Entity]
class Tbl08Areas
{
    /**
     * @var integer
     */
    #[ORM\Column(name: "id_area", type: "integer", nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private $idArea;

    /**
     * @var string
     *
     * #[ORM\Column(name="nombre_area", type="string", length=30, nullable=false)]
     *      */
    private $nombreArea;

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
     * Get idArea
     *
     * @return integer
     */
    public function getIdArea()
    {
        return $this->idArea;
    }

    /**
     * String representation for EasyAdmin
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->nombreArea ?? 'Sin nombre';
    }

    /**
     * Set nombreArea
     *
     * @param string $nombreArea
     * @return Tbl08Areas
     */
    public function setNombreArea($nombreArea)
    {
        $this->nombreArea = $nombreArea;

        return $this;
    }

    /**
     * Get nombreArea
     *
     * @return string 
     */
    public function getNombreArea()
    {
        return $this->nombreArea;
    }


    //-   -   -   -   -   -   -   -   -   -   -   -   -   -

    /**
     * Set regStatus
     *
     * @param boolean $regStatus
     * @return Tbl08Areas
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
     * @return Tbl08Areas
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
     * @return Tbl08Areas
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
     * @return Tbl08Areas
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
     * @return Tbl08Areas
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
     * @return Tbl08Areas
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
     * @return Tbl08Areas
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
