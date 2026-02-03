<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:test-whatsapp-webhook',
    description: 'Test WhatsApp webhook locally with sample payloads',
)]
class TestWhatsAppWebhookCommand extends Command
{
    private HttpClientInterface $httpClient;
    
    public function __construct(HttpClientInterface $httpClient)
    {
        parent::__construct();
        $this->httpClient = $httpClient;
    }
    
    protected function configure(): void
    {
        $this
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Type of test: verify, status, message', 'verify')
            ->addOption('status', 's', InputOption::VALUE_REQUIRED, 'Status type: sent, delivered, read, failed', 'delivered')
            ->addOption('url', 'u', InputOption::VALUE_REQUIRED, 'Webhook URL', 'http://localhost:8000/api/whatsapp/webhook')
        ;
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $type = $input->getOption('type');
        $webhookUrl = $input->getOption('url');
        
        $io->title('Testing WhatsApp Webhook');
        $io->text('URL: ' . $webhookUrl);
        $io->text('Test Type: ' . $type);
        
        switch ($type) {
            case 'verify':
                $this->testVerification($io, $webhookUrl);
                break;
                
            case 'status':
                $status = $input->getOption('status');
                $this->testStatusUpdate($io, $webhookUrl, $status);
                break;
                
            case 'message':
                $this->testIncomingMessage($io, $webhookUrl);
                break;
                
            default:
                $io->error('Invalid test type: ' . $type);
                return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
    
    private function testVerification(SymfonyStyle $io, string $url): void
    {
        $io->section('Testing Webhook Verification');
        
        $verifyToken = $_ENV['WHATSAPP_WEBHOOK_VERIFY_TOKEN'] ?? 'sgv_monitor_2025';
        $challenge = 'test_challenge_' . uniqid();
        
        $params = [
            'hub.mode' => 'subscribe',
            'hub.verify_token' => $verifyToken,
            'hub.challenge' => $challenge
        ];
        
        $verifyUrl = $url . '?' . http_build_query($params);
        
        $io->text('Sending GET request to: ' . $verifyUrl);
        
        try {
            $response = $this->httpClient->request('GET', $verifyUrl);
            $content = $response->getContent();
            $statusCode = $response->getStatusCode();
            
            $io->text('Status Code: ' . $statusCode);
            $io->text('Response: ' . $content);
            
            if ($content === $challenge && $statusCode === 200) {
                $io->success('Webhook verification successful!');
            } else {
                $io->warning('Unexpected response');
            }
            
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
        }
    }
    
    private function testStatusUpdate(SymfonyStyle $io, string $url, string $status): void
    {
        $io->section('Testing Status Update: ' . $status);
        
        $messageId = 'wamid.TEST_' . strtoupper(uniqid());
        $timestamp = time();
        
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '15550783881',
                                    'phone_number_id' => '651420641396348'
                                ],
                                'statuses' => [
                                    [
                                        'id' => $messageId,
                                        'status' => $status,
                                        'timestamp' => (string)$timestamp,
                                        'recipient_id' => '56972126016'
                                    ]
                                ]
                            ],
                            'field' => 'messages'
                        ]
                    ]
                ]
            ]
        ];
        
        // Agregar errores si el estado es 'failed'
        if ($status === 'failed') {
            $payload['entry'][0]['changes'][0]['value']['statuses'][0]['errors'] = [
                [
                    'code' => 131026,
                    'title' => 'Message undeliverable',
                    'message' => 'Unable to deliver message',
                    'error_data' => [
                        'details' => 'The recipient phone is offline or blocked'
                    ]
                ]
            ];
        }
        
        $io->text('Payload:');
        $io->text(json_encode($payload, JSON_PRETTY_PRINT));
        
        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => $payload
            ]);
            
            $statusCode = $response->getStatusCode();
            $content = $response->getContent();
            
            $io->text('Status Code: ' . $statusCode);
            $io->text('Response: ' . $content);
            
            if ($statusCode === 200) {
                $io->success('Status update sent successfully!');
            } else {
                $io->warning('Unexpected status code');
            }
            
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
        }
    }
    
    private function testIncomingMessage(SymfonyStyle $io, string $url): void
    {
        $io->section('Testing Incoming Message');
        
        $messageId = 'wamid.INCOMING_' . strtoupper(uniqid());
        $timestamp = time();
        
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '15550783881',
                                    'phone_number_id' => '651420641396348'
                                ],
                                'messages' => [
                                    [
                                        'id' => $messageId,
                                        'from' => '56972126016',
                                        'timestamp' => (string)$timestamp,
                                        'type' => 'text',
                                        'text' => [
                                            'body' => 'Test message from webhook test command'
                                        ]
                                    ]
                                ]
                            ],
                            'field' => 'messages'
                        ]
                    ]
                ]
            ]
        ];
        
        $io->text('Payload:');
        $io->text(json_encode($payload, JSON_PRETTY_PRINT));
        
        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => $payload
            ]);
            
            $statusCode = $response->getStatusCode();
            $content = $response->getContent();
            
            $io->text('Status Code: ' . $statusCode);
            $io->text('Response: ' . $content);
            
            if ($statusCode === 200) {
                $io->success('Incoming message sent successfully!');
            } else {
                $io->warning('Unexpected status code');
            }
            
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
        }
    }
}