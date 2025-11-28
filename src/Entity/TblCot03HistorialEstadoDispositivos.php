<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * TblCot03HistorialEstadoDispositivos
 *
 * #[ORM\Table(name: "tbl_cot_03_historial_estado_dispositivos")]
 * #[ORM\Entity]
 */
class TblCot03HistorialEstadoDispositivos
{
    /**
     * @var integer
     *
     * #[ORM\Column(name: "id", type: "integer")]
     */
    #[ORM\Id]
        #[ORM\GeneratedValue(strategy: "IDENTITY")]
        private $id;



}
