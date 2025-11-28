<?php

namespace App\Repository;

use App\Entity\NotificationLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NotificationLog>
 */
class NotificationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationLog::class);
    }

    public function findByAlert(int $alertId): array
    {
        return $this->createQueryBuilder('nl')
            ->andWhere('nl.alertId = :alertId')
            ->setParameter('alertId', $alertId)
            ->orderBy('nl.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByChannel(string $channel): array
    {
        return $this->createQueryBuilder('nl')
            ->andWhere('nl.channel = :channel')
            ->setParameter('channel', $channel)
            ->orderBy('nl.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByStatus(string $status, int $limit = 100): array
    {
        return $this->createQueryBuilder('nl')
            ->andWhere('nl.status = :status')
            ->setParameter('status', $status)
            ->orderBy('nl.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findFailedNotifications(int $limit = 50): array
    {
        return $this->createQueryBuilder('nl')
            ->andWhere('nl.status = :status')
            ->setParameter('status', 'failed')
            ->orderBy('nl.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findPendingNotifications(): array
    {
        return $this->createQueryBuilder('nl')
            ->andWhere('nl.status IN (:statuses)')
            ->setParameter('statuses', ['pending', 'sending'])
            ->orderBy('nl.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findRetryableNotifications(int $maxRetries = 3): array
    {
        return $this->createQueryBuilder('nl')
            ->andWhere('nl.status = :status')
            ->andWhere('nl.retryCount < :maxRetries')
            ->setParameter('status', 'failed')
            ->setParameter('maxRetries', $maxRetries)
            ->orderBy('nl.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByRecipient(string $recipient): array
    {
        return $this->createQueryBuilder('nl')
            ->andWhere('nl.recipient = :recipient')
            ->setParameter('recipient', $recipient)
            ->orderBy('nl.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getNotificationStatistics(): array
    {
        $connection = $this->getEntityManager()->getConnection();
        
        return [
            'total_notifications' => $connection->fetchOne('SELECT COUNT(*) FROM notification_logs'),
            'sent_today' => $connection->fetchOne('SELECT COUNT(*) FROM notification_logs WHERE status = "sent" AND DATE(created_at) = CURDATE()'),
            'failed_today' => $connection->fetchOne('SELECT COUNT(*) FROM notification_logs WHERE status = "failed" AND DATE(created_at) = CURDATE()'),
            'pending_notifications' => $connection->fetchOne('SELECT COUNT(*) FROM notification_logs WHERE status IN ("pending", "sending")'),
            'delivered_notifications' => $connection->fetchOne('SELECT COUNT(*) FROM notification_logs WHERE status IN ("delivered", "read")'),
            'retry_needed' => $connection->fetchOne('SELECT COUNT(*) FROM notification_logs WHERE status = "failed" AND retry_count < 3'),
            'avg_delivery_time' => $connection->fetchOne('
                SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, delivered_at)) 
                FROM notification_logs 
                WHERE status = "delivered" AND delivered_at IS NOT NULL AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            '),
            'success_rate' => $connection->fetchOne('
                SELECT (COUNT(CASE WHEN status IN ("sent", "delivered", "read") THEN 1 END) * 100.0 / COUNT(*)) 
                FROM notification_logs 
                WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            '),
        ];
    }

    public function getChannelStatistics(): array
    {
        return $this->createQueryBuilder('nl')
            ->select('nl.channel, nl.status, COUNT(nl.id) as notification_count')
            ->groupBy('nl.channel, nl.status')
            ->orderBy('nl.channel', 'ASC')
            ->addOrderBy('notification_count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findRecentNotifications(int $limit = 50): array
    {
        return $this->createQueryBuilder('nl')
            ->orderBy('nl.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findNotificationsByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('nl')
            ->andWhere('nl.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('nl.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getDeliveryRatesByChannel(): array
    {
        $connection = $this->getEntityManager()->getConnection();
        
        return $connection->fetchAllAssociative('
            SELECT 
                channel,
                COUNT(*) as total_sent,
                SUM(CASE WHEN status IN ("delivered", "read") THEN 1 ELSE 0 END) as delivered_count,
                (SUM(CASE CASE WHEN status IN ("delivered", "read") THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) as delivery_rate
            FROM notification_logs 
            WHERE status IN ("sent", "delivered", "read", "failed")
            GROUP BY channel
            ORDER BY delivery_rate DESC
        ');
    }

    public function getHourlyNotificationVolume(): array
    {
        $connection = $this->getEntityManager()->getConnection();
        
        return $connection->fetchAllAssociative('
            SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as notification_count
            FROM notification_logs 
            WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY HOUR(created_at)
            ORDER BY hour
        ');
    }

    public function findSlowDeliveries(int $slowThresholdSeconds = 30): array
    {
        return $this->createQueryBuilder('nl')
            ->andWhere('nl.deliveredAt IS NOT NULL')
            ->andWhere('nl.sentAt IS NOT NULL')
            ->andWhere('TIMESTAMPDIFF(SECOND, nl.sentAt, nl.deliveredAt) > :threshold')
            ->setParameter('threshold', $slowThresholdSeconds)
            ->orderBy('nl.createdAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }

    public function findByExternalId(string $externalId): ?NotificationLog
    {
        return $this->createQueryBuilder('nl')
            ->andWhere('nl.externalId = :externalId')
            ->setParameter('externalId', $externalId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function cleanupOldLogs(int $daysToKeep = 90): int
    {
        $cutoffDate = new \DateTime();
        $cutoffDate->modify("-{$daysToKeep} days");

        return $this->createQueryBuilder('nl')
            ->delete()
            ->andWhere('nl.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->execute();
    }
}