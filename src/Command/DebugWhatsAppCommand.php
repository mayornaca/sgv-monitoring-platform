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
    name: 'app:debug-whatsapp',
    description: 'Debug WhatsApp API directly with raw HTTP request',
)]
class DebugWhatsAppCommand extends Command
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
            ->addOption('phone', 'p', InputOption::VALUE_REQUIRED, 'Phone number (with or without +)', '56972126016')
            ->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Template name', 'prometheus_alert_firing')
        ;
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Credenciales del proyecto antiguo
        $accessToken = 'EAAR912L41MoBOx95sDl6qhXCcT9oqyvbl1ibxSmC7BG1YUHiU6BSyqtYL3fWFZC8CbVWPtToH2ara3ASrClorj3Grqg5FrkNyfyqZBSN5YPxAeToXFNCpUqerqZCueJuJC1i0t0E3BZA0gpVfrZCu7z4cuW77Up0cCG3ndAzGcopWuaQqUiG1CrhsMhwWqvBPZAwZDZD';
        $phoneNumberId = '651420641396348';
        $apiUrl = sprintf('https://graph.facebook.com/v22.0/%s/messages', $phoneNumberId);
        
        $phone = $input->getOption('phone');
        $template = $input->getOption('template');
        
        // Limpiar el número de teléfono (remover '+' si existe)
        $phone = ltrim($phone, '+');
        
        $io->title('Debugging WhatsApp API Directly');
        
        // Construir el payload
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => 'es'],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => 'TEST_ALERT'],
                            ['type' => 'text', 'text' => 'critical'],
                            ['type' => 'text', 'text' => 'This is a test alert from SGV monitoring'],
                            ['type' => 'text', 'text' => 'sgv-server-01']
                        ]
                    ]
                ]
            ]
        ];
        
        $io->section('Request Details');
        $io->text('URL: ' . $apiUrl);
        $io->text('Phone: ' . $phone . ' (cleaned, without +)');
        $io->text('Template: ' . $template);
        
        $io->section('Payload');
        $io->text(json_encode($payload, JSON_PRETTY_PRINT));
        
        try {
            $io->section('Sending Request...');
            
            $response = $this->httpClient->request('POST', $apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);
            
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
            $data = json_decode($content, true);
            
            $io->section('Response');
            $io->text('Status Code: ' . $statusCode);
            $io->text('Response Body:');
            $io->text(json_encode($data, JSON_PRETTY_PRINT));
            
            if ($statusCode === 200 || $statusCode === 201) {
                if (isset($data['messages'][0]['id'])) {
                    $io->success('Message sent successfully! ID: ' . $data['messages'][0]['id']);
                } else {
                    $io->warning('Response successful but unexpected format');
                }
            } else {
                $io->error('Failed with status code: ' . $statusCode);
                if (isset($data['error'])) {
                    $io->error('Error: ' . json_encode($data['error'], JSON_PRETTY_PRINT));
                }
            }
            
        } catch (\Exception $e) {
            $io->error('Exception: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
        }
        
        return Command::SUCCESS;
    }
}