<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'tbl_22_alarmas')]
class Alarms
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_alarma', type: 'integer')]
    private ?int $idAlarma = null;

    #[ORM\Column(name: 'categoria', type: 'string', length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['Mantenciones', 'Conductores', 'Combustibles', 'GPS', 'Sistema', 'COT'])]
    private ?string $categoria = null;

    #[ORM\Column(name: 'tipo', type: 'string', length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['error', 'success', 'info', 'warn'])]
    private ?string $tipo = null;

    #[ORM\Column(name: 'alarma', type: 'string', length: 200)]
    #[Assert\NotBlank]
    private ?string $alarma = null;

    #[ORM\Column(name: 'ROLE_SUPER_ADMIN', type: 'boolean')]
    private bool $roleSuperAdmin = false;

    #[ORM\Column(name: 'ROLE_ADMIN', type: 'boolean')]
    private bool $roleAdmin = false;

    #[ORM\Column(name: 'ROLE_SUPERVISOR', type: 'boolean')]
    private bool $roleSupervisor = false;

    #[ORM\Column(name: 'ROLE_USER', type: 'boolean')]
    private bool $roleUser = false;

    #[ORM\Column(name: 'ROLE_RESPONSIBLE', type: 'boolean')]
    private bool $roleResponsible = false;

    #[ORM\Column(name: 'ROLE_FUEL_SUPPLY', type: 'boolean')]
    private bool $roleFuelSupply = false;

    #[ORM\Column(name: 'ROLE_ADMIN_COT', type: 'boolean')]
    private bool $roleAdminCot = false;

    #[ORM\Column(name: 'ROLE_OPERATOR_COT', type: 'boolean')]
    private bool $roleOperatorCot = false;

    #[ORM\Column(name: 'ROLE_OPERATOR_SCADA', type: 'boolean')]
    private bool $roleOperatorScada = false;

    #[ORM\Column(name: 'ROLE_OPERATOR_PORTICO', type: 'boolean')]
    private bool $roleOperatorPortico = false;

    #[ORM\Column(name: 'ROLE_SU_COT', type: 'boolean')]
    private bool $roleSuCot = false;

    public function getIdAlarma(): ?int
    {
        return $this->idAlarma;
    }

    public function getCategoria(): ?string
    {
        return $this->categoria;
    }

    public function setCategoria(string $categoria): self
    {
        $this->categoria = $categoria;
        return $this;
    }

    public function getTipo(): ?string
    {
        return $this->tipo;
    }

    public function setTipo(string $tipo): self
    {
        $this->tipo = $tipo;
        return $this;
    }

    public function getAlarma(): ?string
    {
        return $this->alarma;
    }

    public function setAlarma(string $alarma): self
    {
        $this->alarma = $alarma;
        return $this;
    }

    // Método de compatibilidad para templates que esperan getDescripcion
    public function getDescripcion(): ?string
    {
        return $this->alarma;
    }

    public function getRoleSuperAdmin(): bool
    {
        return $this->roleSuperAdmin;
    }

    public function setRoleSuperAdmin(bool $roleSuperAdmin): self
    {
        $this->roleSuperAdmin = $roleSuperAdmin;
        return $this;
    }

    public function getRoleAdmin(): bool
    {
        return $this->roleAdmin;
    }

    public function setRoleAdmin(bool $roleAdmin): self
    {
        $this->roleAdmin = $roleAdmin;
        return $this;
    }

    public function getRoleSupervisor(): bool
    {
        return $this->roleSupervisor;
    }

    public function setRoleSupervisor(bool $roleSupervisor): self
    {
        $this->roleSupervisor = $roleSupervisor;
        return $this;
    }

    public function getRoleUser(): bool
    {
        return $this->roleUser;
    }

    public function setRoleUser(bool $roleUser): self
    {
        $this->roleUser = $roleUser;
        return $this;
    }

    public function getRoleResponsible(): bool
    {
        return $this->roleResponsible;
    }

    public function setRoleResponsible(bool $roleResponsible): self
    {
        $this->roleResponsible = $roleResponsible;
        return $this;
    }

    public function getRoleFuelSupply(): bool
    {
        return $this->roleFuelSupply;
    }

    public function setRoleFuelSupply(bool $roleFuelSupply): self
    {
        $this->roleFuelSupply = $roleFuelSupply;
        return $this;
    }

    public function getRoleAdminCot(): bool
    {
        return $this->roleAdminCot;
    }

    public function setRoleAdminCot(bool $roleAdminCot): self
    {
        $this->roleAdminCot = $roleAdminCot;
        return $this;
    }

    public function getRoleOperatorCot(): bool
    {
        return $this->roleOperatorCot;
    }

    public function setRoleOperatorCot(bool $roleOperatorCot): self
    {
        $this->roleOperatorCot = $roleOperatorCot;
        return $this;
    }

    public function getRoleOperatorScada(): bool
    {
        return $this->roleOperatorScada;
    }

    public function setRoleOperatorScada(bool $roleOperatorScada): self
    {
        $this->roleOperatorScada = $roleOperatorScada;
        return $this;
    }

    public function getRoleOperatorPortico(): bool
    {
        return $this->roleOperatorPortico;
    }

    public function setRoleOperatorPortico(bool $roleOperatorPortico): self
    {
        $this->roleOperatorPortico = $roleOperatorPortico;
        return $this;
    }

    public function getRoleSuCot(): bool
    {
        return $this->roleSuCot;
    }

    public function setRoleSuCot(bool $roleSuCot): self
    {
        $this->roleSuCot = $roleSuCot;
        return $this;
    }
}