<?php

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function findRecentByUser(int $userId, int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByLevel(string $level, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.level = :level')
            ->setParameter('level', $level)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findSecurityEvents(int $limit = 100): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.source = :source OR a.level IN (:levels)')
            ->setParameter('source', 'SECURITY')
            ->setParameter('levels', ['WARNING', 'ERROR', 'CRITICAL'])
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getActivityByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('a')
            ->select('DATE(a.createdAt) as date, COUNT(a.id) as count, a.level')
            ->andWhere('a.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->groupBy('date, a.level')
            ->orderBy('date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findFailedLoginAttempts(int $hours = 24): array
    {
        $since = new \DateTime(sprintf('-%d hours', $hours));
        
        return $this->createQueryBuilder('a')
            ->andWhere('a.action = :action')
            ->andWhere('a.createdAt >= :since')
            ->setParameter('action', 'USER_LOGIN_FAILED')
            ->setParameter('since', $since)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getTopUsers(int $days = 7, int $limit = 10): array
    {
        $since = new \DateTime(sprintf('-%d days', $days));
        
        return $this->createQueryBuilder('a')
            ->select('a.username, COUNT(a.id) as activity_count')
            ->andWhere('a.createdAt >= :since')
            ->andWhere('a.username IS NOT NULL')
            ->setParameter('since', $since)
            ->groupBy('a.username')
            ->orderBy('activity_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function cleanup(int $daysToKeep = 90): int
    {
        $cutoffDate = new \DateTime(sprintf('-%d days', $daysToKeep));
        
        return $this->createQueryBuilder('a')
            ->delete()
            ->andWhere('a.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->execute();
    }
}
