<?php

namespace App\MessageHandler;

use App\Entity\WebhookLog;
use App\Message\ProcessWebhookMessage;
use App\Repository\WebhookLogRepository;
use App\Repository\WhatsApp\RecipientGroupRepository;
use App\Repository\WhatsApp\TemplateRepository;
use App\Service\WebhookLogService;
use App\Service\WhatsAppNotificationService;
use App\Service\WhatsAppWebhookService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessWebhookMessageHandler
{
    public function __construct(
        private WebhookLogRepository $webhookLogRepository,
        private WebhookLogService $webhookLogService,
        private WhatsAppNotificationService $whatsAppNotificationService,
        private WhatsAppWebhookService $whatsAppWebhookService,
        private TemplateRepository $templateRepository,
        private RecipientGroupRepository $groupRepository,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(ProcessWebhookMessage $message): void
    {
        $webhookLog = $this->webhookLogRepository->find($message->getWebhookLogId());

        if (!$webhookLog) {
            $this->logger->error('WebhookLog not found', [
                'webhook_log_id' => $message->getWebhookLogId()
            ]);
            return;
        }

        // Mark as processing
        $this->webhookLogService->markAsProcessing($webhookLog);

        try {
            // Route to appropriate processor based on source
            match ($webhookLog->getSource()) {
                WebhookLog::SOURCE_ALERTMANAGER,
                WebhookLog::SOURCE_PROMETHEUS,
                WebhookLog::SOURCE_GRAFANA => $this->processAlertWebhook($webhookLog),

                WebhookLog::SOURCE_WHATSAPP_STATUS,
                WebhookLog::SOURCE_WHATSAPP_MESSAGE,
                WebhookLog::SOURCE_WHATSAPP_ERROR => $this->processWhatsAppWebhook($webhookLog),

                default => $this->processUnknownWebhook($webhookLog)
            };
        } catch (\Throwable $e) {
            $this->logger->error('Webhook processing failed', [
                'webhook_log_id' => $webhookLog->getId(),
                'source' => $webhookLog->getSource(),
                'error' => $e->getMessage()
            ]);

            $this->webhookLogService->markAsFailed($webhookLog, $e->getMessage());
            throw $e; // Re-throw for Messenger retry handling
        }
    }

    /**
     * Process Alertmanager/Prometheus/Grafana webhooks
     */
    private function processAlertWebhook(WebhookLog $webhookLog): void
    {
        $payload = $webhookLog->getParsedData();

        if (!isset($payload['alerts']) || !is_array($payload['alerts'])) {
            $this->webhookLogService->markAsFailed($webhookLog, 'Payload must contain alerts array');
            return;
        }

        // Find template and group
        $template = $this->templateRepository->findOneBy(['metaTemplateId' => 'prometheus_alert_firing']);
        if (!$template) {
            $this->webhookLogService->markAsFailed($webhookLog, 'WhatsApp template not configured');
            return;
        }

        $group = $this->groupRepository->findOneBy(['slug' => 'prometheus_alerts']);
        if (!$group) {
            $this->webhookLogService->markAsFailed($webhookLog, 'Recipient group not configured');
            return;
        }

        $processedCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($payload['alerts'] as $alert) {
            // Filter only firing alerts
            $status = $alert['status'] ?? null;
            if ($status !== 'firing') {
                $skippedCount++;
                continue;
            }

            // Filter only critical alerts
            $severity = $alert['labels']['severity'] ?? null;
            if ($severity !== 'critical') {
                $skippedCount++;
                continue;
            }

            $parameters = [
                $alert['labels']['alertname'] ?? 'Nombre de Alerta no disponible',
                $alert['labels']['severity'] ?? 'Severidad no especificada',
                $alert['annotations']['summary'] ?? 'Sin resumen',
                $alert['labels']['instance'] ?? $alert['labels']['job'] ?? 'Dispositivo no especificado'
            ];

            try {
                $alertName = $parameters[0];
                $this->whatsAppNotificationService->sendTemplateMessage(
                    $template,
                    $parameters,
                    $group,
                    "prometheus_alert:{$alertName}"
                );
                $processedCount++;
            } catch (\Exception $e) {
                $this->logger->error("Error sending alert: {$e->getMessage()}");
                $errors[] = $e->getMessage();
            }
        }

        $result = [
            'processed' => $processedCount,
            'skipped' => $skippedCount,
            'total_alerts' => count($payload['alerts']),
            'errors' => $errors
        ];

        if (empty($errors)) {
            $this->webhookLogService->markAsCompleted($webhookLog, $result);
        } else {
            $this->webhookLogService->markAsFailed(
                $webhookLog,
                sprintf('%d errors during processing', count($errors)),
                $result
            );
        }

        $this->logger->info('Alert webhook processed', [
            'webhook_log_id' => $webhookLog->getId(),
            'processed' => $processedCount,
            'skipped' => $skippedCount
        ]);
    }

    /**
     * Process WhatsApp status/message webhooks
     */
    private function processWhatsAppWebhook(WebhookLog $webhookLog): void
    {
        $payload = $webhookLog->getParsedData();

        if (!$payload) {
            $this->webhookLogService->markAsFailed($webhookLog, 'Invalid payload');
            return;
        }

        try {
            // Use existing WhatsApp webhook service
            $this->whatsAppWebhookService->processWebhook($payload);

            $this->webhookLogService->markAsCompleted($webhookLog, [
                'object' => $payload['object'] ?? null,
                'entry_count' => count($payload['entry'] ?? []),
                'type' => $webhookLog->getSource()
            ]);

            $this->logger->info('WhatsApp webhook processed', [
                'webhook_log_id' => $webhookLog->getId(),
                'source' => $webhookLog->getSource()
            ]);
        } catch (\Exception $e) {
            $this->webhookLogService->markAsFailed($webhookLog, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle unknown webhook sources
     */
    private function processUnknownWebhook(WebhookLog $webhookLog): void
    {
        $this->logger->warning('Unknown webhook source', [
            'webhook_log_id' => $webhookLog->getId(),
            'source' => $webhookLog->getSource()
        ]);

        $this->webhookLogService->markAsCompleted($webhookLog, [
            'status' => 'skipped',
            'reason' => 'Unknown source type'
        ]);
    }
}
