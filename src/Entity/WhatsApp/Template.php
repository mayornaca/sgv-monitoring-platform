<?php

namespace App\Entity\WhatsApp;

use App\Repository\WhatsApp\TemplateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: TemplateRepository::class)]
#[ORM\Table(name: 'whatsapp_templates')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['nombre'], message: 'Ya existe un template con este nombre')]
class Template
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: 'El nombre es obligatorio')]
    private ?string $nombre = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'El ID de template de Meta es obligatorio')]
    private ?string $metaTemplateId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 0, max: 10, notInRangeMessage: 'El número de parámetros debe estar entre {{ min }} y {{ max }}')]
    private int $parametrosCount = 0;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $parametrosDescripcion = null;

    #[ORM\Column]
    private bool $activo = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 10, options: ['default' => 'es'])]
    private string $language = 'es';

    public function __construct()
    {
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

    public function getMetaTemplateId(): ?string
    {
        return $this->metaTemplateId;
    }

    public function setMetaTemplateId(string $metaTemplateId): static
    {
        $this->metaTemplateId = $metaTemplateId;
        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion;
        return $this;
    }

    public function getParametrosCount(): int
    {
        return $this->parametrosCount;
    }

    public function setParametrosCount(int $parametrosCount): static
    {
        $this->parametrosCount = $parametrosCount;
        return $this;
    }

    public function getParametrosDescripcion(): ?array
    {
        return $this->parametrosDescripcion;
    }

    public function setParametrosDescripcion(?array $parametrosDescripcion): static
    {
        $this->parametrosDescripcion = $parametrosDescripcion;
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

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;
        return $this;
    }

    public function __toString(): string
    {
        return $this->nombre ?? '';
    }
}
