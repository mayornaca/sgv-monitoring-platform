<?php

namespace App\Controller\Api;

use App\Entity\WebhookLog;
use App\Service\WebhookLogService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/prometheus', name: 'api_prometheus_')]
class PrometheusController extends AbstractController
{
    public function __construct(
        private WebhookLogService $webhookLogService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Recibe webhooks de Prometheus/Alertmanager y los encola para procesamiento async
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

        // Validación básica antes de encolar
        $payload = $webhookLog->getParsedData();
        if (!isset($payload['alerts']) || !is_array($payload['alerts'])) {
            $this->logger->error('Payload no contiene array de alertas');
            $this->webhookLogService->markAsFailed($webhookLog, 'Payload must contain alerts array');
            return $this->json([
                'status' => 'error',
                'message' => 'Payload must contain alerts array',
                'webhook_id' => $webhookLog->getId()
            ], 400);
        }

        // PASO 2: Dispatch para procesamiento async
        $this->webhookLogService->dispatchForProcessing($webhookLog);

        // Responder inmediatamente - el procesamiento ocurre async
        return $this->json([
            'status' => 'queued',
            'message' => 'Webhook received and queued for processing',
            'webhook_id' => $webhookLog->getId(),
            'alerts_count' => count($payload['alerts'])
        ]);
    }
}
