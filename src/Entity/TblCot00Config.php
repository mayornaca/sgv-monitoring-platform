<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: "tbl_cot_00_config")]
#[ORM\Entity]
class TblCot00Config
{
    #[ORM\Id]
    #[ORM\Column(name: "parametro", type: "string", length: 50)]
    private ?string $parametro = null;

    #[ORM\Column(name: "tipo", type: "smallint")]
    private ?int $tipo = null;

    #[ORM\Column(name: "valor", type: "string", length: 50)]
    private ?string $valor = null;

    #[ORM\Column(name: "categoria", type: "smallint")]
    private ?int $categoria = null;

    public function getParametro(): ?string
    {
        return $this->parametro;
    }

    public function setParametro(string $parametro): self
    {
        $this->parametro = $parametro;
        return $this;
    }

    public function getTipo(): ?int
    {
        return $this->tipo;
    }

    public function setTipo(int $tipo): self
    {
        $this->tipo = $tipo;
        return $this;
    }

    public function getValor(): ?string
    {
        return $this->valor;
    }

    public function setValor(string $valor): self
    {
        $this->valor = $valor;
        return $this;
    }

    public function getCategoria(): ?int
    {
        return $this->categoria;
    }

    public function setCategoria(int $categoria): self
    {
        $this->categoria = $categoria;
        return $this;
    }
}
