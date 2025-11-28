<?php

namespace App\Controller\Api;

use App\Entity\WebhookLog;
use App\Repository\WhatsApp\RecipientGroupRepository;
use App\Repository\WhatsApp\TemplateRepository;
use App\Service\WebhookLogService;
use App\Service\WhatsAppNotificationService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/prometheus', name: 'api_prometheus_')]
class PrometheusController extends AbstractController
{
    public function __construct(
        private WhatsAppNotificationService $whatsAppService,
        private TemplateRepository $templateRepository,
        private RecipientGroupRepository $groupRepository,
        private WebhookLogService $webhookLogService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Recibe webhooks de Prometheus y envía alertas WhatsApp para alertas críticas
     */
    #[Route('/webhook', name: 'webhook', methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
    {
        $this->logger->info('Webhook de Prometheus recibido');

        // PASO 1: Registrar webhook ANTES de cualquier validación (store-then-process)
        $webhookLog = $this->webhookLogService->logIncoming($request, WebhookLog::SOURCE_ALERTMANAGER);

        // Si el webhook ya fue marcado como fallido (JSON inválido), retornar error
        if ($webhookLog->getProcessingStatus() === WebhookLog::STATUS_FAILED) {
            return $this->json([
                'status' => 'error',
                'message' => 'Invalid JSON payload',
                'webhook_id' => $webhookLog->getId()
            ], 400);
        }

        // Marcar como en procesamiento
        $this->webhookLogService->markAsProcessing($webhookLog);

        $payload = $webhookLog->getParsedData();

        // Verificar que el payload contiene alertas
        if (!isset($payload['alerts']) || !is_array($payload['alerts'])) {
            $this->logger->error('Payload no contiene array de alertas');
            $this->webhookLogService->markAsFailed($webhookLog, 'Payload must contain alerts array');
            return $this->json([
                'status' => 'error',
                'message' => 'Payload must contain alerts array',
                'webhook_id' => $webhookLog->getId()
            ], 400);
        }

        // Buscar template y grupo
        $template = $this->templateRepository->findOneBy(['metaTemplateId' => 'prometheus_alert_firing']);
        if (!$template) {
            $this->logger->error('Template prometheus_alert_firing no encontrado');
            $this->webhookLogService->markAsFailed($webhookLog, 'WhatsApp template not configured');
            return $this->json([
                'status' => 'error',
                'message' => 'WhatsApp template not configured',
                'webhook_id' => $webhookLog->getId()
            ], 500);
        }

        $group = $this->groupRepository->findOneBy(['slug' => 'prometheus_alerts']);
        if (!$group) {
            $this->logger->error('Grupo prometheus_alerts no encontrado');
            $this->webhookLogService->markAsFailed($webhookLog, 'Recipient group not configured');
            return $this->json([
                'status' => 'error',
                'message' => 'Recipient group not configured',
                'webhook_id' => $webhookLog->getId()
            ], 500);
        }

        // Procesar cada alerta del payload
        $processedCount = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($payload['alerts'] as $alert) {
            // Filtrar solo alertas firing
            $status = $alert['status'] ?? null;
            if ($status !== 'firing') {
                $alertName = $alert['labels']['alertname'] ?? 'unknown';
                $this->logger->info("Alerta '{$alertName}' ignorada (estado: {$status})");
                $skippedCount++;
                continue;
            }

            // Filtrar solo alertas críticas
            $severity = $alert['labels']['severity'] ?? null;
            if ($severity !== 'critical') {
                $alertName = $alert['labels']['alertname'] ?? 'unknown';
                $this->logger->info("Alerta '{$alertName}' ignorada (severidad: {$severity}, requiere critical)");
                $skippedCount++;
                continue;
            }

            // Extraer parámetros para el template
            $parameters = [
                $alert['labels']['alertname'] ?? 'Nombre de Alerta no disponible',
                $alert['labels']['severity'] ?? 'Severidad no especificada',
                $alert['annotations']['summary'] ?? 'Sin resumen',
                $alert['labels']['instance'] ?? $alert['labels']['job'] ?? 'Dispositivo no especificado'
            ];

            try {
                // Enviar alerta WhatsApp
                $alertName = $parameters[0];
                $messages = $this->whatsAppService->sendTemplateMessage(
                    $template,
                    $parameters,
                    $group,
                    "prometheus_alert:{$alertName}"
                );

                $this->logger->info(
                    "Alerta '{$alertName}' procesada exitosamente",
                    ['messages_sent' => count($messages)]
                );
                $processedCount++;
            } catch (\Exception $e) {
                $this->logger->error(
                    "Error procesando alerta: {$e->getMessage()}",
                    ['alert' => $alert]
                );
                $errors[] = $e->getMessage();
            }
        }

        $message = sprintf(
            'Webhooks procesados: %d alertas enviadas, %d alertas omitidas',
            $processedCount,
            $skippedCount
        );

        $this->logger->info($message);

        // Marcar webhook como completado con resultado
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
                sprintf('%d errores durante procesamiento', count($errors)),
                $result
            );
        }

        return $this->json([
            'status' => 'ok',
            'message' => $message,
            'processed' => $processedCount,
            'skipped' => $skippedCount,
            'webhook_id' => $webhookLog->getId()
        ]);
    }
}
