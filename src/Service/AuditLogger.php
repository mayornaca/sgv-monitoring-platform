<?php

namespace App\Service;

use App\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack
    ) {}

    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        string $level = 'INFO',
        ?string $source = 'WEB'
    ): void {
        $audit = new AuditLog();
        $audit->setAction($action)
            ->setEntityType($entityType)
            ->setEntityId($entityId)
            ->setOldValues($oldValues)
            ->setNewValues($newValues)
            ->setDescription($description)
            ->setLevel($level)
            ->setSource($source);

        $user = $this->security->getUser();
        if ($user) {
            $audit->setUserId($user->getId())
                ->setUsername($user->getUsername());
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $audit->setIpAddress($this->getClientIp($request))
                ->setUserAgent($request->headers->get('User-Agent'));
        }

        try {
            $this->entityManager->persist($audit);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            error_log("AuditLogger failed: " . $e->getMessage());
        }
    }

    public function logUserAction(string $action, ?int $userId = null, ?string $description = null): void
    {
        $this->log($action, 'User', $userId, null, null, $description, 'INFO', 'USER_MANAGEMENT');
    }

    public function logLogin(?int $userId = null, bool $successful = true): void
    {
        $action = $successful ? 'USER_LOGIN_SUCCESS' : 'USER_LOGIN_FAILED';
        $level = $successful ? 'INFO' : 'WARNING';
        $this->log($action, 'Authentication', $userId, null, null, null, $level, 'AUTH');
    }

    public function logLogout(?int $userId = null): void
    {
        $this->log('USER_LOGOUT', 'Authentication', $userId, null, null, null, 'INFO', 'AUTH');
    }

    public function logEntityChange(
        string $action,
        string $entityType,
        int $entityId,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $this->log($action, $entityType, $entityId, $oldValues, $newValues, null, 'INFO', 'ENTITY');
    }

    public function logSystemEvent(string $action, ?string $description = null, string $level = 'INFO'): void
    {
        $this->log($action, null, null, null, null, $description, $level, 'SYSTEM');
    }

    public function logSecurityEvent(string $action, ?string $description = null): void
    {
        $this->log($action, null, null, null, null, $description, 'WARNING', 'SECURITY');
    }

    private function getClientIp($request): ?string
    {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if ($ip = $request->server->get($key)) {
                $ip = trim(explode(',', $ip)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $request->getClientIp();
    }

    public function getRecentLogs(int $limit = 50, ?string $level = null, ?string $source = null): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(AuditLog::class, 'a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($level) {
            $qb->andWhere('a.level = :level')
                ->setParameter('level', $level);
        }

        if ($source) {
            $qb->andWhere('a.source = :source')
                ->setParameter('source', $source);
        }

        return $qb->getQuery()->getResult();
    }

    public function getUserActivityLogs(int $userId, int $limit = 20): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(AuditLog::class, 'a')
            ->where('a.userId = :userId')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();
    }

    public function getSystemStats(): array
    {
        $connection = $this->entityManager->getConnection();
        
        return [
            'total_logs' => $connection->fetchOne('SELECT COUNT(*) FROM audit_log'),
            'today_logs' => $connection->fetchOne('SELECT COUNT(*) FROM audit_log WHERE DATE(created_at) = CURDATE()'),
            'week_logs' => $connection->fetchOne('SELECT COUNT(*) FROM audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
            'error_logs' => $connection->fetchOne('SELECT COUNT(*) FROM audit_log WHERE level IN ("ERROR", "CRITICAL")'),
            'warning_logs' => $connection->fetchOne('SELECT COUNT(*) FROM audit_log WHERE level = "WARNING"'),
            'unique_users_today' => $connection->fetchOne('SELECT COUNT(DISTINCT user_id) FROM audit_log WHERE DATE(created_at) = CURDATE() AND user_id IS NOT NULL'),
        ];
    }
}