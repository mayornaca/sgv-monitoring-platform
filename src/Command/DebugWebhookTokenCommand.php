<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug:webhook-token',
    description: 'Debug webhook token configuration (secure)',
)]
class DebugWebhookTokenCommand extends Command
{
    public function __construct(
        private string $whatsappWebhookVerifyToken
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('WhatsApp Webhook Token Debug');

        $io->section('Token Information (Secure)');
        $io->table(
            ['Property', 'Value'],
            [
                ['Is null?', $this->whatsappWebhookVerifyToken === null ? 'YES' : 'NO'],
                ['Is empty?', empty($this->whatsappWebhookVerifyToken) ? 'YES' : 'NO'],
                ['Length', strlen($this->whatsappWebhookVerifyToken ?? '')],
                ['MD5 Hash (first 8 chars)', $this->whatsappWebhookVerifyToken ? substr(md5($this->whatsappWebhookVerifyToken), 0, 8) : 'N/A'],
                ['Starts with', $this->whatsappWebhookVerifyToken ? substr($this->whatsappWebhookVerifyToken, 0, 4) . '...' : 'N/A'],
                ['Ends with', $this->whatsappWebhookVerifyToken ? '...' . substr($this->whatsappWebhookVerifyToken, -4) : 'N/A'],
            ]
        );

        // Test comparison
        $testToken = $_ENV['CRON_AUTH_TOKEN'] ?? 'test_token_not_configured';
        $io->section('Test Comparison');
        $io->writeln(sprintf('Test token matches: %s', $testToken === $this->whatsappWebhookVerifyToken ? 'YES' : 'NO'));
        $io->writeln(sprintf('Test token length: %d', strlen($testToken)));
        $io->writeln(sprintf('Test token MD5: %s', substr(md5($testToken), 0, 8)));

        $io->success('Debug complete');

        return Command::SUCCESS;
    }
}
