<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * TblCot00Config
 *
 * #[ORM\Table(name: "tbl_cot_00_config")]
 * #[ORM\Entity]
 */
class TblCot00Config
{
    /**
     * @var string
     *
     * #[ORM\Column(name="parametro", type="string", length=50, nullable=false)]
     */
    #[ORM\Id]
        #[ORM\GeneratedValue(strategy: "IDENTITY")]
        private $parametro;

    /**
     * @var integer
     *
     * #[ORM\Column(name: "tipo", type: "smallint", nullable: false)]
     */
    private $tipo;

    /**
     * @var string
     *
     * #[ORM\Column(name="valor", type="string", length=50, nullable=false)]
     */
    private $valor;

    /**
     * @var integer
     *
     * #[ORM\Column(name: "categoria", type: "smallint", nullable: false)]
     */
    private $categoria;

    /**
     * @return string
     */
    public function getParametro()
    {
        return $this->parametro;
    }

    /**
     * @param string $parametro
     */
    public function setParametro($parametro)
    {
        $this->parametro = $parametro;
    }

    /**
     * @return int
     */
    public function getTipo()
    {
        return $this->tipo;
    }

    /**
     * @param int $tipo
     */
    public function setTipo($tipo)
    {
        $this->tipo = $tipo;
    }

    /**
     * @return string
     */
    public function getValor()
    {
        /*if($this->tipo == 8){
            return date_create_from_format('Y-m-d H:i:s', $this->valor);
        }*/
        return $this->valor;
    }

    /**
     * @param string $valor
     */
    public function setValor($valor)
    {
        $this->valor = $valor;
    }

    /**
     * @return int
     */
    public function getCategoria()
    {
        return $this->categoria;
    }

    /**
     * @param int $categoria
     */
    public function setCategoria($categoria)
    {
        $this->categoria = $categoria;
    }

}
