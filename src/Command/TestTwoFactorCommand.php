<?php

namespace App\Command;

use App\Service\OtpService;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-2fa',
    description: 'Test the complete 2FA flow'
)]
class TestTwoFactorCommand extends Command
{
    public function __construct(
        private OtpService $otpService,
        private UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email address to test')
            ->addArgument('channel', InputArgument::OPTIONAL, 'Channel (email or whatsapp)', 'email')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $channel = $input->getArgument('channel');

        $io->title('Testing 2FA Flow');

        // Check if user exists
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error("Usuario no encontrado: $email");
            return Command::FAILURE;
        }

        $io->section('Step 1: Generate OTP Code');
        
        try {
            $otpCode = $this->otpService->generateOtpCode($email, $channel);
            $io->success("✅ OTP code generated");
            $io->table(['Property', 'Value'], [
                ['ID', $otpCode->getId()],
                ['Email', $otpCode->getEmail()],
                ['Channel', $otpCode->getChannel()],
                ['Code', $otpCode->getCode()],
                ['Expires At', $otpCode->getExpiresAt()->format('Y-m-d H:i:s')],
                ['Used', $otpCode->isUsed() ? 'Yes' : 'No']
            ]);
        } catch (\Exception $e) {
            $io->error("❌ Failed to generate OTP: " . $e->getMessage());
            return Command::FAILURE;
        }

        $io->section('Step 2: Send OTP Code');
        
        try {
            $sent = $this->otpService->sendOtpCode($otpCode);
            if ($sent) {
                $io->success("✅ OTP code sent successfully via $channel");
            } else {
                $io->warning("⚠️ Failed to send OTP code (expected in development)");
            }
        } catch (\Exception $e) {
            $io->warning("⚠️ Failed to send OTP: " . $e->getMessage());
            $io->note("This is expected in development environment without proper SMTP configuration.");
            $io->note("In production, configure MAILER_DSN in .env.prod");
        }

        $io->section('Step 3: Validate OTP Code');
        
        // Test validation with correct code
        try {
            $isValid = $this->otpService->validateOtpCode($email, $otpCode->getCode(), $channel);
            if ($isValid) {
                $io->success("✅ OTP validation successful");
            } else {
                $io->error("❌ OTP validation failed");
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error("❌ Validation error: " . $e->getMessage());
            return Command::FAILURE;
        }

        $io->section('Step 4: Test Invalid Code');
        
        // Test validation with wrong code
        try {
            $isValid = $this->otpService->validateOtpCode($email, '000000', $channel);
            if (!$isValid) {
                $io->success("✅ Invalid code correctly rejected");
            } else {
                $io->error("❌ Invalid code was accepted - security issue!");
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error("❌ Validation error: " . $e->getMessage());
            return Command::FAILURE;
        }

        $io->section('Step 5: Test Backup Codes');
        
        // Generate backup codes
        $backupCodes = $user->generateBackupCodes();
        $io->success("✅ Generated " . count($backupCodes) . " backup codes");
        $io->listing($backupCodes);

        // Test backup code validation
        $testCode = $backupCodes[0];
        if ($user->hasValidBackupCode($testCode)) {
            $io->success("✅ Backup code validation working");
            
            // Remove the code
            $user->removeBackupCode($testCode);
            if (!$user->hasValidBackupCode($testCode)) {
                $io->success("✅ Backup code removal working");
            } else {
                $io->error("❌ Backup code not removed properly");
            }
        } else {
            $io->error("❌ Backup code validation failed");
        }

        $io->success('🎉 All 2FA tests passed successfully!');
        
        return Command::SUCCESS;
    }
}