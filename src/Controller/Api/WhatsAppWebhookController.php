<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use App\Service\WhatsAppWebhookService;

#[Route('/api/whatsapp')]
class WhatsAppWebhookController extends AbstractController
{
    private LoggerInterface $logger;
    private WhatsAppWebhookService $webhookService;
    
    public function __construct(
        LoggerInterface $logger,
        WhatsAppWebhookService $webhookService
    ) {
        $this->logger = $logger;
        $this->webhookService = $webhookService;
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
        $mode = $request->query->get('hub.mode');
        $token = $request->query->get('hub.verify_token');
        $challenge = $request->query->get('hub.challenge');
        
        // Token de verificación (debe coincidir con el configurado en Meta)
        $verifyToken = $_ENV['WHATSAPP_WEBHOOK_VERIFY_TOKEN'] ?? 'sgv_monitor_2025';
        
        $this->logger->info('WhatsApp webhook verification attempt', [
            'mode' => $mode,
            'token_match' => $token === $verifyToken,
            'has_challenge' => !empty($challenge)
        ]);
        
        if ($mode === 'subscribe' && $token === $verifyToken) {
            $this->logger->info('WhatsApp webhook verified successfully');
            // Responder con el challenge para completar la verificación
            return new Response($challenge, 200);
        }
        
        $this->logger->warning('WhatsApp webhook verification failed', [
            'expected_token' => $verifyToken,
            'received_token' => $token
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
        
        try {
            $data = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON: ' . json_last_error_msg());
            }
            
            // Log estructurado del payload
            $this->logger->info('WhatsApp webhook payload parsed', [
                'object' => $data['object'] ?? null,
                'entry_count' => count($data['entry'] ?? [])
            ]);
            
            // Procesar el webhook usando el servicio
            $this->webhookService->processWebhook($data);
            
            // Responder 200 OK inmediatamente para evitar reintentos
            return new Response('OK', 200);
            
        } catch (\Exception $e) {
            $this->logger->error('Error processing WhatsApp webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Aún así responder 200 para evitar reintentos infinitos
            return new Response('OK', 200);
        }
    }
}