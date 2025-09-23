<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private Connection $connection,
        private UserRepository $userRepository
    ) {}

    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(): Response
    {
        // Redirigir a EasyAdmin
        return $this->redirectToRoute('admin');
    }

    private function getSystemMetrics(): array
    {
        try {
            // Database health
            $dbStatus = $this->connection->fetchOne('SELECT 1');
            
            // System tables count
            $systemTables = $this->connection->fetchOne("
                SELECT COUNT(*) FROM information_schema.tables 
                WHERE table_schema = DATABASE() AND table_name LIKE 'tbl_%'
            ");

            // Recent activity (last 24h)
            $recentActivity = $this->connection->fetchOne("
                SELECT COUNT(*) FROM security_user 
                WHERE last_login > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");

            return [
                'database_status' => $dbStatus ? 'healthy' : 'error',
                'business_tables' => $systemTables,
                'recent_activity' => $recentActivity,
                'system_status' => 'operational',
                'uptime' => $this->getSystemUptime(),
            ];
        } catch (\Exception $e) {
            return [
                'database_status' => 'error',
                'business_tables' => 0,
                'recent_activity' => 0,
                'system_status' => 'error',
                'uptime' => 0,
            ];
        }
    }

    private function getUserMetrics(): array
    {
        try {
            $totalUsers = $this->userRepository->count([]);
            $activeUsers = $this->userRepository->count(['isActive' => true]);
            $adminUsers = $this->connection->fetchOne("
                SELECT COUNT(*) FROM security_user 
                WHERE JSON_CONTAINS(roles, '\"ROLE_ADMIN\"')
            ");
            $usersNeedingPasswordChange = $this->userRepository->count(['mustChangePassword' => true]);

            return [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'admin_users' => $adminUsers,
                'password_reset_required' => $usersNeedingPasswordChange,
                'activity_rate' => $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 1) : 0,
            ];
        } catch (\Exception $e) {
            return [
                'total_users' => 0,
                'active_users' => 0,
                'admin_users' => 0,
                'password_reset_required' => 0,
                'activity_rate' => 0,
            ];
        }
    }

    private function getBusinessMetrics(): array
    {
        try {
            // Sample business metrics from key tables
            $metrics = [];

            // Vehicles/Fleet data
            if ($this->tableExists('tbl_01_marca')) {
                $metrics['brands'] = $this->connection->fetchOne("SELECT COUNT(*) FROM tbl_01_marca");
            }

            if ($this->tableExists('tbl_02_modelo')) {
                $metrics['models'] = $this->connection->fetchOne("SELECT COUNT(*) FROM tbl_02_modelo");
            }

            if ($this->tableExists('tbl_04_mantenciones')) {
                $metrics['maintenance_records'] = $this->connection->fetchOne("SELECT COUNT(*) FROM tbl_04_mantenciones");
                
                // Recent maintenance (last 30 days)
                $metrics['recent_maintenance'] = $this->connection->fetchOne("
                    SELECT COUNT(*) FROM tbl_04_mantenciones 
                    WHERE fecha_mantencion > DATE_SUB(NOW(), INTERVAL 30 DAY)
                ");
            }

            if ($this->tableExists('tbl_06_concesionaria')) {
                $metrics['dealers'] = $this->connection->fetchOne("SELECT COUNT(*) FROM tbl_06_concesionaria");
            }

            return $metrics;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function tableExists(string $tableName): bool
    {
        try {
            $result = $this->connection->fetchOne("
                SELECT COUNT(*) FROM information_schema.tables 
                WHERE table_schema = DATABASE() AND table_name = ?
            ", [$tableName]);
            return $result > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getSystemUptime(): string
    {
        try {
            $uptime = $this->connection->fetchOne('SHOW STATUS LIKE "Uptime"');
            if ($uptime) {
                $seconds = (int) $uptime;
                $days = floor($seconds / 86400);
                $hours = floor(($seconds % 86400) / 3600);
                $minutes = floor(($seconds % 3600) / 60);
                
                if ($days > 0) {
                    return "{$days}d {$hours}h";
                } elseif ($hours > 0) {
                    return "{$hours}h {$minutes}m";
                } else {
                    return "{$minutes}m";
                }
            }
            return 'Unknown';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    #[Route('/api/metrics', name: 'app_api_metrics', methods: ['GET'])]
    public function apiMetrics(): Response
    {
        return $this->json([
            'system' => $this->getSystemMetrics(),
            'users' => $this->getUserMetrics(),
            'business' => $this->getBusinessMetrics(),
            'timestamp' => time(),
        ]);
    }
}