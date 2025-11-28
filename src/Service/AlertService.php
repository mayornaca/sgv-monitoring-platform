<?php

namespace App\Service;

use App\Entity\Alert;
use App\Notification\AlertNotification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

class AlertService
{
    public function __construct(
        private NotifierInterface $notifier
    ) {}

    public function sendAlertNotification(Alert $alert): void
    {
        // Crear la notificación
        $notification = new AlertNotification($alert);
        
        // Enviar a los administradores configurados en notifier.yaml
        // Los canales se seleccionan automáticamente según la importancia y channel_policy
        $this->notifier->send($notification, ...$this->notifier->getAdminRecipients());
    }
    
    public function sendAlertToEmail(Alert $alert, string $email): void
    {
        $notification = new AlertNotification($alert);
        $recipient = new Recipient($email);
        
        $this->notifier->send($notification, $recipient);
    }
}