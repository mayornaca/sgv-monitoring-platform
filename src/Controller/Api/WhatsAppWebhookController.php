<?php

namespace App\Controller\Api;

use App\Entity\WebhookLog;
use App\Service\ConfigurationService;
use App\Service\WebhookLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use App\Service\WhatsAppWebhookService;
use App\Service\WhatsAppService;

#[Route('/api/whatsapp')]
class WhatsAppWebhookController extends AbstractController
{
    private LoggerInterface $logger;
    private WhatsAppWebhookService $webhookService;
    private WhatsAppService $whatsappService;
    private WebhookLogService $webhookLogService;
    private string $verifyToken;

    public function __construct(
        LoggerInterface $logger,
        WhatsAppWebhookService $webhookService,
        WhatsAppService $whatsappService,
        WebhookLogService $webhookLogService,
        ConfigurationService $configService
    ) {
        $this->logger = $logger;
        $this->webhookService = $webhookService;
        $this->whatsappService = $whatsappService;
        $this->webhookLogService = $webhookLogService;
        $this->verifyToken = $configService->get('whatsapp.webhook_verify_token');
    }
    
    #[Route('/webhook', name: 'whatsapp_webhook', methods: ['GET', 'POST'])]
    public function webhook(Request $request): Response
    {
        // GET request = Verificación del webhook
        if ($request->isMethod('GET')) {
            return $this->handleVerification($request);
        }
        
        // POST request = Notificación de evento
        if ($request->isMethod('POST')) {
            return $this->handleNotification($request);
        }
        
        return new Response('Method not allowed', 405);
    }
    
    /**
     * Maneja la verificación del webhook de Meta
     */
    private function handleVerification(Request $request): Response
    {
        // PHP convierte hub.mode a hub_mode automáticamente en query params
        $mode = $request->query->get('hub_mode');
        $token = $request->query->get('hub_verify_token');
        $challenge = $request->query->get('hub_challenge');

        // Logging seguro sin exponer tokens completos
        $this->logger->info('WhatsApp webhook verification attempt', [
            'mode' => $mode,
            'token_match' => $token === $this->verifyToken,
            'has_challenge' => !empty($challenge),
            'expected_token_length' => strlen($this->verifyToken ?? ''),
            'received_token_length' => strlen($token ?? ''),
            'expected_token_hash' => $this->verifyToken ? substr(md5($this->verifyToken), 0, 8) : 'null',
            'received_token_hash' => $token ? substr(md5($token), 0, 8) : 'null',
            'verifyToken_is_null' => $this->verifyToken === null,
            'verifyToken_is_empty' => empty($this->verifyToken)
        ]);

        if ($mode === 'subscribe' && $token === $this->verifyToken) {
            $this->logger->info('WhatsApp webhook verified successfully');
            // Responder con el challenge para completar la verificación
            return new Response($challenge, 200);
        }

        $this->logger->warning('WhatsApp webhook verification failed', [
            'reason' => 'Token mismatch or invalid mode',
            'mode_valid' => $mode === 'subscribe',
            'tokens_match' => $token === $this->verifyToken
        ]);

        return new Response('Forbidden', 403);
    }
    
    /**
     * Procesa las notificaciones del webhook
     */
    private function handleNotification(Request $request): Response
    {
        $content = $request->getContent();

        // Log del payload completo para debugging
        $this->logger->info('WhatsApp webhook notification received', [
            'payload_size' => strlen($content),
            'headers' => $request->headers->all()
        ]);

        // Detectar tipo de webhook primero para el logging
        $parsedData = json_decode($content, true);
        $webhookType = WebhookLog::SOURCE_UNKNOWN;
        if (json_last_error() === JSON_ERROR_NONE && is_array($parsedData)) {
            $webhookType = $this->webhookLogService->detectWhatsAppWebhookType($parsedData);
        }

        // PASO 1: Registrar webhook ANTES de cualquier validación (store-then-process)
        $webhookLog = $this->webhookLogService->logIncoming($request, $webhookType);

        // Si el webhook ya fue marcado como fallido (JSON inválido), retornar OK
        // (Meta espera 200 OK incluso en errores para evitar reintentos)
        if ($webhookLog->getProcessingStatus() === WebhookLog::STATUS_FAILED) {
            return new Response('OK', 200);
        }

        // Marcar como en procesamiento
        $this->webhookLogService->markAsProcessing($webhookLog);

        try {
            $data = $webhookLog->getParsedData();

            // Log estructurado del payload
            $this->logger->info('WhatsApp webhook payload parsed', [
                'object' => $data['object'] ?? null,
                'entry_count' => count($data['entry'] ?? []),
                'webhook_id' => $webhookLog->getId(),
                'webhook_type' => $webhookType,
                'concession' => $webhookLog->getConcessionCode()
            ]);

            // Procesar el webhook usando el servicio
            $this->webhookService->processWebhook($data);

            // Marcar como completado
            $this->webhookLogService->markAsCompleted($webhookLog, [
                'object' => $data['object'] ?? null,
                'entry_count' => count($data['entry'] ?? []),
                'type' => $webhookType
            ]);

            // Responder 200 OK inmediatamente para evitar reintentos
            return new Response('OK', 200);

        } catch (\Exception $e) {
            $this->logger->error('Error processing WhatsApp webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'webhook_id' => $webhookLog->getId()
            ]);

            // Marcar como fallido
            $this->webhookLogService->markAsFailed($webhookLog, $e->getMessage());

            // Aún así responder 200 para evitar reintentos infinitos
            return new Response('OK', 200);
        }
    }

    /**
     * Endpoint para enviar mensajes de WhatsApp
     * Compatible con formato legacy: GET/POST/PUT con parámetros 'to' y 'message'
     */
    #[Route('/send', name: 'send', methods: ['GET', 'POST', 'PUT'])]
    public function send(Request $request): JsonResponse
    {
        set_time_limit(60);

        // Obtener parámetros según el método HTTP
        if ($request->isMethod('POST') || $request->isMethod('PUT')) {
            $params = $request->request->all();

            // Si no hay parámetros POST normales, intentar obtener JSON del body
            if (empty($params)) {
                $content = $request->getContent();
                if (!empty($content)) {
                    $jsonParams = json_decode($content, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $params = $jsonParams;
                    } else {
                        // Si no es JSON válido, asumir que es query string en el body
                        $params = ['message' => $content];
                    }
                }
            }

            // Combinar con query parameters para máxima compatibilidad
            $params = array_merge($request->query->all(), $params);
        } else {
            // GET request
            $params = $request->query->all();
        }

        $to = $params['to'] ?? null;
        $message = $params['message'] ?? null;

        // Validar parámetros requeridos
        if (empty($to) || empty($message)) {
            $this->logger->warning('WhatsApp send request missing parameters', [
                'params' => $params,
                'method' => $request->getMethod()
            ]);

            return new JsonResponse([
                'result' => false,
                'error' => 'Missing required parameters: to, message'
            ], 400);
        }

        // Log de la solicitud
        $this->logger->info('WhatsApp send request', [
            'to' => $to,
            'message_length' => strlen($message),
            'method' => $request->getMethod()
        ]);

        // Enviar mensaje usando el servicio
        try {
            $success = $this->whatsappService->sendMessage($to, $message);

            return new JsonResponse([
                'result' => $success
            ], 200);

        } catch (\Exception $e) {
            $this->logger->error('WhatsApp send error', [
                'error' => $e->getMessage(),
                'to' => $to
            ]);

            return new JsonResponse([
                'result' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}