<?php

namespace App\Service;

use App\Entity\Alert;
use App\Repository\AlertRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AlertEscalationService
{
    private array $escalationConfig = [
        'critical' => [
            'levels' => [
                ['time' => 0, 'recipients' => ['oncall'], 'channels' => ['email', 'whatsapp']],
                ['time' => 15, 'recipients' => ['team_lead'], 'channels' => ['email', 'whatsapp']],
                ['time' => 30, 'recipients' => ['manager'], 'channels' => ['email', 'whatsapp']],
                ['time' => 60, 'recipients' => ['director'], 'channels' => ['email']]
            ]
        ],
        'high' => [
            'levels' => [
                ['time' => 0, 'recipients' => ['oncall'], 'channels' => ['email']],
                ['time' => 30, 'recipients' => ['team_lead'], 'channels' => ['email']],
                ['time' => 120, 'recipients' => ['manager'], 'channels' => ['email']]
            ]
        ],
        'medium' => [
            'levels' => [
                ['time' => 0, 'recipients' => ['oncall'], 'channels' => ['email']],
                ['time' => 60, 'recipients' => ['team_lead'], 'channels' => ['email']]
            ]
        ],
        'low' => [
            'levels' => [
                ['time' => 0, 'recipients' => ['oncall'], 'channels' => ['email']]
            ]
        ]
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AlertRepository $alertRepository,
        private UserRepository $userRepository,
        private NotificationService $notificationService,
        private LoggerInterface $logger
    ) {}

    /**
     * Process escalations for all active alerts
     */
    public function processEscalations(): array
    {
        $results = [];
        
        // Get all active alerts that need processing
        $alerts = $this->alertRepository->findActiveAlerts();
        
        foreach ($alerts as $alert) {
            $escalationResult = $this->processAlertEscalation($alert);
            if ($escalationResult) {
                $results[] = $escalationResult;
            }
        }
        
        $this->logger->info('Escalation process completed', [
            'total_alerts' => count($alerts),
            'escalations_performed' => count($results)
        ]);
        
        return $results;
    }

    /**
     * Process escalation for a specific alert
     */
    public function processAlertEscalation(Alert $alert): ?array
    {
        if (!$alert->isActive()) {
            return null;
        }

        $ageInMinutes = $alert->getAgeInMinutes();
        $currentLevel = $alert->getEscalationLevel();
        $severity = $alert->getSeverity();
        
        // Get escalation configuration for this severity
        $config = $this->escalationConfig[$severity] ?? $this->escalationConfig['low'];
        
        // Check if we need to escalate
        $nextLevel = $this->findNextEscalationLevel($config['levels'], $ageInMinutes, $currentLevel);
        
        if ($nextLevel !== null && $nextLevel > $currentLevel) {
            return $this->escalateAlert($alert, $nextLevel, $config['levels'][$nextLevel]);
        }
        
        return null;
    }

    /**
     * Manually escalate an alert
     */
    public function manualEscalation(Alert $alert, string $reason, int $targetLevel = null): array
    {
        $severity = $alert->getSeverity();
        $config = $this->escalationConfig[$severity] ?? $this->escalationConfig['low'];
        
        if ($targetLevel === null) {
            $targetLevel = $alert->getEscalationLevel() + 1;
        }
        
        // Ensure target level exists in configuration
        if (!isset($config['levels'][$targetLevel])) {
            $targetLevel = count($config['levels']) - 1;
        }
        
        $result = $this->escalateAlert($alert, $targetLevel, $config['levels'][$targetLevel], $reason);
        
        $this->logger->warning('Manual escalation performed', [
            'alert_id' => $alert->getId(),
            'reason' => $reason,
            'target_level' => $targetLevel,
            'performed_by' => 'manual'
        ]);
        
        return $result;
    }

    /**
     * Create a new alert and start escalation process
     */
    public function createAlert(
        string $title,
        string $description,
        string $severity,
        string $alertType,
        string $sourceType,
        ?string $sourceId = null,
        array $metadata = []
    ): Alert {
        $alert = new Alert();
        $alert->setTitle($title)
              ->setDescription($description)
              ->setSeverity($severity)
              ->setAlertType($alertType)
              ->setSourceType($sourceType)
              ->setSourceId($sourceId)
              ->setMetadata($metadata)
              ->setStatus('active')
              ->setEscalationLevel(0);

        $this->entityManager->persist($alert);
        $this->entityManager->flush();

        // Start immediate escalation process
        $this->processAlertEscalation($alert);

        $this->logger->info('New alert created', [
            'alert_id' => $alert->getId(),
            'title' => $title,
            'severity' => $severity,
            'source' => "$sourceType:$sourceId"
        ]);

        return $alert;
    }

    /**
     * Acknowledge an alert
     */
    public function acknowledgeAlert(Alert $alert, int $userId): void
    {
        $alert->acknowledge($userId);
        $this->entityManager->flush();

        $this->logger->info('Alert acknowledged', [
            'alert_id' => $alert->getId(),
            'acknowledged_by' => $userId,
            'age_minutes' => $alert->getAgeInMinutes()
        ]);
    }

    /**
     * Resolve an alert
     */
    public function resolveAlert(Alert $alert, int $userId, string $notes = null): void
    {
        $alert->resolve($userId, $notes);
        $this->entityManager->flush();

        $this->logger->info('Alert resolved', [
            'alert_id' => $alert->getId(),
            'resolved_by' => $userId,
            'age_minutes' => $alert->getAgeInMinutes(),
            'escalation_level' => $alert->getEscalationLevel()
        ]);
    }

    /**
     * Get escalation statistics
     */
    public function getEscalationStats(): array
    {
        $activeAlerts = $this->alertRepository->findBy(['status' => 'active']);
        $acknowledgedAlerts = $this->alertRepository->findBy(['status' => 'acknowledged']);
        $resolvedToday = $this->alertRepository->findResolvedInTimeframe(new \DateTime('-1 day'));

        $severityStats = [];
        foreach (['critical', 'high', 'medium', 'low'] as $severity) {
            $count = $this->alertRepository->countBySeverityAndStatus($severity, 'active');
            $severityStats[$severity] = $count;
        }

        return [
            'active_alerts' => count($activeAlerts),
            'acknowledged_alerts' => count($acknowledgedAlerts),
            'resolved_today' => count($resolvedToday),
            'severity_breakdown' => $severityStats,
            'avg_resolution_time' => $this->calculateAverageResolutionTime(),
            'escalation_levels' => $this->getEscalationLevelStats()
        ];
    }

    /**
     * Find the next escalation level based on age and configuration
     */
    private function findNextEscalationLevel(array $levels, int $ageInMinutes, int $currentLevel): ?int
    {
        foreach ($levels as $index => $level) {
            if ($ageInMinutes >= $level['time'] && $index > $currentLevel) {
                return $index;
            }
        }
        
        return null;
    }

    /**
     * Escalate an alert to the specified level
     */
    private function escalateAlert(Alert $alert, int $level, array $levelConfig, string $reason = 'automatic'): array
    {
        $alert->setEscalationLevel($level);
        $alert->incrementNotificationCount();
        
        // Get recipients based on configuration
        $recipients = $this->getRecipientsForRoles($levelConfig['recipients']);
        $channels = $levelConfig['channels'];

        // Send notifications
        $notificationResults = $this->notificationService->sendNotification(
            $recipients,
            "🚨 Escalation Level $level - {$alert->getTitle()}",
            $this->formatEscalationMessage($alert, $level, $reason),
            $channels,
            $alert->getSeverity() === 'critical' ? 'urgent' : 'high',
            [
                'alert_id' => $alert->getId(),
                'escalation_level' => $level,
                'severity' => $alert->getSeverity(),
                'reason' => $reason
            ]
        );

        $this->entityManager->flush();

        $result = [
            'alert_id' => $alert->getId(),
            'level' => $level,
            'recipients' => $recipients,
            'channels' => $channels,
            'reason' => $reason,
            'notification_results' => $notificationResults
        ];

        $this->logger->warning('Alert escalated', $result);

        return $result;
    }

    /**
     * Get recipient emails for specified roles
     */
    private function getRecipientsForRoles(array $roles): array
    {
        $recipients = [];
        
        foreach ($roles as $role) {
            switch ($role) {
                case 'oncall':
                    // Get on-call users (could be based on schedule)
                    $users = $this->userRepository->findByRole('ROLE_ONCALL');
                    break;
                case 'team_lead':
                    $users = $this->userRepository->findByRole('ROLE_TEAM_LEAD');
                    break;
                case 'manager':
                    $users = $this->userRepository->findByRole('ROLE_MANAGER');
                    break;
                case 'director':
                    $users = $this->userRepository->findByRole('ROLE_DIRECTOR');
                    break;
                default:
                    $users = $this->userRepository->findByRole('ROLE_ADMIN');
            }
            
            foreach ($users as $user) {
                $recipients[] = $user->getEmail();
            }
        }
        
        // Fallback to admin users if no specific role users found
        if (empty($recipients)) {
            $adminUsers = $this->userRepository->findByRole('ROLE_ADMIN');
            foreach ($adminUsers as $user) {
                $recipients[] = $user->getEmail();
            }
        }
        
        return array_unique($recipients);
    }

    /**
     * Format escalation message
     */
    private function formatEscalationMessage(Alert $alert, int $level, string $reason): string
    {
        $ageFormatted = $this->formatAge($alert->getAgeInMinutes());
        
        return "⚠️ ESCALATION ALERT - Level $level\n\n" .
               "Title: {$alert->getTitle()}\n" .
               "Severity: " . strtoupper($alert->getSeverity()) . "\n" .
               "Age: $ageFormatted\n" .
               "Reason: $reason\n\n" .
               "Description:\n{$alert->getDescription()}\n\n" .
               "Source: {$alert->getSourceType()}" . 
               ($alert->getSourceId() ? " ({$alert->getSourceId()})" : "") . "\n" .
               "Alert ID: {$alert->getId()}\n" .
               "Created: " . $alert->getCreatedAt()->format('Y-m-d H:i:s');
    }

    /**
     * Format age in minutes to human readable format
     */
    private function formatAge(int $minutes): string
    {
        if ($minutes < 60) {
            return "$minutes minutes";
        } elseif ($minutes < 1440) {
            $hours = floor($minutes / 60);
            $remainingMinutes = $minutes % 60;
            return "$hours hours, $remainingMinutes minutes";
        } else {
            $days = floor($minutes / 1440);
            $remainingHours = floor(($minutes % 1440) / 60);
            return "$days days, $remainingHours hours";
        }
    }

    /**
     * Calculate average resolution time
     */
    private function calculateAverageResolutionTime(): float
    {
        $resolvedAlerts = $this->alertRepository->findResolvedInTimeframe(new \DateTime('-7 days'));
        
        if (empty($resolvedAlerts)) {
            return 0.0;
        }
        
        $totalMinutes = 0;
        foreach ($resolvedAlerts as $alert) {
            $resolvedAt = $alert->getResolvedAt();
            $createdAt = $alert->getCreatedAt();
            $diff = $resolvedAt->getTimestamp() - $createdAt->getTimestamp();
            $totalMinutes += $diff / 60;
        }
        
        return $totalMinutes / count($resolvedAlerts);
    }

    /**
     * Get escalation level statistics
     */
    private function getEscalationLevelStats(): array
    {
        $stats = [];
        for ($level = 0; $level <= 3; $level++) {
            $count = $this->alertRepository->countByEscalationLevel($level);
            $stats["level_$level"] = $count;
        }
        
        return $stats;
    }
}