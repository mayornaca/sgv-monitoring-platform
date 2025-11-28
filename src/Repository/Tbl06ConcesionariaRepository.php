<?php

namespace App\Repository;

use App\Entity\Tbl06Concesionaria;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tbl06Concesionaria>
 */
class Tbl06ConcesionariaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tbl06Concesionaria::class);
    }

    /**
     * Obtiene todas las concesionarias ordenadas por nombre
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca concesionarias por IDs
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->where('c.idConcesionaria IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('c.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}