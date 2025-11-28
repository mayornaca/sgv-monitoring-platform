<?php

namespace App\Repository;

use App\Entity\DeviceAlert;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DeviceAlert>
 */
class DeviceAlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeviceAlert::class);
    }

    public function findActiveAlerts(int $limit = 25): array
    {
        return $this->createQueryBuilder('da')
            ->andWhere('da.estado = :active')
            ->andWhere('da.regStatus = :regStatus')
            ->andWhere('da.concesionaria = :concessionaire')
            ->setParameter('active', false) // false = active alert
            ->setParameter('regStatus', true)
            ->setParameter('concessionaire', 22)
            ->orderBy('da.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findAlertsForDevice(int $deviceId): array
    {
        return $this->createQueryBuilder('da')
            ->andWhere('da.idDispositivo = :deviceId')
            ->andWhere('da.regStatus = :regStatus')
            ->setParameter('deviceId', $deviceId)
            ->setParameter('regStatus', true)
            ->orderBy('da.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveAlertsForDevice(int $deviceId): array
    {
        return $this->createQueryBuilder('da')
            ->andWhere('da.idDispositivo = :deviceId')
            ->andWhere('da.estado = :active')
            ->andWhere('da.regStatus = :regStatus')
            ->setParameter('deviceId', $deviceId)
            ->setParameter('active', false)
            ->setParameter('regStatus', true)
            ->orderBy('da.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAlertsRequiringEscalation(int $escalationTimeMinutes): array
    {
        $cutoffTime = new \DateTime();
        $cutoffTime->modify("-{$escalationTimeMinutes} minutes");

        return $this->createQueryBuilder('da')
            ->andWhere('da.estado = :active')
            ->andWhere('da.regStatus = :regStatus')
            ->andWhere('da.createdAt <= :cutoff')
            ->andWhere('da.concesionaria = :concessionaire')
            ->setParameter('active', false)
            ->setParameter('regStatus', true)
            ->setParameter('cutoff', $cutoffTime)
            ->setParameter('concessionaire', 22)
            ->orderBy('da.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findCriticalAlerts(): array
    {
        $oneHourAgo = new \DateTime();
        $oneHourAgo->modify('-1 hour');

        return $this->createQueryBuilder('da')
            ->andWhere('da.estado = :active')
            ->andWhere('da.regStatus = :regStatus')
            ->andWhere('da.createdAt <= :oneHourAgo')
            ->andWhere('da.concesionaria = :concessionaire')
            ->setParameter('active', false)
            ->setParameter('regStatus', true)
            ->setParameter('oneHourAgo', $oneHourAgo)
            ->setParameter('concessionaire', 22)
            ->orderBy('da.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getAlertCountsByType(): array
    {
        return $this->createQueryBuilder('da')
            ->select('da.idAlarma, COUNT(da.id) as alert_count')
            ->andWhere('da.estado = :active')
            ->andWhere('da.regStatus = :regStatus')
            ->andWhere('da.concesionaria = :concessionaire')
            ->setParameter('active', false)
            ->setParameter('regStatus', true)
            ->setParameter('concessionaire', 22)
            ->groupBy('da.idAlarma')
            ->orderBy('alert_count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getAlertStatistics(): array
    {
        $connection = $this->getEntityManager()->getConnection();
        
        return [
            'active_alerts' => $connection->fetchOne('SELECT COUNT(*) FROM tbl_cot_06_alarmas_dispositivos WHERE estado = 0 AND reg_status = 1 AND concesionaria = 22'),
            'resolved_today' => $connection->fetchOne('SELECT COUNT(*) FROM tbl_cot_06_alarmas_dispositivos WHERE estado = 1 AND DATE(closed_at) = CURDATE() AND concesionaria = 22'),
            'created_today' => $connection->fetchOne('SELECT COUNT(*) FROM tbl_cot_06_alarmas_dispositivos WHERE DATE(created_at) = CURDATE() AND concesionaria = 22'),
            'avg_resolution_time_hours' => $connection->fetchOne('
                SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, closed_at)) / 60 
                FROM tbl_cot_06_alarmas_dispositivos 
                WHERE estado = 1 AND closed_at IS NOT NULL AND DATE(closed_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            '),
            'critical_count' => $connection->fetchOne('
                SELECT COUNT(*) FROM tbl_cot_06_alarmas_dispositivos 
                WHERE estado = 0 AND reg_status = 1 AND concesionaria = 22 
                AND created_at <= DATE_SUB(NOW(), INTERVAL 60 MINUTE)
            '),
        ];
    }

    public function findRecentlyResolved(int $limit = 10): array
    {
        return $this->createQueryBuilder('da')
            ->andWhere('da.estado = :resolved')
            ->andWhere('da.regStatus = :regStatus')
            ->andWhere('da.closedAt IS NOT NULL')
            ->andWhere('da.concesionaria = :concessionaire')
            ->setParameter('resolved', true)
            ->setParameter('regStatus', true)
            ->setParameter('concessionaire', 22)
            ->orderBy('da.closedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findLongestActiveAlerts(int $limit = 5): array
    {
        return $this->createQueryBuilder('da')
            ->andWhere('da.estado = :active')
            ->andWhere('da.regStatus = :regStatus')
            ->andWhere('da.concesionaria = :concessionaire')
            ->setParameter('active', false)
            ->setParameter('regStatus', true)
            ->setParameter('concessionaire', 22)
            ->orderBy('da.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('da')
            ->andWhere('da.createdAt BETWEEN :start AND :end')
            ->andWhere('da.regStatus = :regStatus')
            ->andWhere('da.concesionaria = :concessionaire')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('regStatus', true)
            ->setParameter('concessionaire', 22)
            ->orderBy('da.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}