<?php

namespace App\Notifier\WhatsApp;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Meta WhatsApp Business API Transport for Symfony Notifier
 * Uses Facebook Graph API v22.0 to send WhatsApp template messages
 * 
 * Based on the existing implementation in prometheusWebhookAction
 */
final class MetaWhatsAppTransport extends AbstractTransport
{
    protected const HOST = 'graph.facebook.com';
    protected const API_VERSION = 'v22.0';
    
    private string $accessToken;
    private string $phoneNumberId;
    private ?string $defaultTemplate;
    private LoggerInterface $logger;
    
    public function __construct(
        string $accessToken,
        string $phoneNumberId,
        ?string $defaultTemplate = null,
        HttpClientInterface $client = null,
        EventDispatcherInterface $dispatcher = null,
        LoggerInterface $logger = null
    ) {
        $this->accessToken = $accessToken;
        $this->phoneNumberId = $phoneNumberId;
        $this->defaultTemplate = $defaultTemplate;
        $this->logger = $logger ?? new NullLogger();
        
        parent::__construct($client, $dispatcher);
    }
    
    public function __toString(): string
    {
        return sprintf('meta-whatsapp://%s@%s', 
            substr($this->accessToken, 0, 10) . '...', 
            $this->getEndpoint()
        );
    }
    
    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage && 
               ($message->getOptions() === null || $message->getOptions() instanceof MetaWhatsAppOptions);
    }
    
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }
        
        $options = $message->getOptions();
        if ($options && !$options instanceof MetaWhatsAppOptions) {
            throw new UnsupportedMessageTypeException(__CLASS__, MetaWhatsAppOptions::class, $options);
        }
        
        $options = $options ?? new MetaWhatsAppOptions();
        
        // Obtener el número de teléfono del destinatario
        $recipient = $options->getRecipientId() ?? $message->getRecipientId();
        
        if (!$recipient) {
            throw new TransportException('Missing recipient phone number');
        }
        
        // Meta WhatsApp API requiere números sin el prefijo '+'
        // Ejemplo: 56972126016 en lugar de +56972126016
        $recipient = ltrim($recipient, '+');
        
        // Construir la URL de la API
        $endpoint = sprintf('https://%s/%s/%s/messages', 
            self::HOST, 
            self::API_VERSION, 
            $this->phoneNumberId
        );
        
        // Construir el payload
        $payload = $this->buildPayload($recipient, $message, $options);
        
        // Log del intento de envío
        $this->logger->info('Sending WhatsApp message', [
            'to' => $recipient,
            'template' => $payload['template']['name'] ?? 'text_message'
        ]);
        
        // Realizar la petición HTTP
        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);
        
        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Could not reach WhatsApp server', [
                'error' => $e->getMessage()
            ]);
            throw new TransportException('Could not reach WhatsApp server: ' . $e->getMessage(), $response, 0, $e);
        }
        
        $result = $response->toArray(false);
        
        // Log completo de la respuesta para debug
        $this->logger->info('WhatsApp API Response', [
            'status_code' => $statusCode,
            'response' => $result,
            'request_payload' => $payload
        ]);
        
        if (200 !== $statusCode && 201 !== $statusCode) {
            $errorMessage = $result['error']['message'] ?? 'Unknown error';
            $errorType = $result['error']['type'] ?? 'unknown';
            $errorCode = $result['error']['code'] ?? 0;
            
            $this->logger->error('WhatsApp API error', [
                'status' => $statusCode,
                'error_type' => $errorType,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'response' => $result,
                'request_payload' => $payload
            ]);
            
            throw new TransportException(
                sprintf('Unable to send WhatsApp message: [%s] %s', $errorType, $errorMessage),
                $response
            );
        }
        
        // Verificar que el mensaje fue enviado correctamente
        if (!isset($result['messages'][0]['id'])) {
            $this->logger->warning('Unexpected response structure from WhatsApp API', [
                'response' => $result,
                'expected' => 'messages[0][id]'
            ]);
            // Si no hay error pero tampoco el formato esperado, podría ser exitoso
            if (isset($result['success']) && $result['success'] === true) {
                $messageId = 'success-' . uniqid();
            } else {
                throw new TransportException('Invalid response from WhatsApp API', $response);
            }
        } else {
            $messageId = $result['messages'][0]['id'];
        }
        
        $this->logger->info('WhatsApp message sent successfully', [
            'message_id' => $messageId,
            'to' => $recipient
        ]);
        
        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($messageId);
        
        return $sentMessage;
    }
    
    private function buildPayload(string $recipient, ChatMessage $message, MetaWhatsAppOptions $options): array
    {
        // El recipient ya llega limpio sin '+' desde doSend()
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $recipient,
        ];
        
        // Determinar si enviar como template o mensaje de texto
        $templateName = $options->getTemplate() ?? $this->defaultTemplate;
        
        if ($templateName) {
            // Envío de mensaje con template
            $payload['type'] = 'template';
            $payload['template'] = [
                'name' => $templateName,
                'language' => [
                    'code' => $options->getLanguageCode() ?? 'es'
                ],
            ];
            
            // Añadir parámetros si existen
            $parameters = $options->getTemplateParameters();
            
            // Si no hay parámetros específicos, intentar extraerlos del mensaje
            if (empty($parameters) && $message->getSubject()) {
                // Usar el subject como parámetros (separados por |)
                $parameters = explode('|', $message->getSubject());
            }
            
            if (!empty($parameters)) {
                $bodyParameters = [];
                foreach ($parameters as $param) {
                    $bodyParameters[] = [
                        'type' => 'text',
                        'text' => (string) $param
                    ];
                }
                
                $payload['template']['components'] = [
                    [
                        'type' => 'body',
                        'parameters' => $bodyParameters
                    ]
                ];
            }
        } else {
            // Envío de mensaje de texto simple
            $payload['type'] = 'text';
            $payload['text'] = [
                'body' => $message->getSubject() ?: 'Notification',
                'preview_url' => false
            ];
        }
        
        return $payload;
    }
}