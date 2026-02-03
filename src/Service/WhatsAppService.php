<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WhatsAppService
{
    private array $authTokens = [
        '0a2c158a-6773-41fe-b903-3167d07b61e8',
        // Add more tokens if needed for fallback
    ];

    private int $maxRetries = 3;
    private int $retryDelay = 2;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger
    ) {}

    public function sendMessage(string $to, string $message): bool
    {
        $this->logger->info('Sending WhatsApp message', [
            'to' => $to,
            'message_length' => strlen($message)
        ]);

        foreach ($this->authTokens as $attempt => $token) {
            try {
                $this->logger->info('WhatsApp attempt', ['attempt' => $attempt + 1]);

                $response = $this->httpClient->request('POST', 'https://gate.whapi.cloud/messages/text', [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'to' => $to,
                        'body' => $message,
                        'typing_time' => 0,
                        'no_link_preview' => false
                    ],
                    'timeout' => 30
                ]);

                $statusCode = $response->getStatusCode();
                $content = $response->getContent();

                $this->logger->info('WhatsApp API response', [
                    'status_code' => $statusCode,
                    'response' => $content
                ]);

                if ($statusCode === 200) {
                    $jsonData = json_decode($content, true);
                    
                    if (isset($jsonData['messages'][0]['id'])) {
                        $this->logger->info('WhatsApp message sent successfully', [
                            'message_id' => $jsonData['messages'][0]['id'],
                            'to' => $to
                        ]);
                        return true;
                    }
                }

                $this->logger->warning('WhatsApp API failed', [
                    'attempt' => $attempt + 1,
                    'status_code' => $statusCode,
                    'response' => $content
                ]);

            } catch (\Exception $e) {
                $this->logger->error('WhatsApp API error', [
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                    'to' => $to
                ]);

                if ($attempt < count($this->authTokens) - 1) {
                    sleep($this->retryDelay);
                }
            }
        }

        $this->logger->error('Failed to send WhatsApp message after all attempts', ['to' => $to]);
        return false;
    }

    public function sendTemplateMessage(
        string $to, 
        string $templateName, 
        array $bodyParameters, 
        string $langCode = 'es'
    ): bool {
        $this->logger->info('Sending WhatsApp template message', [
            'to' => $to,
            'template' => $templateName,
            'parameters' => $bodyParameters
        ]);

        // Build template parameters
        $parameters = [];
        foreach ($bodyParameters as $param) {
            $parameters[] = [
                "type" => "text",
                "text" => (string) $param
            ];
        }

        foreach ($this->authTokens as $attempt => $token) {
            try {
                $this->logger->info('WhatsApp template attempt', ['attempt' => $attempt + 1]);

                $response = $this->httpClient->request('POST', 'https://gate.whapi.cloud/messages/template', [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'to' => $to,
                        'template' => [
                            'name' => $templateName,
                            'language' => [
                                'code' => $langCode
                            ],
                            'components' => [
                                [
                                    'type' => 'body',
                                    'parameters' => $parameters
                                ]
                            ]
                        ]
                    ],
                    'timeout' => 30
                ]);

                $statusCode = $response->getStatusCode();
                $content = $response->getContent();

                $this->logger->info('WhatsApp template API response', [
                    'status_code' => $statusCode,
                    'response' => $content
                ]);

                if ($statusCode === 200) {
                    $jsonData = json_decode($content, true);
                    
                    if (isset($jsonData['messages'][0]['id'])) {
                        $this->logger->info('WhatsApp template message sent successfully', [
                            'message_id' => $jsonData['messages'][0]['id'],
                            'to' => $to,
                            'template' => $templateName
                        ]);
                        return true;
                    }
                }

                $this->logger->warning('WhatsApp template API failed', [
                    'attempt' => $attempt + 1,
                    'status_code' => $statusCode,
                    'response' => $content
                ]);

            } catch (\Exception $e) {
                $this->logger->error('WhatsApp template API error', [
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                    'to' => $to,
                    'template' => $templateName
                ]);

                if ($attempt < count($this->authTokens) - 1) {
                    sleep($this->retryDelay);
                }
            }
        }

        $this->logger->error('Failed to send WhatsApp template message after all attempts', [
            'to' => $to, 
            'template' => $templateName
        ]);
        return false;
    }

    public function validatePhoneNumber(string $phone): bool
    {
        // Basic validation for Chilean phone numbers
        // Should start with 569 and have 8 more digits
        return preg_match('/^569\d{8}$/', $phone);
    }

    public function formatPhoneNumber(string $phone): string
    {
        // Remove any non-digit characters
        $phone = preg_replace('/\D/', '', $phone);
        
        // If it starts with +56, remove the +
        if (str_starts_with($phone, '56')) {
            return $phone;
        }
        
        // If it's a Chilean mobile number starting with 9, add 56
        if (str_starts_with($phone, '9') && strlen($phone) === 9) {
            return '56' . $phone;
        }
        
        return $phone;
    }
}