<?php

namespace App\Repository;

use App\Entity\Alert;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Alert>
 */
class AlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Alert::class);
    }

    public function findActiveAlerts(int $limit = 25): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findBySeverity(string $severity): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.severity = :severity')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('severity', $severity)
            ->setParameter('statuses', ['active', 'acknowledged'])
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findBySourceType(string $sourceType): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.sourceType = :sourceType')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('sourceType', $sourceType)
            ->setParameter('statuses', ['active', 'acknowledged'])
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByAlertType(string $alertType): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.alertType = :alertType')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('alertType', $alertType)
            ->setParameter('statuses', ['active', 'acknowledged'])
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAlertsRequiringEscalation(int $escalationTimeMinutes): array
    {
        $cutoffTime = new \DateTime();
        $cutoffTime->modify("-{$escalationTimeMinutes} minutes");

        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->andWhere('a.createdAt <= :cutoff')
            ->orWhere('a.lastEscalatedAt IS NULL AND a.createdAt <= :cutoff')
            ->orWhere('a.lastEscalatedAt <= :cutoff')
            ->setParameter('status', 'active')
            ->setParameter('cutoff', $cutoffTime)
            ->orderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findCriticalAlerts(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.severity = :severity')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('severity', 'critical')
            ->setParameter('statuses', ['active', 'acknowledged'])
            ->orderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findHighPriorityUnacknowledged(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->andWhere('a.severity IN (:severities)')
            ->andWhere('a.acknowledgedAt IS NULL')
            ->setParameter('status', 'active')
            ->setParameter('severities', ['critical', 'high'])
            ->orderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getAlertStatistics(): array
    {
        $connection = $this->getEntityManager()->getConnection();
        
        return [
            'active_alerts' => $connection->fetchOne('SELECT COUNT(*) FROM alerts WHERE status = "active"'),
            'acknowledged_alerts' => $connection->fetchOne('SELECT COUNT(*) FROM alerts WHERE status = "acknowledged"'),
            'resolved_today' => $connection->fetchOne('SELECT COUNT(*) FROM alerts WHERE status = "resolved" AND DATE(resolved_at) = CURDATE()'),
            'created_today' => $connection->fetchOne('SELECT COUNT(*) FROM alerts WHERE DATE(created_at) = CURDATE()'),
            'critical_count' => $connection->fetchOne('SELECT COUNT(*) FROM alerts WHERE severity = "critical" AND status IN ("active", "acknowledged")'),
            'high_count' => $connection->fetchOne('SELECT COUNT(*) FROM alerts WHERE severity = "high" AND status IN ("active", "acknowledged")'),
            'avg_resolution_time_hours' => $connection->fetchOne('
                SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) / 60 
                FROM alerts 
                WHERE status = "resolved" AND resolved_at IS NOT NULL AND DATE(resolved_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            '),
            'escalated_count' => $connection->fetchOne('SELECT COUNT(*) FROM alerts WHERE escalation_level > 0 AND status IN ("active", "acknowledged")'),
        ];
    }

    public function getAlertCountsByType(): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.alertType, a.severity, COUNT(a.id) as alert_count')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('statuses', ['active', 'acknowledged'])
            ->groupBy('a.alertType, a.severity')
            ->orderBy('alert_count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getAlertCountsBySource(): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.sourceType, COUNT(a.id) as alert_count')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('statuses', ['active', 'acknowledged'])
            ->groupBy('a.sourceType')
            ->orderBy('alert_count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByTags(array $tags): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('statuses', ['active', 'acknowledged']);

        foreach ($tags as $index => $tag) {
            $qb->andWhere("JSON_CONTAINS(a.tags, :tag{$index})")
                ->setParameter("tag{$index}", json_encode($tag));
        }

        return $qb->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findForSource(string $sourceType, string $sourceId): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.sourceType = :sourceType')
            ->andWhere('a.sourceId = :sourceId')
            ->setParameter('sourceType', $sourceType)
            ->setParameter('sourceId', $sourceId)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveForSource(string $sourceType, string $sourceId): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.sourceType = :sourceType')
            ->andWhere('a.sourceId = :sourceId')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('sourceType', $sourceType)
            ->setParameter('sourceId', $sourceId)
            ->setParameter('statuses', ['active', 'acknowledged'])
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLongestActiveAlerts(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('a.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findRecentlyResolved(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->andWhere('a.resolvedAt IS NOT NULL')
            ->setParameter('status', 'resolved')
            ->orderBy('a.resolvedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findResolvedInTimeframe(\DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->andWhere('a.resolvedAt >= :since')
            ->setParameter('status', 'resolved')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();
    }

    public function countBySeverityAndStatus(string $severity, string $status): int
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.severity = :severity')
            ->andWhere('a.status = :status')
            ->setParameter('severity', $severity)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByEscalationLevel(int $level): int
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.escalationLevel = :level')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('level', $level)
            ->setParameter('statuses', ['active', 'acknowledged'])
            ->getQuery()
            ->getSingleScalarResult();
    }
}