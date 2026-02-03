<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: "tbl_cot_03_historial_estado_dispositivos")]
#[ORM\Entity]
class TblCot03HistorialEstadoDispositivos
{
    #[ORM\Column(name: "id", type: "integer")]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "IDENTITY")]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
