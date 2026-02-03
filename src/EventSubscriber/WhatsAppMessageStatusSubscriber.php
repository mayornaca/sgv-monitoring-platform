<?php

namespace App\EventSubscriber;

use App\Event\WhatsAppMessageStatusEvent;
use App\Service\WhatsAppNotificationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class WhatsAppMessageStatusSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private WhatsAppNotificationService $whatsAppNotificationService,
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WhatsAppMessageStatusEvent::NAME => 'onMessageStatus',
        ];
    }

    /**
     * Actualiza el estado del mensaje en la base de datos cuando Meta envía webhooks
     */
    public function onMessageStatus(WhatsAppMessageStatusEvent $event): void
    {
        $metaMessageId = $event->getMessageId();
        $statusType = $event->getStatus();

        $this->logger->info('Actualizando estado de mensaje desde webhook', [
            'meta_message_id' => $metaMessageId,
            'status' => $statusType
        ]);

        try {
            $message = $this->whatsAppNotificationService->updateMessageStatus(
                $metaMessageId,
                $statusType
            );

            if ($message) {
                $this->logger->info('Estado de mensaje actualizado correctamente', [
                    'message_id' => $message->getId(),
                    'meta_message_id' => $metaMessageId,
                    'new_status' => $statusType,
                    'recipient' => $message->getRecipient()?->getTelefono()
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error actualizando estado de mensaje', [
                'meta_message_id' => $metaMessageId,
                'status' => $statusType,
                'error' => $e->getMessage()
            ]);
        }
    }
}
