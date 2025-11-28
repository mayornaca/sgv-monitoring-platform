<?php

namespace App\Controller;

use App\Service\AuditLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/audit')]
#[IsGranted('ROLE_ADMIN')]
class AuditController extends AbstractController
{
    public function __construct(
        private AuditLogger $auditLogger
    ) {}

    #[Route('/', name: 'app_audit_logs')]
    public function index(Request $request): Response
    {
        $level = $request->query->get('level');
        $source = $request->query->get('source');
        $limit = (int) $request->query->get('limit', 50);

        $logs = $this->auditLogger->getRecentLogs($limit, $level, $source);
        $stats = $this->auditLogger->getSystemStats();

        return $this->render('audit/index.html.twig', [
            'logs' => $logs,
            'stats' => $stats,
            'current_level' => $level,
            'current_source' => $source,
            'current_limit' => $limit,
        ]);
    }

    #[Route('/api/logs', name: 'app_audit_api_logs', methods: ['GET'])]
    public function apiLogs(Request $request): JsonResponse
    {
        $level = $request->query->get('level');
        $source = $request->query->get('source');
        $limit = min((int) $request->query->get('limit', 50), 500);

        $logs = $this->auditLogger->getRecentLogs($limit, $level, $source);
        
        $response = array_map(function ($log) {
            return [
                'id' => $log->getId(),
                'action' => $log->getAction(),
                'entityType' => $log->getEntityType(),
                'entityId' => $log->getEntityId(),
                'userId' => $log->getUserId(),
                'username' => $log->getUsername(),
                'ipAddress' => $log->getIpAddress(),
                'description' => $log->getDescription(),
                'level' => $log->getLevel(),
                'source' => $log->getSource(),
                'createdAt' => $log->getCreatedAt()?->format('c'),
                'oldValues' => $log->getOldValues(),
                'newValues' => $log->getNewValues(),
            ];
        }, $logs);

        return $this->json([
            'logs' => $response,
            'stats' => $this->auditLogger->getSystemStats(),
            'timestamp' => time(),
        ]);
    }

    #[Route('/user/{userId}', name: 'app_audit_user_logs', requirements: ['userId' => '\d+'])]
    public function userLogs(int $userId): Response
    {
        $logs = $this->auditLogger->getUserActivityLogs($userId);
        
        return $this->render('audit/user_logs.html.twig', [
            'logs' => $logs,
            'userId' => $userId,
        ]);
    }

    #[Route('/stats', name: 'app_audit_stats')]
    public function stats(): JsonResponse
    {
        return $this->json($this->auditLogger->getSystemStats());
    }

    #[Route('/export', name: 'app_audit_export')]
    public function export(Request $request): Response
    {
        $level = $request->query->get('level');
        $source = $request->query->get('source');
        $limit = min((int) $request->query->get('limit', 1000), 5000);

        $logs = $this->auditLogger->getRecentLogs($limit, $level, $source);

        $csvContent = "ID,Action,Entity Type,Entity ID,User ID,Username,IP Address,Level,Source,Description,Created At\n";
        
        foreach ($logs as $log) {
            $csvContent .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $log->getId(),
                $this->escapeCsv($log->getAction()),
                $this->escapeCsv($log->getEntityType()),
                $log->getEntityId() ?? '',
                $log->getUserId() ?? '',
                $this->escapeCsv($log->getUsername()),
                $this->escapeCsv($log->getIpAddress()),
                $log->getLevel(),
                $this->escapeCsv($log->getSource()),
                $this->escapeCsv($log->getDescription()),
                $log->getCreatedAt()?->format('Y-m-d H:i:s')
            );
        }

        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="audit_logs_' . date('Y-m-d_H-i-s') . '.csv"');

        return $response;
    }

    private function escapeCsv(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        
        return '"' . str_replace('"', '""', $value) . '"';
    }
}