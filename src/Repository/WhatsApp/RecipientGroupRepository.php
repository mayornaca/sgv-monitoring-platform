<?php

namespace App\Repository\WhatsApp;

use App\Entity\WhatsApp\RecipientGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RecipientGroup>
 */
class RecipientGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecipientGroup::class);
    }

    /**
     * Encuentra un grupo por su slug
     */
    public function findOneBySlug(string $slug): ?RecipientGroup
    {
        return $this->createQueryBuilder('g')
            ->where('g.slug = :slug')
            ->andWhere('g.activo = :activo')
            ->setParameter('slug', $slug)
            ->setParameter('activo', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Encuentra todos los grupos activos
     *
     * @return RecipientGroup[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.activo = :activo')
            ->setParameter('activo', true)
            ->orderBy('g.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
