<?php

namespace App\Repository;

use App\Entity\DeviceType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DeviceType>
 */
class DeviceTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeviceType::class);
    }

    public function findVisibleByConcessionaire(int $concessionaireId): array
    {
        return $this->createQueryBuilder('dt')
            ->andWhere('dt.mostrar = :mostrar')
            ->andWhere('dt.concesionaria = :concesionaria')
            ->setParameter('mostrar', true)
            ->setParameter('concesionaria', $concessionaireId)
            ->orderBy('dt.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findWithPermissions(bool $canViewCNSpires = false, bool $canViewVSSpires = false): array
    {
        $qb = $this->createQueryBuilder('dt')
            ->andWhere('dt.mostrar = :mostrar')
            ->andWhere('dt.concesionaria = :concesionaria')
            ->setParameter('mostrar', true)
            ->setParameter('concesionaria', 22); // Default concessionaire from old system

        // Filter out spire types based on permissions (matching old system logic)
        if (!$canViewCNSpires) {
            $qb->andWhere('dt.id NOT IN (4, 12)');
        }
        
        if (!$canViewVSSpires) {
            $qb->andWhere('dt.id != 13');
        }

        return $qb->orderBy('dt.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByMonitoringMethod(int $method): array
    {
        return $this->createQueryBuilder('dt')
            ->andWhere('dt.metodoMonitoreo = :method')
            ->setParameter('method', $method)
            ->getQuery()
            ->getResult();
    }

    public function getDeviceTypesForDashboard(): array
    {
        return $this->createQueryBuilder('dt')
            ->select('dt.id, dt.tipo, dt.icono, dt.intervalo')
            ->andWhere('dt.mostrar = :mostrar')
            ->andWhere('dt.concesionaria = :concesionaria')
            ->setParameter('mostrar', true)
            ->setParameter('concesionaria', 22)
            ->orderBy('dt.id', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}