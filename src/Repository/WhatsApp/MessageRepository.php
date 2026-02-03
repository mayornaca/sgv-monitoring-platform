<?php

namespace App\Repository\WhatsApp;

use App\Entity\WhatsApp\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Encuentra un mensaje por su ID de Meta
     */
    public function findOneByMetaMessageId(string $metaMessageId): ?Message
    {
        return $this->createQueryBuilder('m')
            ->where('m.metaMessageId = :metaMessageId')
            ->setParameter('metaMessageId', $metaMessageId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Encuentra mensajes pendientes de envío
     *
     * @return Message[]
     */
    public function findPending(int $limit = 100): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.estado = :estado')
            ->setParameter('estado', Message::STATUS_PENDING)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene estadísticas de mensajes
     */
    public function getStats(\DateTimeInterface $desde = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->select('m.estado, COUNT(m.id) as total')
            ->groupBy('m.estado');

        if ($desde) {
            $qb->where('m.createdAt >= :desde')
                ->setParameter('desde', $desde);
        }

        $results = $qb->getQuery()->getResult();

        $stats = [
            'total' => 0,
            'pending' => 0,
            'sent' => 0,
            'delivered' => 0,
            'read' => 0,
            'failed' => 0,
        ];

        foreach ($results as $row) {
            $stats[$row['estado']] = (int) $row['total'];
            $stats['total'] += (int) $row['total'];
        }

        // Calcular tasa de entrega
        $sent = $stats['sent'] + $stats['delivered'] + $stats['read'];
        $stats['delivery_rate'] = $sent > 0 ? round(($stats['delivered'] + $stats['read']) / $sent * 100, 2) : 0;
        $stats['read_rate'] = $stats['delivered'] > 0 ? round($stats['read'] / $stats['delivered'] * 100, 2) : 0;

        return $stats;
    }

    /**
     * Encuentra mensajes fallidos que pueden reintentarse
     *
     * @return Message[]
     */
    public function findRetryable(int $maxRetries = 3): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.estado = :estado')
            ->andWhere('m.retryCount < :maxRetries')
            ->setParameter('estado', Message::STATUS_FAILED)
            ->setParameter('maxRetries', $maxRetries)
            ->orderBy('m.updatedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra mensajes recientes de un destinatario
     *
     * @return Message[]
     */
    public function findRecentByRecipient(int $recipientId, int $limit = 10): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.recipient = :recipientId')
            ->setParameter('recipientId', $recipientId)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
