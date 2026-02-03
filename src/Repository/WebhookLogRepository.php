<?php

namespace App\Repository;

use App\Entity\WebhookLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebhookLog>
 */
class WebhookLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebhookLog::class);
    }

    /**
     * Busca webhooks por fuente
     *
     * @return WebhookLog[]
     */
    public function findBySource(string $source, int $limit = 100): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.source = :source')
            ->setParameter('source', $source)
            ->orderBy('w.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca webhooks por estado de procesamiento
     *
     * @return WebhookLog[]
     */
    public function findByStatus(string $status, int $limit = 100): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.processingStatus = :status')
            ->setParameter('status', $status)
            ->orderBy('w.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca webhooks fallidos que pueden ser reintentados
     *
     * @return WebhookLog[]
     */
    public function findRetryable(int $maxRetries = 3, int $limit = 50): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.processingStatus = :status')
            ->andWhere('w.retryCount < :maxRetries')
            ->setParameter('status', WebhookLog::STATUS_FAILED)
            ->setParameter('maxRetries', $maxRetries)
            ->orderBy('w.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca webhooks por concesión
     *
     * @return WebhookLog[]
     */
    public function findByConcession(string $concessionCode, int $limit = 100): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.concessionCode = :concession')
            ->setParameter('concession', $concessionCode)
            ->orderBy('w.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca webhooks en un rango de fechas
     *
     * @return WebhookLog[]
     */
    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end, ?string $source = null): array
    {
        $qb = $this->createQueryBuilder('w')
            ->andWhere('w.createdAt >= :start')
            ->andWhere('w.createdAt <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('w.createdAt', 'DESC');

        if ($source !== null) {
            $qb->andWhere('w.source = :source')
                ->setParameter('source', $source);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Obtiene estadísticas generales de webhooks
     */
    public function getStatistics(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT
                source,
                processing_status,
                COUNT(*) as count,
                DATE(created_at) as date
            FROM webhook_log
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY source, processing_status, DATE(created_at)
            ORDER BY date DESC, source
        ';

        return $conn->executeQuery($sql)->fetchAllAssociative();
    }

    /**
     * Obtiene estadísticas por fuente
     */
    public function getStatisticsBySource(): array
    {
        return $this->createQueryBuilder('w')
            ->select('w.source, w.processingStatus, COUNT(w.id) as count')
            ->groupBy('w.source, w.processingStatus')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene conteo por estado
     */
    public function getCountByStatus(): array
    {
        $result = $this->createQueryBuilder('w')
            ->select('w.processingStatus, COUNT(w.id) as count')
            ->groupBy('w.processingStatus')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['processingStatus']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Obtiene webhooks recientes
     *
     * @return WebhookLog[]
     */
    public function findRecent(int $limit = 50): array
    {
        return $this->createQueryBuilder('w')
            ->orderBy('w.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca webhooks pendientes de procesar
     *
     * @return WebhookLog[]
     */
    public function findPending(int $limit = 100): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.processingStatus IN (:statuses)')
            ->setParameter('statuses', [WebhookLog::STATUS_RECEIVED, WebhookLog::STATUS_QUEUED])
            ->orderBy('w.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Limpia webhooks antiguos
     */
    public function cleanupOld(int $daysToKeep = 30): int
    {
        $cutoffDate = new \DateTime("-{$daysToKeep} days");

        return $this->createQueryBuilder('w')
            ->delete()
            ->andWhere('w.createdAt < :cutoff')
            ->andWhere('w.processingStatus = :completed')
            ->setParameter('cutoff', $cutoffDate)
            ->setParameter('completed', WebhookLog::STATUS_COMPLETED)
            ->getQuery()
            ->execute();
    }

    /**
     * Busca webhooks con errores específicos
     *
     * @return WebhookLog[]
     */
    public function findWithError(string $errorPattern, int $limit = 50): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.processingStatus = :status')
            ->andWhere('w.errorMessage LIKE :pattern')
            ->setParameter('status', WebhookLog::STATUS_FAILED)
            ->setParameter('pattern', '%' . $errorPattern . '%')
            ->orderBy('w.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene el tiempo promedio de procesamiento por fuente
     */
    public function getAverageProcessingTime(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT
                source,
                AVG(TIMESTAMPDIFF(SECOND, created_at, processed_at)) as avg_seconds,
                COUNT(*) as count
            FROM webhook_log
            WHERE processed_at IS NOT NULL
            AND processing_status = :status
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY source
        ';

        return $conn->executeQuery($sql, ['status' => WebhookLog::STATUS_COMPLETED])->fetchAllAssociative();
    }

    /**
     * Busca webhooks por Meta Message ID (correlación WhatsApp)
     *
     * @return WebhookLog[]
     */
    public function findByMetaMessageId(string $metaMessageId): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.metaMessageId = :messageId')
            ->setParameter('messageId', $metaMessageId)
            ->orderBy('w.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca webhooks por entidad relacionada
     *
     * @return WebhookLog[]
     */
    public function findByRelatedEntity(string $entityType, int $entityId): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.relatedEntityType = :type')
            ->andWhere('w.relatedEntityId = :id')
            ->setParameter('type', $entityType)
            ->setParameter('id', $entityId)
            ->orderBy('w.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca webhooks WhatsApp relacionados con un mensaje específico
     *
     * @return WebhookLog[]
     */
    public function findWhatsAppWebhooksForMessage(int $messageId): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.relatedEntityType = :type')
            ->andWhere('w.relatedEntityId = :id')
            ->andWhere('w.source IN (:sources)')
            ->setParameter('type', 'whatsapp_message')
            ->setParameter('id', $messageId)
            ->setParameter('sources', [
                WebhookLog::SOURCE_WHATSAPP_STATUS,
                WebhookLog::SOURCE_WHATSAPP_MESSAGE,
                WebhookLog::SOURCE_WHATSAPP_ERROR
            ])
            ->orderBy('w.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene historial de status de un mensaje WhatsApp por su Meta Message ID
     *
     * @return WebhookLog[]
     */
    public function findStatusHistoryByMetaMessageId(string $metaMessageId): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.metaMessageId = :messageId')
            ->andWhere('w.source = :source')
            ->setParameter('messageId', $metaMessageId)
            ->setParameter('source', WebhookLog::SOURCE_WHATSAPP_STATUS)
            ->orderBy('w.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
