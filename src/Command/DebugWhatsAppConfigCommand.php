<?php

namespace App\Command;

use App\Service\ConfigurationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:whatsapp:debug-config',
    description: 'Muestra la configuración actual de WhatsApp desde ConfigurationService'
)]
class DebugWhatsAppConfigCommand extends Command
{
    public function __construct(
        private readonly ConfigurationService $configService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Configuración WhatsApp desde ConfigurationService');

        // Primary
        $io->section('PRIMARY Number (Alertas SGV)');
        $primaryToken = $this->configService->get('whatsapp.primary.token');
        $primaryPhoneId = $this->configService->get('whatsapp.primary.phone_id');

        $io->definitionList(
            ['Phone ID' => $primaryPhoneId ?? 'NO CONFIGURADO'],
            ['Token' => $primaryToken ? $this->maskToken($primaryToken) : 'NO CONFIGURADO'],
            ['Token Length' => $primaryToken ? strlen($primaryToken) . ' caracteres' : 'N/A']
        );

        // Backup
        $io->section('BACKUP Number (Craetion Cloud Spa)');
        $backupToken = $this->configService->get('whatsapp.backup.token');
        $backupPhoneId = $this->configService->get('whatsapp.backup.phone_id');

        $io->definitionList(
            ['Phone ID' => $backupPhoneId ?? 'NO CONFIGURADO'],
            ['Token' => $backupToken ? $this->maskToken($backupToken) : 'NO CONFIGURADO'],
            ['Token Length' => $backupToken ? strlen($backupToken) . ' caracteres' : 'N/A']
        );

        // General config
        $io->section('Configuración General');
        $io->definitionList(
            ['API Version' => $this->configService->get('whatsapp.api_version', 'v22.0')],
            ['Failover Threshold' => $this->configService->get('whatsapp.failover_threshold', 3)],
            ['Max Retries' => $this->configService->get('whatsapp.max_retries', 5)],
            ['Webhook Token' => $this->maskToken($this->configService->get('whatsapp.webhook_verify_token', ''))]
        );

        // Verificar estado
        $io->section('Estado de Verificación');

        $allConfigured = $primaryToken && $primaryPhoneId && $backupToken && $backupPhoneId;

        if ($allConfigured) {
            $io->success('✅ Todas las configuraciones están completas');
            $io->text([
                'El sistema está listo para enviar mensajes.',
                'Puedes probar con: php bin/console app:test-whatsapp-prometheus'
            ]);
            return Command::SUCCESS;
        } else {
            $io->error('❌ Faltan configuraciones');
            $io->text('Configura los valores faltantes en EasyAdmin → Configuraciones');
            return Command::FAILURE;
        }
    }

    private function maskToken(?string $token): string
    {
        if (!$token || strlen($token) < 20) {
            return '***NO VÁLIDO***';
        }

        return substr($token, 0, 10) . '...' . substr($token, -10) . ' (' . strlen($token) . ' caracteres)';
    }
}
