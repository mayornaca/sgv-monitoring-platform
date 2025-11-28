<?php

namespace App\Repository\WhatsApp;

use App\Entity\WhatsApp\Template;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Template>
 */
class TemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Template::class);
    }

    /**
     * Encuentra un template por su nombre
     */
    public function findOneByNombre(string $nombre): ?Template
    {
        return $this->createQueryBuilder('t')
            ->where('t.nombre = :nombre')
            ->andWhere('t.activo = :activo')
            ->setParameter('nombre', $nombre)
            ->setParameter('activo', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Encuentra todos los templates activos
     *
     * @return Template[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.activo = :activo')
            ->setParameter('activo', true)
            ->orderBy('t.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
