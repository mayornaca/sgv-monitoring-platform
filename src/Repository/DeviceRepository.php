<?php

namespace App\Repository;

use App\Entity\Device;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Device>
 */
class DeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Device::class);
    }

    public function findActiveByType(int $deviceTypeId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.idTipo = :deviceType')
            ->andWhere('d.estado = :active')
            ->andWhere('d.regStatus = :regStatus')
            ->andWhere('d.concesionaria = :concessionaire')
            ->setParameter('deviceType', $deviceTypeId)
            ->setParameter('active', 1)
            ->setParameter('regStatus', true)
            ->setParameter('concessionaire', 22)
            ->orderBy('d.orden', 'ASC')
            ->addOrderBy('d.km', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByConcessionaireAndTypes(int $concessionaireId, array $deviceTypeIds = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->andWhere('d.concesionaria = :concessionaire')
            ->andWhere('d.regStatus = :regStatus')
            ->setParameter('concessionaire', $concessionaireId)
            ->setParameter('regStatus', true);

        if ($deviceTypeIds) {
            $qb->andWhere('d.idTipo IN (:deviceTypes)')
                ->setParameter('deviceTypes', $deviceTypeIds);
        }

        return $qb->orderBy('d.idTipo', 'ASC')
            ->addOrderBy('d.orden', 'ASC')
            ->addOrderBy('d.km', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findForMonitoring(array $deviceTypeIds): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.idTipo IN (:deviceTypes)')
            ->andWhere('d.supervisado = :supervised')
            ->andWhere('d.regStatus = :regStatus')
            ->andWhere('d.concesionaria = :concessionaire')
            ->setParameter('deviceTypes', $deviceTypeIds)
            ->setParameter('supervised', true)
            ->setParameter('regStatus', true)
            ->setParameter('concessionaire', 22)
            ->orderBy('d.idTipo', 'ASC')
            ->addOrderBy('d.orden', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByStatus(int $status): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.estado = :status')
            ->andWhere('d.regStatus = :regStatus')
            ->andWhere('d.concesionaria = :concessionaire')
            ->setParameter('status', $status)
            ->setParameter('regStatus', true)
            ->setParameter('concessionaire', 22)
            ->orderBy('d.nFallos', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findCriticalDevices(): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.critical != :notCritical')
            ->andWhere('d.regStatus = :regStatus')
            ->andWhere('d.concesionaria = :concessionaire')
            ->setParameter('notCritical', '0')
            ->setParameter('regStatus', true)
            ->setParameter('concessionaire', 22)
            ->orderBy('d.nFallos', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findBySearchTerm(string $searchTerm): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.nombre LIKE :search OR d.descripcion LIKE :search OR d.ip LIKE :search OR d.idExterno LIKE :search')
            ->andWhere('d.regStatus = :regStatus')
            ->andWhere('d.concesionaria = :concessionaire')
            ->setParameter('search', '%' . $searchTerm . '%')
            ->setParameter('regStatus', true)
            ->setParameter('concessionaire', 22)
            ->orderBy('d.nFallos', 'DESC')
            ->addOrderBy('d.nombre', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getDeviceCountByType(): array
    {
        return $this->createQueryBuilder('d')
            ->select('d.idTipo, COUNT(d.id) as device_count')
            ->andWhere('d.regStatus = :regStatus')
            ->andWhere('d.concesionaria = :concessionaire')
            ->setParameter('regStatus', true)
            ->setParameter('concessionaire', 22)
            ->groupBy('d.idTipo')
            ->getQuery()
            ->getResult();
    }

    public function getDeviceStatusSummary(): array
    {
        return $this->createQueryBuilder('d')
            ->select('d.estado, COUNT(d.id) as device_count')
            ->andWhere('d.regStatus = :regStatus')
            ->andWhere('d.concesionaria = :concessionaire')
            ->setParameter('regStatus', true)
            ->setParameter('concessionaire', 22)
            ->groupBy('d.estado')
            ->getQuery()
            ->getResult();
    }

    public function findDevicesWithMostFailures(int $limit = 10): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.nFallos > :minFailures')
            ->andWhere('d.regStatus = :regStatus')
            ->andWhere('d.concesionaria = :concessionaire')
            ->setParameter('minFailures', 0)
            ->setParameter('regStatus', true)
            ->setParameter('concessionaire', 22)
            ->orderBy('d.nFallos', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}