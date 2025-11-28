<?php

namespace App\Command;

use App\Entity\Alert;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

#[AsCommand(
    name: 'app:test-notifier',
    description: 'Prueba el sistema de notificaciones de Symfony',
)]
class TestNotifierCommand extends Command
{
    public function __construct(
        private NotifierInterface $notifier
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Email para enviar notificación')
            ->addOption('importance', null, InputOption::VALUE_OPTIONAL, 'Importancia: urgent, high, medium, low', 'medium')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $importance = $input->getOption('importance');
        $email = $input->getOption('email');
        
        // Crear una notificación simple
        $notification = new Notification(
            'Alerta de Prueba SGV',
            ['email', 'browser']
        );
        
        // Establecer la importancia
        switch ($importance) {
            case 'urgent':
                $notification->importance(Notification::IMPORTANCE_URGENT);
                break;
            case 'high':
                $notification->importance(Notification::IMPORTANCE_HIGH);
                break;
            case 'low':
                $notification->importance(Notification::IMPORTANCE_LOW);
                break;
            default:
                $notification->importance(Notification::IMPORTANCE_MEDIUM);
        }
        
        $notification->content('Esta es una notificación de prueba del sistema SGV. Dispositivo #123 ha reportado un fallo.');
        
        $io->section('Enviando notificación');
        $io->listing([
            'Título: ' . $notification->getSubject(),
            'Importancia: ' . $notification->getImportance(),
            'Canales solicitados: email, browser'
        ]);
        
        // Enviar notificación
        if ($email) {
            $recipient = new Recipient($email);
            $this->notifier->send($notification, $recipient);
            $io->success('Notificación enviada a: ' . $email);
        } else {
            // Enviar a los admin recipients configurados
            $adminRecipients = $this->notifier->getAdminRecipients();
            if (empty($adminRecipients)) {
                $io->warning('No hay admin_recipients configurados en notifier.yaml');
                $io->note('Configurados en notifier.yaml:');
                $io->text('- sgv@gesvial.cl');
                $io->text('- admin@gesvial.cl');
            } else {
                $this->notifier->send($notification, ...$adminRecipients);
                $io->success('Notificación enviada a los administradores configurados');
            }
        }
        
        $io->section('Cómo funciona el sistema:');
        $io->text('1. Browser Channel: Agrega flash messages a la sesión');
        $io->text('2. Email Channel: Envía emails usando Symfony Mailer');
        $io->text('3. SMS Channel: Requiere configurar transportes (Twilio, etc)');
        $io->text('4. Chat Channel: Requiere configurar transportes (Slack, Telegram, etc)');
        
        $io->section('Configuración actual (notifier.yaml):');
        $io->text('urgent: [email, browser]');
        $io->text('high: [email, browser]');
        $io->text('medium: [email, browser]');
        $io->text('low: [browser]');
        
        return Command::SUCCESS;
    }
}