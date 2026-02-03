<?php

namespace App\Service;

use App\Entity\Alert;
use App\Entity\AlertRule;
use App\Entity\NotificationLog;
use App\Repository\AlertRuleRepository;
use App\Repository\NotificationLogRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\EmailRecipient;
use Symfony\Component\Notifier\Recipient\SmsRecipient;
use Symfony\Component\Notifier\Channel\BrowserChannel;
use Symfony\Component\Notifier\Channel\EmailChannel;
use Symfony\Component\Notifier\Channel\SmsChannel;

class AlertNotificationService
{
    private array $legacyServices = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotifierInterface $notifier,
        private AlertRuleRepository $alertRuleRepository,
        private NotificationLogRepository $notificationLogRepository,
        private LoggerInterface $logger,
        private AuditLogger $auditLogger,
        private ?object $fcmService = null,
        private ?object $whatsappService = null
    ) {
        // Store legacy services for backward compatibility
        $this->legacyServices = [
            'fcm' => $fcmService,
            'whatsapp' => $whatsappService
        ];
    }

    public function processAlert(Alert $alert): void
    {
        try {
            // Find applicable rules for this alert
            $rules = $this->alertRuleRepository->findRulesForAlert(
                $alert->getSourceType(),
                $alert->getAlertType()
            );

            if (empty($rules)) {
                $this->logger->warning('No notification rules found for alert', [
                    'alert_id' => $alert->getId(),
                    'source_type' => $alert->getSourceType(),
                    'alert_type' => $alert->getAlertType()
                ]);
                return;
            }

            // Process immediate notifications (level 0)
            foreach ($rules as $rule) {
                $this->sendImmediateNotifications($alert, $rule);
            }

            // Log the alert processing
            $this->auditLogger->log(
                'ALERT_PROCESSED',
                'Alert',
                $alert->getId(),
                null,
                null,
                "Alert processed with " . count($rules) . " applicable rules",
                'INFO',
                'NOTIFICATIONS'
            );

        } catch (\Exception $e) {
            $this->logger->error('Failed to process alert notifications', [
                'alert_id' => $alert->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->auditLogger->logSecurityEvent(
                'ALERT_NOTIFICATION_FAILURE',
                "Failed to process notifications for alert {$alert->getId()}: {$e->getMessage()}"
            );
        }
    }

    public function processEscalation(Alert $alert, int $escalationLevel): void
    {
        try {
            $rules = $this->alertRuleRepository->findRulesForAlert(
                $alert->getSourceType(),
                $alert->getAlertType()
            );

            foreach ($rules as $rule) {
                $this->sendEscalationNotifications($alert, $rule, $escalationLevel);
            }

            $alert->incrementEscalationLevel();
            $this->entityManager->flush();

            $this->auditLogger->log(
                'ALERT_ESCALATED',
                'Alert',
                $alert->getId(),
                null,
                ['escalation_level' => $escalationLevel],
                "Alert escalated to level {$escalationLevel}",
                'WARNING',
                'NOTIFICATIONS'
            );

        } catch (\Exception $e) {
            $this->logger->error('Failed to process alert escalation', [
                'alert_id' => $alert->getId(),
                'escalation_level' => $escalationLevel,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function sendImmediateNotifications(Alert $alert, AlertRule $rule): void
    {
        $channels = $rule->getChannelsForEscalationLevel(0);
        
        foreach ($channels as $channel) {
            $this->sendNotification($alert, $rule, $channel, 0);
        }
    }

    private function sendEscalationNotifications(Alert $alert, AlertRule $rule, int $escalationLevel): void
    {
        $channels = $rule->getChannelsForEscalationLevel($escalationLevel);
        
        foreach ($channels as $channel) {
            $this->sendNotification($alert, $rule, $channel, $escalationLevel);
        }
    }

    private function sendNotification(Alert $alert, AlertRule $rule, string $channel, int $escalationLevel): void
    {
        $notificationLog = new NotificationLog();
        $notificationLog->setAlertId($alert->getId())
            ->setChannel($channel)
            ->setStatus('pending');

        try {
            $this->entityManager->persist($notificationLog);

            switch ($channel) {
                case 'browser':
                    $this->sendBrowserNotification($alert, $notificationLog);
                    break;
                case 'email':
                    $this->sendEmailNotification($alert, $notificationLog);
                    break;
                case 'sms':
                    $this->sendSmsNotification($alert, $notificationLog);
                    break;
                case 'push':
                    $this->sendPushNotification($alert, $notificationLog);
                    break;
                case 'whatsapp':
                    $this->sendWhatsAppNotification($alert, $notificationLog);
                    break;
                default:
                    $notificationLog->markAsFailed("Unsupported channel: {$channel}");
            }

            $alert->incrementNotificationCount();
            $this->entityManager->flush();

        } catch (\Exception $e) {
            $notificationLog->markAsFailed($e->getMessage());
            $this->entityManager->flush();
            
            $this->logger->error('Failed to send notification', [
                'alert_id' => $alert->getId(),
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function sendBrowserNotification(Alert $alert, NotificationLog $log): void
    {
        $notification = new Notification(
            $this->formatAlertTitle($alert),
            ['browser']
        );
        $notification->content($this->formatAlertContent($alert));
        $notification->importance($this->mapSeverityToImportance($alert->getSeverity()));

        $this->notifier->send($notification);
        
        $log->setRecipient('browser')
            ->setMessage($this->formatAlertContent($alert))
            ->markAsSent();
    }

    private function sendEmailNotification(Alert $alert, NotificationLog $log): void
    {
        // TODO(human): Configure email recipients based on alert rules or system config
        $recipients = $this->getEmailRecipients($alert);
        
        foreach ($recipients as $email) {
            $notification = new Notification(
                $this->formatAlertTitle($alert),
                ['email']
            );
            $notification->content($this->formatAlertEmailContent($alert));
            $notification->importance($this->mapSeverityToImportance($alert->getSeverity()));

            $recipient = new EmailRecipient($email);
            $this->notifier->send($notification, $recipient);
        }
        
        $log->setRecipient(implode(', ', $recipients))
            ->setMessage($this->formatAlertEmailContent($alert))
            ->markAsSent();
    }

    private function sendSmsNotification(Alert $alert, NotificationLog $log): void
    {
        // TODO(human): Configure SMS recipients based on alert rules or system config
        $phoneNumbers = $this->getSmsRecipients($alert);
        
        foreach ($phoneNumbers as $phone) {
            $notification = new Notification(
                $this->formatAlertTitle($alert),
                ['sms']
            );
            $notification->content($this->formatAlertSmsContent($alert));
            $notification->importance($this->mapSeverityToImportance($alert->getSeverity()));

            $recipient = new SmsRecipient($phone);
            $this->notifier->send($notification, $recipient);
        }
        
        $log->setRecipient(implode(', ', $phoneNumbers))
            ->setMessage($this->formatAlertSmsContent($alert))
            ->markAsSent();
    }

    private function sendPushNotification(Alert $alert, NotificationLog $log): void
    {
        if (!$this->legacyServices['fcm']) {
            $log->markAsFailed('FCM service not available');
            return;
        }

        // Use legacy FCM service for now
        $deviceTokens = $this->getDeviceTokens($alert);
        $title = $this->formatAlertTitle($alert);
        $body = $this->formatAlertContent($alert);

        foreach ($deviceTokens as $token) {
            $result = $this->legacyServices['fcm']->sendNotification($token, $title, $body, [
                'alert_id' => $alert->getId(),
                'severity' => $alert->getSeverity(),
                'source_type' => $alert->getSourceType(),
            ]);

            if ($result) {
                $log->setRecipient($token)
                    ->setMessage($body)
                    ->markAsSent();
            } else {
                $log->markAsFailed('FCM delivery failed');
            }
        }
    }

    private function sendWhatsAppNotification(Alert $alert, NotificationLog $log): void
    {
        if (!$this->legacyServices['whatsapp']) {
            $log->markAsFailed('WhatsApp service not available');
            return;
        }

        // Use legacy WhatsApp service for now
        $phoneNumbers = $this->getWhatsAppRecipients($alert);
        $message = $this->formatAlertWhatsAppContent($alert);

        foreach ($phoneNumbers as $phone) {
            $result = $this->legacyServices['whatsapp']->sendWhatsAppAction($phone, $message);
            
            if ($result) {
                $log->setRecipient($phone)
                    ->setMessage($message)
                    ->markAsSent();
            } else {
                $log->markAsFailed('WhatsApp delivery failed');
            }
        }
    }

    // Helper methods for message formatting
    private function formatAlertTitle(Alert $alert): string
    {
        return "[{$alert->getSeverity()}] {$alert->getTitle()}";
    }

    private function formatAlertContent(Alert $alert): string
    {
        return "{$alert->getDescription()}\n\nSource: {$alert->getSourceType()}\nTime: {$alert->getCreatedAt()->format('Y-m-d H:i:s')}";
    }

    private function formatAlertEmailContent(Alert $alert): string
    {
        return "Alert Details:\n\n" .
               "Title: {$alert->getTitle()}\n" .
               "Description: {$alert->getDescription()}\n" .
               "Severity: {$alert->getSeverity()}\n" .
               "Source: {$alert->getSourceType()}\n" .
               "Created: {$alert->getCreatedAt()->format('Y-m-d H:i:s')}\n" .
               "Alert ID: {$alert->getId()}";
    }

    private function formatAlertSmsContent(Alert $alert): string
    {
        return "[{$alert->getSeverity()}] {$alert->getTitle()}: {$alert->getDescription()}";
    }

    private function formatAlertWhatsAppContent(Alert $alert): string
    {
        return "🚨 *Alert Notification*\n\n" .
               "*{$alert->getTitle()}*\n" .
               "{$alert->getDescription()}\n\n" .
               "Severity: {$alert->getSeverity()}\n" .
               "Source: {$alert->getSourceType()}\n" .
               "Time: {$alert->getCreatedAt()->format('Y-m-d H:i:s')}";
    }

    private function mapSeverityToImportance(string $severity): string
    {
        return match($severity) {
            'critical' => Notification::IMPORTANCE_URGENT,
            'high' => Notification::IMPORTANCE_HIGH,
            'medium' => Notification::IMPORTANCE_MEDIUM,
            'low' => Notification::IMPORTANCE_LOW,
            default => Notification::IMPORTANCE_MEDIUM
        };
    }

    private function getEmailRecipients(Alert $alert): array
    {
        $emails = $_ENV['ALERT_ADMIN_EMAILS'] ?? 'admin@gesvial.cl,sgv@gesvial.cl';
        return array_map('trim', explode(',', $emails));
    }

    private function getSmsRecipients(Alert $alert): array
    {
        $phones = $_ENV['ALERT_ADMIN_PHONES'] ?? '';
        return $phones ? array_map('trim', explode(',', $phones)) : [];
    }

    private function getDeviceTokens(Alert $alert): array
    {
        return [];
    }

    private function getWhatsAppRecipients(Alert $alert): array
    {
        $phones = $_ENV['ALERT_WHATSAPP_PHONES'] ?? '';
        return $phones ? array_map('trim', explode(',', $phones)) : [];
    }
}