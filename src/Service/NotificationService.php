<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

class NotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private WhatsAppService $whatsAppService,
        private LoggerInterface $logger
    ) {}

    /**
     * Send notification through multiple channels
     */
    public function sendNotification(
        array $recipients,
        string $subject,
        string $message,
        array $channels = ['email'],
        string $priority = 'normal',
        array $metadata = []
    ): array {
        $results = [];
        
        foreach ($channels as $channel) {
            switch ($channel) {
                case 'email':
                    $results[$channel] = $this->sendEmailNotification($recipients, $subject, $message, $priority, $metadata);
                    break;
                case 'whatsapp':
                    $results[$channel] = $this->sendWhatsAppNotification($recipients, $subject, $message, $priority, $metadata);
                    break;
                case 'sms':
                    $results[$channel] = $this->sendSmsNotification($recipients, $subject, $message, $priority, $metadata);
                    break;
                default:
                    $this->logger->warning('Unsupported notification channel', ['channel' => $channel]);
                    $results[$channel] = false;
            }
        }
        
        return $results;
    }

    /**
     * Send critical alert notification
     */
    public function sendCriticalAlert(
        array $recipients,
        string $alertType,
        string $message,
        array $details = []
    ): bool {
        $subject = "🚨 ALERTA CRÍTICA - $alertType";
        $fullMessage = $this->formatCriticalAlert($alertType, $message, $details);
        
        // Send through all available channels for critical alerts
        $results = $this->sendNotification(
            $recipients,
            $subject,
            $fullMessage,
            ['email', 'whatsapp'],
            'urgent',
            ['alert_type' => $alertType, 'details' => $details]
        );
        
        // Log the critical alert
        $this->logger->critical('Critical alert sent', [
            'alert_type' => $alertType,
            'message' => $message,
            'recipients' => $recipients,
            'results' => $results,
            'details' => $details
        ]);
        
        return in_array(true, $results, true);
    }

    /**
     * Send warning notification
     */
    public function sendWarning(
        array $recipients,
        string $warningType,
        string $message,
        array $details = []
    ): bool {
        $subject = "⚠️ ADVERTENCIA - $warningType";
        $fullMessage = $this->formatWarning($warningType, $message, $details);
        
        $results = $this->sendNotification(
            $recipients,
            $subject,
            $fullMessage,
            ['email'],
            'high',
            ['warning_type' => $warningType, 'details' => $details]
        );
        
        $this->logger->warning('Warning notification sent', [
            'warning_type' => $warningType,
            'message' => $message,
            'recipients' => $recipients,
            'results' => $results
        ]);
        
        return in_array(true, $results, true);
    }

    /**
     * Send system status notification
     */
    public function sendSystemStatus(
        array $recipients,
        string $status,
        string $message,
        array $metrics = []
    ): bool {
        $emoji = match($status) {
            'online' => '✅',
            'degraded' => '⚠️',
            'offline' => '🔴',
            'maintenance' => '🔧',
            default => 'ℹ️'
        };
        
        $subject = "$emoji Sistema - $status";
        $fullMessage = $this->formatSystemStatus($status, $message, $metrics);
        
        $results = $this->sendNotification(
            $recipients,
            $subject,
            $fullMessage,
            ['email'],
            'normal',
            ['status' => $status, 'metrics' => $metrics]
        );
        
        $this->logger->info('System status notification sent', [
            'status' => $status,
            'message' => $message,
            'recipients' => $recipients,
            'metrics' => $metrics
        ]);
        
        return in_array(true, $results, true);
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(
        array $recipients,
        string $subject,
        string $message,
        string $priority,
        array $metadata
    ): bool {
        try {
            $email = (new Email())
                ->from('sistema@gvops.cl')
                ->subject($subject)
                ->text($message)
                ->html($this->formatEmailHtml($subject, $message, $metadata));

            // Set priority
            if ($priority === 'urgent') {
                $email->priority(Email::PRIORITY_HIGH);
            } elseif ($priority === 'low') {
                $email->priority(Email::PRIORITY_LOW);
            }

            // Add recipients
            foreach ($recipients as $recipient) {
                if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $email->addTo($recipient);
                }
            }

            $this->mailer->send($email);
            
            $this->logger->info('Email notification sent successfully', [
                'recipients' => $recipients,
                'subject' => $subject,
                'priority' => $priority
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send email notification', [
                'recipients' => $recipients,
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Send WhatsApp notification
     */
    private function sendWhatsAppNotification(
        array $recipients,
        string $subject,
        string $message,
        string $priority,
        array $metadata
    ): bool {
        try {
            $priorityEmoji = match($priority) {
                'urgent' => '🚨',
                'high' => '⚠️',
                'normal' => 'ℹ️',
                'low' => '📝',
                default => 'ℹ️'
            };
            
            $whatsappMessage = "$priorityEmoji *$subject*\n\n$message";
            
            $allSent = true;
            foreach ($recipients as $recipient) {
                // Assume recipients are phone numbers for WhatsApp
                if (preg_match('/^\+?[1-9]\d{1,14}$/', $recipient)) {
                    $sent = $this->whatsAppService->sendMessage($recipient, $whatsappMessage);
                    if (!$sent) {
                        $allSent = false;
                    }
                }
            }
            
            if ($allSent) {
                $this->logger->info('WhatsApp notification sent successfully', [
                    'recipients' => $recipients,
                    'subject' => $subject,
                    'priority' => $priority
                ]);
            }
            
            return $allSent;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send WhatsApp notification', [
                'recipients' => $recipients,
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Send SMS notification (placeholder for future implementation)
     */
    private function sendSmsNotification(
        array $recipients,
        string $subject,
        string $message,
        string $priority,
        array $metadata
    ): bool {
        // TODO: Implement SMS gateway integration
        $this->logger->info('SMS notification requested but not implemented', [
            'recipients' => $recipients,
            'subject' => $subject
        ]);
        
        return false;
    }

    /**
     * Format critical alert message
     */
    private function formatCriticalAlert(string $alertType, string $message, array $details): string
    {
        $formatted = "🚨 ALERTA CRÍTICA: $alertType\n\n";
        $formatted .= "$message\n\n";
        
        if (!empty($details)) {
            $formatted .= "DETALLES:\n";
            foreach ($details as $key => $value) {
                $formatted .= "• $key: $value\n";
            }
            $formatted .= "\n";
        }
        
        $formatted .= "Tiempo: " . date('Y-m-d H:i:s') . "\n";
        $formatted .= "Requiere atención inmediata.";
        
        return $formatted;
    }

    /**
     * Format warning message
     */
    private function formatWarning(string $warningType, string $message, array $details): string
    {
        $formatted = "⚠️ ADVERTENCIA: $warningType\n\n";
        $formatted .= "$message\n\n";
        
        if (!empty($details)) {
            $formatted .= "Detalles:\n";
            foreach ($details as $key => $value) {
                $formatted .= "• $key: $value\n";
            }
            $formatted .= "\n";
        }
        
        $formatted .= "Tiempo: " . date('Y-m-d H:i:s');
        
        return $formatted;
    }

    /**
     * Format system status message
     */
    private function formatSystemStatus(string $status, string $message, array $metrics): string
    {
        $formatted = "📊 ESTADO DEL SISTEMA: " . strtoupper($status) . "\n\n";
        $formatted .= "$message\n\n";
        
        if (!empty($metrics)) {
            $formatted .= "Métricas:\n";
            foreach ($metrics as $metric => $value) {
                $formatted .= "• $metric: $value\n";
            }
            $formatted .= "\n";
        }
        
        $formatted .= "Tiempo: " . date('Y-m-d H:i:s');
        
        return $formatted;
    }

    /**
     * Format email HTML content
     */
    private function formatEmailHtml(string $subject, string $message, array $metadata): string
    {
        $priority = $metadata['priority'] ?? 'normal';
        $bgColor = match($priority) {
            'urgent' => '#fee2e2',
            'high' => '#fef3c7',
            'normal' => '#f3f4f6',
            'low' => '#e5f3ff',
            default => '#f3f4f6'
        };
        
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: $bgColor; padding: 20px; border-radius: 8px;'>
            <h2 style='color: #1f2937; margin-bottom: 20px;'>$subject</h2>
            <div style='background-color: white; padding: 15px; border-radius: 6px; border-left: 4px solid #3b82f6;'>
                " . nl2br(htmlspecialchars($message)) . "
            </div>
            <div style='margin-top: 20px; font-size: 12px; color: #6b7280;'>
                SGV - " . date('Y-m-d H:i:s') . "
            </div>
        </div>";
    }
}