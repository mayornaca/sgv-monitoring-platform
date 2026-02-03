<?php

namespace App\Repository;

use App\Entity\AlertRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AlertRule>
 */
class AlertRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlertRule::class);
    }

    public function findActiveRules(): array
    {
        return $this->createQueryBuilder('ar')
            ->andWhere('ar.active = :active')
            ->setParameter('active', true)
            ->orderBy('ar.priority', 'DESC')
            ->addOrderBy('ar.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findRulesForAlert(string $sourceType = null, string $alertType = null): array
    {
        $qb = $this->createQueryBuilder('ar')
            ->andWhere('ar.active = :active')
            ->setParameter('active', true);

        if ($sourceType) {
            $qb->andWhere('ar.sourceType IS NULL OR ar.sourceType = :sourceType')
                ->setParameter('sourceType', $sourceType);
        }

        if ($alertType) {
            $qb->andWhere('ar.alertType IS NULL OR ar.alertType = :alertType')
                ->setParameter('alertType', $alertType);
        }

        return $qb->orderBy('ar.priority', 'DESC')
            ->addOrderBy('ar.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySourceType(string $sourceType): array
    {
        return $this->createQueryBuilder('ar')
            ->andWhere('ar.sourceType = :sourceType OR ar.sourceType IS NULL')
            ->andWhere('ar.active = :active')
            ->setParameter('sourceType', $sourceType)
            ->setParameter('active', true)
            ->orderBy('ar.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByAlertType(string $alertType): array
    {
        return $this->createQueryBuilder('ar')
            ->andWhere('ar.alertType = :alertType OR ar.alertType IS NULL')
            ->andWhere('ar.active = :active')
            ->setParameter('alertType', $alertType)
            ->setParameter('active', true)
            ->orderBy('ar.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByPriority(string $priority): array
    {
        return $this->createQueryBuilder('ar')
            ->andWhere('ar.priority = :priority')
            ->andWhere('ar.active = :active')
            ->setParameter('priority', $priority)
            ->setParameter('active', true)
            ->orderBy('ar.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findGlobalRules(): array
    {
        return $this->createQueryBuilder('ar')
            ->andWhere('ar.sourceType IS NULL')
            ->andWhere('ar.alertType IS NULL')
            ->andWhere('ar.active = :active')
            ->setParameter('active', true)
            ->orderBy('ar.priority', 'DESC')
            ->addOrderBy('ar.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findSpecificRules(): array
    {
        return $this->createQueryBuilder('ar')
            ->andWhere('ar.sourceType IS NOT NULL OR ar.alertType IS NOT NULL')
            ->andWhere('ar.active = :active')
            ->setParameter('active', true)
            ->orderBy('ar.priority', 'DESC')
            ->addOrderBy('ar.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findRulesWithChannel(string $channel): array
    {
        return $this->createQueryBuilder('ar')
            ->andWhere('JSON_CONTAINS(ar.channels, :channel)')
            ->andWhere('ar.active = :active')
            ->setParameter('channel', json_encode($channel))
            ->setParameter('active', true)
            ->orderBy('ar.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findRulesWithEscalationTime(int $minutes): array
    {
        return $this->createQueryBuilder('ar')
            ->andWhere('JSON_CONTAINS(ar.escalationTimes, :minutes)')
            ->andWhere('ar.active = :active')
            ->setParameter('minutes', json_encode($minutes))
            ->setParameter('active', true)
            ->orderBy('ar.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getRuleStatistics(): array
    {
        $connection = $this->getEntityManager()->getConnection();
        
        return [
            'total_rules' => $connection->fetchOne('SELECT COUNT(*) FROM alert_rules'),
            'active_rules' => $connection->fetchOne('SELECT COUNT(*) FROM alert_rules WHERE active = 1'),
            'global_rules' => $connection->fetchOne('SELECT COUNT(*) FROM alert_rules WHERE source_type IS NULL AND alert_type IS NULL AND active = 1'),
            'specific_rules' => $connection->fetchOne('SELECT COUNT(*) FROM alert_rules WHERE (source_type IS NOT NULL OR alert_type IS NOT NULL) AND active = 1'),
            'critical_priority_rules' => $connection->fetchOne('SELECT COUNT(*) FROM alert_rules WHERE priority = "critical" AND active = 1'),
            'rules_by_priority' => $connection->fetchAllAssociative('SELECT priority, COUNT(*) as count FROM alert_rules WHERE active = 1 GROUP BY priority ORDER BY count DESC'),
        ];
    }

    public function getMostUsedChannels(): array
    {
        return $this->createQueryBuilder('ar')
            ->select('ar.channels')
            ->andWhere('ar.active = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    public function findCreatedBy(int $userId): array
    {
        return $this->createQueryBuilder('ar')
            ->andWhere('ar.createdBy = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('ar.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findRecentlyCreated(int $days = 7, int $limit = 10): array
    {
        $sinceDate = new \DateTime();
        $sinceDate->modify("-{$days} days");

        return $this->createQueryBuilder('ar')
            ->andWhere('ar.createdAt >= :since')
            ->setParameter('since', $sinceDate)
            ->orderBy('ar.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findRecentlyUpdated(int $days = 7, int $limit = 10): array
    {
        $sinceDate = new \DateTime();
        $sinceDate->modify("-{$days} days");

        return $this->createQueryBuilder('ar')
            ->andWhere('ar.updatedAt >= :since')
            ->setParameter('since', $sinceDate)
            ->orderBy('ar.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}