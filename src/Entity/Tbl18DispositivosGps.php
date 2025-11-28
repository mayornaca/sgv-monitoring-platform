<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Tbl18DispositivosGps
 *
 * #[ORM\Table(name: "tbl_18_dispositivos_gps")]
 * #[ORM\Entity]
 */
class Tbl18DispositivosGps
{
    /**
     * #[ORM\OneToMany(targetEntity="App\Entity\Tbl19GpsTrackLog", mappedBy="Tbl18DispositivosGps")]
     */
    protected $Tbl19GpsTrackLog;

    /**
     * @var integer
     *
     * #[ORM\Column(name: "id_dispositivo_gps", type: "smallint", nullable: false)]
     */
    #[ORM\Id]
        #[ORM\GeneratedValue(strategy: "IDENTITY")]
        private $idDispositivoGps;

    /**
     * @var string
     *
     * #[ORM\Column(name="imei", type="string", length=15, nullable=false)]
     */
    private $imei;

    /**
     * @var string
     *
     * #[ORM\Column(name="nro_celular_sim", type="string", length=10, nullable=false)]
     */
    private $nroCelularSim;

    /**
     * @var boolean
     *
     * #[ORM\Column(name: "estado", type: "boolean", nullable: false)]
     */
    private $estado;

    /**
     * @var string
     *
     * #[ORM\Column(name: "marca", type: "string", nullable: false)]
     */
    private $marca;

    /**
     * @var string
     *
     * #[ORM\Column(name: "modelo", type: "string", nullable: false)]
     */
    private $modelo;

    /**
     * @var \DateTime
     *
     * #[ORM\Column(name: "fecha_hora_posicion", type: "datetime", nullable: false)]
     */
    private $fechaHoraPosicion;

    /**
     * @var float
     *
     * #[ORM\Column(name="latitud", type="float", precision=10, scale=0, nullable=false)]
     */
    private $latitud;

    /**
     * @var float
     *
     * #[ORM\Column(name="longitud", type="float", precision=10, scale=0, nullable=false)]
     */
    private $longitud;

    /**
     * @var integer
     *
     * #[ORM\Column(name: "altitud", type: "smallint", nullable: false)]
     */
    private $altitud;

    /**
     * @var integer
     *
     * #[ORM\Column(name: "velocidad", type: "smallint", nullable: false)]
     */
    private $velocidad;

    /**
     * @var string
     *
     * #[ORM\Column(name="curso", type="string", length=4, nullable=false)]
     */
    private $curso;

    /**
     * @var string
     *
     * #[ORM\Column(name="gps_info_alarm", type="string", length=30, nullable=false)]
     */
    private $gpsInfoAlarm;

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
     * Constructor
     */
    public function __construct()
    {
        $this->Tbl19GpsTrackLog = new ArrayCollection();
    }
    //-----------------------------------------------

    /**
     * Add Tbl19GpsTrackLog
     *
     * @param \sgv\DashboardBundle\Entity\Tbl19GpsTrackLog $tbl19GpsTrackLog
     * @return Tbl18DispositivosGps
     */
    public function addTbl19GpsTrackLog(\sgv\DashboardBundle\Entity\Tbl19GpsTrackLog $tbl19GpsTrackLog)
    {
        $this->Tbl19GpsTrackLog[] = $tbl19GpsTrackLog;

        return $this;
    }

    /**
     * Remove Tbl19GpsTrackLog
     *
     * @param \sgv\DashboardBundle\Entity\Tbl19GpsTrackLog $tbl19GpsTrackLog
     */
    public function removeTbl19GpsTrackLog(\sgv\DashboardBundle\Entity\Tbl19GpsTrackLog $tbl19GpsTrackLog)
    {
        $this->Tbl19GpsTrackLog->removeElement($tbl19GpsTrackLog);
    }

    /**
     * Get Tbl19GpsTrackLog
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getTbl19GpsTrackLog()
    {
        return $this->Tbl19GpsTrackLog;
    }

    //-----------------------------------------------
    /**
     * Get idDispositivoGps
     *
     * @return integer 
     */
    public function getIdDispositivoGps()
    {
        return $this->idDispositivoGps;
    }

    /**
     * Set imei
     *
     * @param string $imei
     * @return Tbl18DispositivosGps
     */
    public function setImei($imei)
    {
        $this->imei = $imei;

        return $this;
    }

    /**
     * Get imei
     *
     * @return string 
     */
    public function getImei()
    {
        return $this->imei;
    }

    /**
     * Set nroCelularSim
     *
     * @param string $nroCelularSim
     * @return Tbl18DispositivosGps
     */
    public function setNroCelularSim($nroCelularSim)
    {
        $this->nroCelularSim = $nroCelularSim;

        return $this;
    }

    /**
     * Get nroCelularSim
     *
     * @return string 
     */
    public function getNroCelularSim()
    {
        return $this->nroCelularSim;
    }

    /**
     * Set estado
     *
     * @param boolean $estado
     * @return Tbl18DispositivosGps
     */
    public function setEstado($estado)
    {
        $this->estado = $estado;

        return $this;
    }

    /**
     * Get estado
     *
     * @return boolean 
     */
    public function getEstado()
    {
        return $this->estado;
    }

    /**
     * Set marca
     *
     * @param string $marca
     * @return Tbl18DispositivosGps
     */
    public function setMarca($marca)
    {
        $this->marca = $marca;

        return $this;
    }

    /**
     * Get marca
     *
     * @return string
     */
    public function getMarca()
    {
        return $this->marca;
    }

    /**
     * Set modelo
     *
     * @param string $modelo
     * @return Tbl18DispositivosGps
     */
    public function setModelo($modelo)
    {
        $this->modelo = $modelo;

        return $this;
    }

    /**
     * Get modelo
     *
     * @return string
     */
    public function getModelo()
    {
        return $this->modelo;
    }

    /**
     * Set fechaHoraPosicion
     *
     * @param \DateTime $fechaHoraPosicion
     * @return Tbl18DispositivosGps
     */
    public function setFechaHoraPosicion($fechaHoraPosicion)
    {
        $this->fechaHoraPosicion = $fechaHoraPosicion;

        return $this;
    }

    /**
     * Get fechaHoraPosicion
     *
     * @return \DateTime 
     */
    public function getFechaHoraPosicion()
    {
        return $this->fechaHoraPosicion;
    }

    /**
     * Set latitud
     *
     * @param float $latitud
     * @return Tbl18DispositivosGps
     */
    public function setLatitud($latitud)
    {
        $this->latitud = $latitud;

        return $this;
    }

    /**
     * Get latitud
     *
     * @return float 
     */
    public function getLatitud()
    {
        return $this->latitud;
    }

    /**
     * Set longitud
     *
     * @param float $longitud
     * @return Tbl18DispositivosGps
     */
    public function setLongitud($longitud)
    {
        $this->longitud = $longitud;

        return $this;
    }

    /**
     * Get longitud
     *
     * @return float 
     */
    public function getLongitud()
    {
        return $this->longitud;
    }

    public function getPosition()
    {
        return $this->latitud . "," .$this->longitud;
    }
    /**
     * Set altitud
     *
     * @param integer $altitud
     * @return Tbl18DispositivosGps
     */
    public function setAltitud($altitud)
    {
        $this->altitud = $altitud;

        return $this;
    }

    /**
     * Get altitud
     *
     * @return integer 
     */
    public function getAltitud()
    {
        return $this->altitud;
    }

    /**
     * Set velocidad
     *
     * @param integer $velocidad
     * @return Tbl18DispositivosGps
     */
    public function setVelocidad($velocidad)
    {
        $this->velocidad = $velocidad;

        return $this;
    }

    /**
     * Get velocidad
     *
     * @return integer
     */
    public function getVelocidad()
    {
        return $this->velocidad;
    }

    /**
     * Set curso
     *
     * @param string $curso
     * @return Tbl18DispositivosGps
     */
    public function setCurso($curso)
    {
        $this->curso = $curso;

        return $this;
    }

    /**
     * Get curso
     *
     * @return string 
     */
    public function getCurso()
    {
        return $this->curso;
    }

    /**
     * Set gpsInfoAlarm
     *
     * @param string $gpsInfoAlarm
     * @return Tbl18DispositivosGps
     */
    public function setGpsInfoAlarm($gpsInfoAlarm)
    {
        $this->gpsInfoAlarm = $gpsInfoAlarm;

        return $this;
    }

    /**
     * Get gpsInfoAlarm
     *
     * @return string 
     */
    public function getGpsInfoAlarm()
    {
        return $this->gpsInfoAlarm;
    }

    //-   -   -   -   -   -   -   -   -   -   -   -   -   -

    /**
     * Set regStatus
     *
     * @param boolean $regStatus
     * @return Tbl18DispositivosGps
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
     * @return Tbl18DispositivosGps
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
     * @return Tbl18DispositivosGps
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
     * @return Tbl18DispositivosGps
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
     * @return Tbl18DispositivosGps
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
     * @return Tbl18DispositivosGps
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
     * @return Tbl18DispositivosGps
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
