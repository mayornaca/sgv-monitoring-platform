<?php

namespace App\Repository;

use App\Entity\Tbl14Personal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tbl14Personal>
 */
class Tbl14PersonalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tbl14Personal::class);
    }

    /**
     * Busca personal por nombre o apellido
     */
    public function findBySearchTerm(string $searchTerm): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.nombres LIKE :term OR p.apellidos LIKE :term OR p.rut LIKE :term')
            ->setParameter('term', '%' . $searchTerm . '%')
            ->orderBy('p.apellidos', 'ASC')
            ->addOrderBy('p.nombres', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene todo el personal ordenado
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.apellidos', 'ASC')
            ->addOrderBy('p.nombres', 'ASC')
            ->getQuery()
            ->getResult();
    }
}