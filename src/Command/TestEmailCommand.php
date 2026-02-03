<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:test-email',
    description: 'Test email configuration by sending a test email',
)]
class TestEmailCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('to', InputArgument::REQUIRED, 'Email address to send test to')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $toEmail = $input->getArgument('to');

        $email = (new Email())
            ->from('sgv@gesvial.cl')
            ->to($toEmail)
            ->subject('Test Email - SGV')
            ->html('<p>Este es un email de prueba desde SGV.</p>
                    <p>Si recibes este mensaje, la configuración de email está funcionando correctamente.</p>
                    <hr>
                    <p><small>Enviado desde: SGV</small></p>');

        try {
            $this->mailer->send($email);
            $io->success(sprintf('Test email sent successfully to %s', $toEmail));
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to send email: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}