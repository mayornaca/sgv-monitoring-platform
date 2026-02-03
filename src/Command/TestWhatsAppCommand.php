<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use App\Notifier\WhatsApp\MetaWhatsAppOptions;

#[AsCommand(
    name: 'app:test-whatsapp',
    description: 'Test WhatsApp notification sending via Meta Business API',
)]
class TestWhatsAppCommand extends Command
{
    private ChatterInterface $chatter;
    
    public function __construct(ChatterInterface $chatter)
    {
        parent::__construct();
        $this->chatter = $chatter;
    }
    
    protected function configure(): void
    {
        $this
            ->addOption('phone', 'p', InputOption::VALUE_REQUIRED, 'Phone number to send to (with country code, e.g. +56900000000)')
            ->addOption('template', 't', InputOption::VALUE_REQUIRED, 'Template to use', 'prometheus_alert_firing')
            ->addOption('message', 'm', InputOption::VALUE_OPTIONAL, 'Message to send (for text messages)')
        ;
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $phone = $input->getOption('phone');
        $template = $input->getOption('template');
        $message = $input->getOption('message');
        
        $io->title('Testing WhatsApp Notification');
        $io->info([
            'Phone: ' . $phone,
            'Template: ' . ($template ?: 'none (text message)'),
        ]);
        
        try {
            // Create message with options
            $chatMessage = new ChatMessage('');
            
            // Configure WhatsApp options
            $options = new MetaWhatsAppOptions();
            $options->recipientId($phone);
            
            if ($template) {
                $options->template($template);
                
                // Set template parameters based on template type
                if ($template === 'prometheus_alert_firing') {
                    $options->templateParameters([
                        'TEST_ALERT',           // Alert name
                        'critical',             // Severity
                        'This is a test alert from SGV monitoring system', // Summary
                        'sgv-server-01'         // Instance
                    ]);
                } elseif ($template === 'alarma_vehiculo') {
                    $options->templateParameters([
                        '10:00 a 10:30 del ' . date('d-m-Y'), // Period
                        'Espira-001 con 15%',   // Device 1
                        'Espira-002 con 12%',   // Device 2
                        'Espira-003 con 10%'    // Device 3
                    ]);
                }
                
                $io->section('Sending template message: ' . $template);
            } else {
                // Send as text message
                $chatMessage = new ChatMessage($message ?: 'Test message from SGV monitoring system at ' . date('Y-m-d H:i:s'));
                $io->section('Sending text message');
            }
            
            $chatMessage->options($options);
            
            // Send the message
            $this->chatter->send($chatMessage);
            
            $io->success('WhatsApp message sent successfully!');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error([
                'Failed to send WhatsApp message',
                'Error: ' . $e->getMessage(),
                'File: ' . $e->getFile() . ':' . $e->getLine()
            ]);
            
            if ($output->isVerbose()) {
                $io->section('Stack Trace');
                $io->text($e->getTraceAsString());
            }
            
            return Command::FAILURE;
        }
    }
}