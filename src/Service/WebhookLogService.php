<?php

namespace App\Service;

use App\Entity\WebhookLog;
use App\Repository\WebhookLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Service for logging and tracking incoming webhooks
 */
class WebhookLogService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WebhookLogRepository $webhookLogRepository,
        private ConcessionMappingService $concessionMappingService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Logs an incoming webhook request BEFORE any processing
     */
    public function logIncoming(Request $request, string $source): WebhookLog
    {
        $webhookLog = new WebhookLog();

        $rawContent = $request->getContent();

        $webhookLog
            ->setSource($source)
            ->setEndpoint($request->getPathInfo())
            ->setMethod($request->getMethod())
            ->setRawPayload($rawContent)
            ->setIpAddress($request->getClientIp())
            ->setUserAgent($request->headers->get('User-Agent'));

        // Store relevant headers (filter out sensitive ones)
        $headers = [];
        $relevantHeaders = ['Content-Type', 'X-Hub-Signature-256', 'X-Forwarded-For', 'X-Real-IP'];
        foreach ($relevantHeaders as $header) {
            if ($request->headers->has($header)) {
                $headers[$header] = $request->headers->get($header);
            }
        }
        $webhookLog->setHeaders($headers);

        // Try to parse JSON payload
        $parsedData = json_decode($rawContent, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $webhookLog->setParsedData($parsedData);

            // Extract concession code for WhatsApp webhooks
            if ($this->isWhatsAppSource($source)) {
                $phoneNumberId = $this->extractPhoneNumberId($parsedData);
                if ($phoneNumberId) {
                    $concessionCode = $this->concessionMappingService->getConcessionByPhoneNumberId($phoneNumberId);
                    $webhookLog->setConcessionCode($concessionCode);
                }

                // Extract meta message ID if present
                $metaMessageId = $this->extractMetaMessageId($parsedData);
                if ($metaMessageId) {
                    $webhookLog->setMetaMessageId($metaMessageId);
                }
            }
        } else {
            // Mark as failed if JSON is malformed
            $webhookLog->markAsFailed('JSON parse error: ' . json_last_error_msg());
            $this->logger->warning('Webhook received with invalid JSON', [
                'source' => $source,
                'endpoint' => $request->getPathInfo(),
                'error' => json_last_error_msg()
            ]);
        }

        $this->entityManager->persist($webhookLog);
        $this->entityManager->flush();

        $this->logger->info('Webhook logged', [
            'id' => $webhookLog->getId(),
            'source' => $source,
            'status' => $webhookLog->getProcessingStatus()
        ]);

        return $webhookLog;
    }

    /**
     * Marks the webhook as currently being processed
     */
    public function markAsProcessing(WebhookLog $webhookLog): void
    {
        $webhookLog->markAsProcessing();
        $this->entityManager->flush();
    }

    /**
     * Marks the webhook as successfully completed
     */
    public function markAsCompleted(WebhookLog $webhookLog, ?array $result = null): void
    {
        $webhookLog->markAsCompleted($result);
        $this->entityManager->flush();

        $this->logger->info('Webhook completed', [
            'id' => $webhookLog->getId(),
            'source' => $webhookLog->getSource()
        ]);
    }

    /**
     * Marks the webhook as failed
     */
    public function markAsFailed(WebhookLog $webhookLog, string $error, ?array $result = null): void
    {
        $webhookLog->markAsFailed($error, $result);
        $this->entityManager->flush();

        $this->logger->error('Webhook failed', [
            'id' => $webhookLog->getId(),
            'source' => $webhookLog->getSource(),
            'error' => $error
        ]);
    }

    /**
     * Correlates the webhook with a WhatsApp message via Meta Message ID
     */
    public function correlateWithMessage(WebhookLog $webhookLog, string $metaMessageId): void
    {
        $webhookLog->setMetaMessageId($metaMessageId);
        $this->entityManager->flush();
    }

    /**
     * Sets the related entity for the webhook
     */
    public function setRelatedEntity(WebhookLog $webhookLog, string $type, int $id): void
    {
        $webhookLog->setRelatedEntity($type, $id);
        $this->entityManager->flush();
    }

    /**
     * Detects the specific WhatsApp webhook type from parsed data
     */
    public function detectWhatsAppWebhookType(array $parsedData): string
    {
        foreach ($parsedData['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                if (!empty($value['statuses'])) {
                    // Check if any status is an error
                    foreach ($value['statuses'] as $status) {
                        if (($status['status'] ?? '') === 'failed') {
                            return WebhookLog::SOURCE_WHATSAPP_ERROR;
                        }
                    }
                    return WebhookLog::SOURCE_WHATSAPP_STATUS;
                }

                if (!empty($value['messages'])) {
                    return WebhookLog::SOURCE_WHATSAPP_MESSAGE;
                }

                if (!empty($value['errors'])) {
                    return WebhookLog::SOURCE_WHATSAPP_ERROR;
                }
            }
        }

        return WebhookLog::SOURCE_UNKNOWN;
    }

    /**
     * Extracts phone_number_id from WhatsApp webhook payload
     */
    private function extractPhoneNumberId(array $parsedData): ?string
    {
        foreach ($parsedData['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $metadata = $change['value']['metadata'] ?? [];
                if (!empty($metadata['phone_number_id'])) {
                    return $metadata['phone_number_id'];
                }
            }
        }
        return null;
    }

    /**
     * Extracts Meta Message ID from WhatsApp webhook payload
     */
    private function extractMetaMessageId(array $parsedData): ?string
    {
        foreach ($parsedData['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                // From status updates
                foreach ($value['statuses'] ?? [] as $status) {
                    if (!empty($status['id'])) {
                        return $status['id'];
                    }
                }

                // From incoming messages
                foreach ($value['messages'] ?? [] as $message) {
                    if (!empty($message['id'])) {
                        return $message['id'];
                    }
                }
            }
        }
        return null;
    }

    /**
     * Checks if the source is a WhatsApp webhook type
     */
    private function isWhatsAppSource(string $source): bool
    {
        return in_array($source, [
            WebhookLog::SOURCE_WHATSAPP_STATUS,
            WebhookLog::SOURCE_WHATSAPP_MESSAGE,
            WebhookLog::SOURCE_WHATSAPP_ERROR,
        ], true);
    }
}
