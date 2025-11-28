<?php

namespace App\Entity\WhatsApp;

use App\Repository\WhatsApp\RecipientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RecipientRepository::class)]
#[ORM\Table(name: 'whatsapp_recipients')]
#[ORM\HasLifecycleCallbacks]
class Recipient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'El nombre es obligatorio')]
    private ?string $nombre = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'El teléfono es obligatorio')]
    #[Assert\Regex(
        pattern: '/^\+?\d{10,15}$/',
        message: 'El teléfono debe tener formato internacional (+56972126016)'
    )]
    private ?string $telefono = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email(message: 'El email no es válido')]
    private ?string $email = null;

    #[ORM\Column]
    private bool $activo = true;

    #[ORM\ManyToMany(targetEntity: RecipientGroup::class, mappedBy: 'recipients')]
    private Collection $grupos;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $notas = null;

    public function __construct()
    {
        $this->grupos = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;
        return $this;
    }

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }

    public function setTelefono(string $telefono): static
    {
        $this->telefono = $telefono;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;
        return $this;
    }

    /**
     * @return Collection<int, RecipientGroup>
     */
    public function getGrupos(): Collection
    {
        return $this->grupos;
    }

    public function addGrupo(RecipientGroup $grupo): static
    {
        if (!$this->grupos->contains($grupo)) {
            $this->grupos->add($grupo);
            $grupo->addRecipient($this);
        }

        return $this;
    }

    public function removeGrupo(RecipientGroup $grupo): static
    {
        if ($this->grupos->removeElement($grupo)) {
            $grupo->removeRecipient($this);
        }

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getNotas(): ?string
    {
        return $this->notas;
    }

    public function setNotas(?string $notas): static
    {
        $this->notas = $notas;
        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s (%s)', $this->nombre, $this->telefono);
    }
}
