<?php

namespace App\Notification;

use App\Entity\Alert;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Notification\EmailNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;

class AlertNotification extends Notification implements EmailNotificationInterface
{
    private Alert $alert;

    public function __construct(Alert $alert)
    {
        $this->alert = $alert;
        
        // Establecer el título y la importancia según la severidad
        $title = sprintf('[%s] %s', strtoupper($alert->getSeverity()), $alert->getTitle());
        
        parent::__construct($title);
        
        // La importancia determina qué canales se usan según channel_policy en notifier.yaml
        $this->importance($this->mapSeverityToImportance());
    }

    public function asEmailMessage(EmailRecipientInterface $recipient, string $transport = null): ?EmailMessage
    {
        $message = EmailMessage::fromNotification($this, $recipient);
        $message->getMessage()
            ->htmlTemplate('emails/alert_notification.html.twig')
            ->context([
                'alert' => $this->alert,
                'severity' => $this->alert->getSeverity(),
                'description' => $this->alert->getDescription(),
                'createdAt' => $this->alert->getCreatedAt(),
            ]);

        return $message;
    }

    private function mapSeverityToImportance(): string
    {
        // Mapear severidad de alerta a importancia de notificación
        return match($this->alert->getSeverity()) {
            'critical' => self::IMPORTANCE_URGENT,
            'high' => self::IMPORTANCE_HIGH,
            'medium' => self::IMPORTANCE_MEDIUM,
            'low', 'info' => self::IMPORTANCE_LOW,
            default => self::IMPORTANCE_MEDIUM,
        };
    }
    
    public function getImportance(): string
    {
        return $this->mapSeverityToImportance();
    }
}