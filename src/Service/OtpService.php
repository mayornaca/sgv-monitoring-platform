<?php

namespace App\Service;

use App\Entity\OtpCode;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

class OtpService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private ?WhatsAppService $whatsAppService = null
    ) {}

    public function generateOtpCode(string $email, string $channel = 'email', ?string $ipAddress = null, ?string $userAgent = null): OtpCode
    {
        // Invalidate existing codes for this email
        $this->invalidateExistingCodes($email, $channel);

        // Create new OTP code
        $otpCode = new OtpCode();
        $otpCode->setEmail($email)
                ->setChannel($channel)
                ->setIpAddress($ipAddress)
                ->setUserAgent($userAgent);

        $this->entityManager->persist($otpCode);
        $this->entityManager->flush();

        $this->logger->info('OTP code generated', [
            'email' => $email,
            'channel' => $channel,
            'code_id' => $otpCode->getId(),
            'ip_address' => $ipAddress
        ]);

        return $otpCode;
    }

    public function sendOtpCode(OtpCode $otpCode): bool
    {
        try {
            if ($otpCode->getChannel() === 'email') {
                return $this->sendEmailOtp($otpCode);
            } elseif ($otpCode->getChannel() === 'whatsapp') {
                return $this->sendWhatsAppOtp($otpCode);
            }

            throw new \InvalidArgumentException('Unsupported OTP channel: ' . $otpCode->getChannel());
        } catch (\Exception $e) {
            $this->logger->error('Failed to send OTP code', [
                'email' => $otpCode->getEmail(),
                'channel' => $otpCode->getChannel(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function validateOtpCode(string $email, string $code, string $channel = 'email'): bool
    {
        $otpCode = $this->findValidOtpCode($email, $channel);
        
        if (!$otpCode) {
            $this->logger->warning('OTP validation failed - no valid code found', [
                'email' => $email,
                'channel' => $channel
            ]);
            return false;
        }

        // Increment attempts
        $otpCode->incrementAttempts();
        $this->entityManager->flush();

        if (!$otpCode->isValid()) {
            $this->logger->warning('OTP validation failed - code no longer valid', [
                'email' => $email,
                'attempts' => $otpCode->getAttempts(),
                'expired' => $otpCode->isExpired(),
                'used' => $otpCode->isUsed()
            ]);
            return false;
        }

        if ($otpCode->getCode() !== $code) {
            $this->logger->warning('OTP validation failed - incorrect code', [
                'email' => $email,
                'attempts' => $otpCode->getAttempts()
            ]);
            return false;
        }

        // Mark as used
        $otpCode->setUsed(true);
        $this->entityManager->flush();

        $this->logger->info('OTP validation successful', [
            'email' => $email,
            'channel' => $channel
        ]);

        return true;
    }

    public function findValidOtpCode(string $email, string $channel = 'email'): ?OtpCode
    {
        return $this->entityManager->getRepository(OtpCode::class)
            ->findOneBy(
                [
                    'email' => $email,
                    'channel' => $channel,
                    'used' => false
                ],
                ['createdAt' => 'DESC']
            );
    }

    public function cleanExpiredCodes(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(OtpCode::class, 'o')
           ->where('o.expiresAt < :now')
           ->setParameter('now', new \DateTime());

        $deleted = $qb->getQuery()->execute();

        $this->logger->info('Expired OTP codes cleaned', ['count' => $deleted]);

        return $deleted;
    }

    private function invalidateExistingCodes(string $email, string $channel): void
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->update(OtpCode::class, 'o')
           ->set('o.used', true)
           ->where('o.email = :email')
           ->andWhere('o.channel = :channel')
           ->andWhere('o.used = false')
           ->setParameter('email', $email)
           ->setParameter('channel', $channel);

        $qb->getQuery()->execute();
    }

    private function sendEmailOtp(OtpCode $otpCode): bool
    {
        try {
            $email = (new Email())
                ->from('no-reply@gvops.cl')
                ->to($otpCode->getEmail())
                ->subject('Código de verificación - SGV')
                ->text(sprintf(
                    "Tu código de verificación es: %s\n\nEste código expira en 10 minutos.\n\nSi no solicitaste este código, ignora este mensaje.",
                    $otpCode->getCode()
                ))
                ->html(sprintf(
                    '<p>Tu código de verificación es: <strong style="font-size: 24px; color: #2563eb;">%s</strong></p>
                     <p>Este código expira en 10 minutos.</p>
                     <p><small>Si no solicitaste este código, ignora este mensaje.</small></p>',
                    $otpCode->getCode()
                ));

            $this->mailer->send($email);
            
            $this->logger->info('OTP email sent successfully', [
                'email' => $otpCode->getEmail(),
                'code_id' => $otpCode->getId()
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send OTP email', [
                'email' => $otpCode->getEmail(),
                'error' => $e->getMessage(),
                'error_class' => get_class($e)
            ]);
            
            throw new \RuntimeException(
                'No se pudo enviar el email de verificación. Verifique la configuración SMTP.',
                0,
                $e
            );
        }
    }

    private function sendWhatsAppOtp(OtpCode $otpCode): bool
    {
        if (!$this->whatsAppService) {
            throw new \RuntimeException('WhatsApp service is not available');
        }

        // Extract phone from email or use a phone field from User entity
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $otpCode->getEmail()]);

        if (!$user) {
            throw new \RuntimeException('User not found for WhatsApp delivery');
        }

        // Check if user has a phone method, if not, try to use a default or fallback
        $phone = null;
        if (method_exists($user, 'getPhone')) {
            $phone = $user->getPhone();
        }

        if (!$phone) {
            throw new \RuntimeException('No phone number found for WhatsApp delivery');
        }

        $message = sprintf(
            "🖥️ *SGV*\n\nTu código de verificación es: *%s*\n\nEste código expira en 10 minutos.\n\nSi no solicitaste este código, ignora este mensaje.",
            $otpCode->getCode()
        );

        return $this->whatsAppService->sendMessage($phone, $message);
    }
}