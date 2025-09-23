<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use App\Event\WhatsAppMessageStatusEvent;
use App\Event\WhatsAppMessageReceivedEvent;

class WhatsAppWebhookService
{
    private LoggerInterface $logger;
    private EventDispatcherInterface $eventDispatcher;
    
    public function __construct(
        LoggerInterface $logger,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
    }
    
    /**
     * Procesa el payload del webhook
     */
    public function processWebhook(array $data): void
    {
        if (!isset($data['entry']) || !is_array($data['entry'])) {
            $this->logger->warning('Invalid webhook structure: missing entry');
            return;
        }
        
        foreach ($data['entry'] as $entry) {
            $this->processEntry($entry);
        }
    }
    
    /**
     * Procesa una entrada del webhook
     */
    private function processEntry(array $entry): void
    {
        if (!isset($entry['changes']) || !is_array($entry['changes'])) {
            return;
        }
        
        foreach ($entry['changes'] as $change) {
            if (!isset($change['value'])) {
                continue;
            }
            
            $value = $change['value'];
            
            // Procesar diferentes tipos de eventos
            if (isset($value['statuses'])) {
                $this->processStatuses($value['statuses'], $value['metadata'] ?? []);
            }
            
            if (isset($value['messages'])) {
                $this->processMessages($value['messages'], $value['metadata'] ?? []);
            }
            
            if (isset($value['errors'])) {
                $this->processErrors($value['errors'], $value['metadata'] ?? []);
            }
        }
    }
    
    /**
     * Procesa actualizaciones de estado de mensajes enviados
     */
    private function processStatuses(array $statuses, array $metadata): void
    {
        foreach ($statuses as $status) {
            $messageId = $status['id'] ?? 'unknown';
            $statusType = $status['status'] ?? 'unknown';
            $timestamp = $status['timestamp'] ?? time();
            $recipientId = $status['recipient_id'] ?? 'unknown';
            
            // Log detallado del estado del mensaje
            $logContext = [
                'message_id' => $messageId,
                'status' => $statusType,
                'recipient' => $recipientId,
                'timestamp' => date('Y-m-d H:i:s', $timestamp),
                'display_phone' => $metadata['display_phone_number'] ?? null
            ];
            
            // Agregar información de error si existe
            if (isset($status['errors']) && is_array($status['errors'])) {
                $logContext['errors'] = $status['errors'];
            }
            
            switch ($statusType) {
                case 'sent':
                    $this->logger->info('📤 WhatsApp message SENT', $logContext);
                    break;
                    
                case 'delivered':
                    $this->logger->info('✅ WhatsApp message DELIVERED', $logContext);
                    break;
                    
                case 'read':
                    $this->logger->info('👁️ WhatsApp message READ', $logContext);
                    break;
                    
                case 'failed':
                    $this->logger->error('❌ WhatsApp message FAILED', $logContext);
                    
                    // Log detallado de errores
                    if (isset($status['errors'])) {
                        foreach ($status['errors'] as $error) {
                            $this->logger->error('WhatsApp error details', [
                                'code' => $error['code'] ?? 'unknown',
                                'title' => $error['title'] ?? 'unknown',
                                'message' => $error['message'] ?? 'unknown',
                                'details' => $error['error_data']['details'] ?? null
                            ]);
                        }
                    }
                    break;
                    
                default:
                    $this->logger->warning('Unknown WhatsApp status', $logContext);
            }
            
            // Disparar evento para que otros componentes puedan reaccionar
            $event = new WhatsAppMessageStatusEvent(
                $messageId,
                $statusType,
                $recipientId,
                $timestamp,
                $status['errors'] ?? []
            );
            
            $this->eventDispatcher->dispatch($event, WhatsAppMessageStatusEvent::NAME);
        }
    }
    
    /**
     * Procesa mensajes entrantes
     */
    private function processMessages(array $messages, array $metadata): void
    {
        foreach ($messages as $message) {
            $messageId = $message['id'] ?? 'unknown';
            $from = $message['from'] ?? 'unknown';
            $timestamp = $message['timestamp'] ?? time();
            $type = $message['type'] ?? 'unknown';
            
            $logContext = [
                'message_id' => $messageId,
                'from' => $from,
                'type' => $type,
                'timestamp' => date('Y-m-d H:i:s', $timestamp)
            ];
            
            // Extraer el contenido según el tipo de mensaje
            $content = '';
            switch ($type) {
                case 'text':
                    $content = $message['text']['body'] ?? '';
                    $logContext['text'] = $content;
                    break;
                    
                case 'image':
                case 'document':
                case 'audio':
                case 'video':
                    $content = $message[$type]['caption'] ?? '';
                    $logContext['media_id'] = $message[$type]['id'] ?? null;
                    $logContext['caption'] = $content;
                    break;
                    
                case 'reaction':
                    $logContext['emoji'] = $message['reaction']['emoji'] ?? '';
                    $logContext['message_id_reacted'] = $message['reaction']['message_id'] ?? '';
                    break;
            }
            
            $this->logger->info('📨 WhatsApp message RECEIVED', $logContext);
            
            // Disparar evento para procesar el mensaje entrante
            $event = new WhatsAppMessageReceivedEvent(
                $messageId,
                $from,
                $type,
                $content,
                $timestamp,
                $message
            );
            
            $this->eventDispatcher->dispatch($event, WhatsAppMessageReceivedEvent::NAME);
        }
    }
    
    /**
     * Procesa errores fuera de banda
     */
    private function processErrors(array $errors, array $metadata): void
    {
        foreach ($errors as $error) {
            $this->logger->error('WhatsApp out-of-band error', [
                'code' => $error['code'] ?? 'unknown',
                'title' => $error['title'] ?? 'unknown',
                'message' => $error['message'] ?? 'unknown',
                'details' => $error['error_data'] ?? [],
                'phone_number' => $metadata['display_phone_number'] ?? null
            ]);
        }
    }
}