<?php

namespace App\Repository\WhatsApp;

use App\Entity\WhatsApp\Recipient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Recipient>
 */
class RecipientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recipient::class);
    }

    /**
     * Encuentra todos los destinatarios activos
     *
     * @return Recipient[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.activo = :activo')
            ->setParameter('activo', true)
            ->orderBy('r.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra destinatarios por grupo
     *
     * @param string $groupSlug
     * @return Recipient[]
     */
    public function findByGroupSlug(string $groupSlug): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.grupos', 'g')
            ->where('g.slug = :slug')
            ->andWhere('r.activo = :activo')
            ->andWhere('g.activo = :grupoActivo')
            ->setParameter('slug', $groupSlug)
            ->setParameter('activo', true)
            ->setParameter('grupoActivo', true)
            ->orderBy('r.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca un destinatario por teléfono
     */
    public function findOneByPhone(string $telefono): ?Recipient
    {
        return $this->createQueryBuilder('r')
            ->where('r.telefono = :telefono')
            ->setParameter('telefono', $telefono)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
