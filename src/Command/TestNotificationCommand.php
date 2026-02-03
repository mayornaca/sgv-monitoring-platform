<?php

namespace App\Command;

use App\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-notifications',
    description: 'Test the notification service with different alert types'
)]
class TestNotificationCommand extends Command
{
    public function __construct(
        private NotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email recipient for testing')
            ->addOption('phone', null, InputOption::VALUE_OPTIONAL, 'Phone number for WhatsApp testing')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Type of notification to test (all, critical, warning, status)', 'all')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $phone = $input->getOption('phone');
        $type = $input->getOption('type');

        $io->title('Testing Notification Service');

        $recipients = [$email];
        if ($phone) {
            $recipients[] = $phone;
        }

        if ($type === 'all' || $type === 'critical') {
            $io->section('Testing Critical Alert');
            
            $result = $this->notificationService->sendCriticalAlert(
                [$email],
                'CPU Overload',
                'El servidor ha superado el 95% de uso de CPU por más de 5 minutos',
                [
                    'CPU Usage' => '97%',
                    'Load Average' => '8.45',
                    'Server' => 'web-server-01',
                    'Time' => date('Y-m-d H:i:s')
                ]
            );
            
            if ($result) {
                $io->success('✅ Critical alert sent successfully');
            } else {
                $io->error('❌ Failed to send critical alert');
            }
        }

        if ($type === 'all' || $type === 'warning') {
            $io->section('Testing Warning Notification');
            
            $result = $this->notificationService->sendWarning(
                [$email],
                'High Memory Usage',
                'El uso de memoria ha superado el 80% en el servidor de base de datos',
                [
                    'Memory Usage' => '85%',
                    'Available' => '2.1GB',
                    'Server' => 'db-server-01'
                ]
            );
            
            if ($result) {
                $io->success('✅ Warning notification sent successfully');
            } else {
                $io->error('❌ Failed to send warning notification');
            }
        }

        if ($type === 'all' || $type === 'status') {
            $io->section('Testing System Status Notification');
            
            $result = $this->notificationService->sendSystemStatus(
                [$email],
                'online',
                'Todos los servicios están funcionando correctamente',
                [
                    'Uptime' => '99.9%',
                    'Response Time' => '150ms',
                    'Active Users' => '1,234',
                    'Services' => 'All Green'
                ]
            );
            
            if ($result) {
                $io->success('✅ System status notification sent successfully');
            } else {
                $io->error('❌ Failed to send system status notification');
            }
        }

        if ($phone && ($type === 'all' || $type === 'whatsapp')) {
            $io->section('Testing WhatsApp Notification');
            
            $result = $this->notificationService->sendNotification(
                [$phone],
                'Test WhatsApp',
                'Este es un mensaje de prueba del sistema de monitoreo.',
                ['whatsapp'],
                'normal',
                []
            );
            
            if ($result['whatsapp']) {
                $io->success('✅ WhatsApp notification sent successfully');
            } else {
                $io->warning('⚠️ WhatsApp notification failed (check WhatsApp service configuration)');
            }
        }

        $io->section('Testing Multi-Channel Notification');
        
        $channels = ['email'];
        if ($phone) {
            $channels[] = 'whatsapp';
        }
        
        $results = $this->notificationService->sendNotification(
            $recipients,
            'SGV - Test',
            'Esta es una notificación de prueba enviada através de múltiples canales.',
            $channels,
            'normal',
            ['test' => true, 'timestamp' => time()]
        );
        
        $io->table(['Channel', 'Result'], [
            ['Email', $results['email'] ? '✅ Success' : '❌ Failed'],
            ['WhatsApp', isset($results['whatsapp']) ? ($results['whatsapp'] ? '✅ Success' : '❌ Failed') : 'Not tested']
        ]);

        $io->success('🎉 Notification service testing completed!');
        $io->note('Check your email inbox and WhatsApp for test messages.');
        $io->note('In development environment, email sending may fail due to SMTP configuration.');
        
        return Command::SUCCESS;
    }
}