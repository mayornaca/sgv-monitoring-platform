<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class UserProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TotpAuthenticatorInterface $totpAuthenticator,
        private AuditLogger $auditLogger
    ) {}

    #[Route('/', name: 'app_user_profile')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Redirigir al CRUD de EasyAdmin para editar el propio usuario
        return $this->redirectToRoute('admin_user_edit', [
            'entityId' => $user->getId(),
        ]);
    }

    #[Route('/2fa', name: 'app_user_2fa')]
    public function twoFactor(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');

            switch ($action) {
                case 'enable_totp':
                    return $this->enableTotp($user);

                case 'disable_totp':
                    return $this->disableTotp($user);

                case 'enable_email':
                    return $this->enableEmail($user);

                case 'disable_email':
                    return $this->disableEmail($user);

                case 'regenerate_backup_codes':
                    return $this->regenerateBackupCodes($user);
            }
        }

        return $this->render('profile/two_factor.html.twig', [
            'user' => $user,
            'qr_code' => $this->generateQrCode($user),
        ]);
    }

    private function enableTotp(User $user): Response
    {
        if (!$user->getTotpSecret()) {
            $secret = $this->totpAuthenticator->generateSecret();
            $user->setTotpSecret($secret);
        }

        $user->setTwoFactorEnabled(true);
        $user->setPreferred2faMethod('totp');

        $this->entityManager->flush();

        $this->auditLogger->logSecurityEvent(
            'totp_enabled',
            'Usuario habilitó TOTP como método de 2FA'
        );

        $this->addFlash('success', 'TOTP habilitado correctamente. Escanea el código QR con tu aplicación autenticadora.');

        return $this->redirectToRoute('app_user_2fa');
    }

    private function disableTotp(User $user): Response
    {
        $user->setTotpSecret(null);
        
        // Si solo tenía TOTP habilitado, deshabilitar 2FA completamente
        if ($user->getPreferred2faMethod() === 'totp') {
            $user->setTwoFactorEnabled(false);
            $user->setPreferred2faMethod(null);
        }

        $this->entityManager->flush();

        $this->auditLogger->logSecurityEvent(
            'totp_disabled',
            'Usuario deshabilitó TOTP'
        );

        $this->addFlash('warning', 'TOTP deshabilitado.');

        return $this->redirectToRoute('app_user_2fa');
    }

    private function enableEmail(User $user): Response
    {
        $user->setTwoFactorEnabled(true);
        $user->setPreferred2faMethod('email');

        $this->entityManager->flush();

        $this->auditLogger->logSecurityEvent(
            'email_2fa_enabled',
            'Usuario habilitó 2FA por email'
        );

        $this->addFlash('success', 'Autenticación por email habilitada.');

        return $this->redirectToRoute('app_user_2fa');
    }

    private function disableEmail(User $user): Response
    {
        // Si solo tenía email habilitado, deshabilitar 2FA completamente
        if ($user->getPreferred2faMethod() === 'email') {
            $user->setTwoFactorEnabled(false);
            $user->setPreferred2faMethod(null);
        }

        $this->entityManager->flush();

        $this->auditLogger->logSecurityEvent(
            'email_2fa_disabled',
            'Usuario deshabilitó 2FA por email'
        );

        $this->addFlash('warning', '2FA por email deshabilitado.');

        return $this->redirectToRoute('app_user_2fa');
    }

    private function regenerateBackupCodes(User $user): Response
    {
        // TODO(human): Implementar generación de códigos de respaldo

        $this->auditLogger->logSecurityEvent(
            'backup_codes_regenerated',
            'Usuario regeneró códigos de respaldo'
        );

        $this->addFlash('info', 'Códigos de respaldo regenerados.');

        return $this->redirectToRoute('app_user_2fa');
    }

    private function generateQrCode(User $user): ?string
    {
        if (!$user->getTotpSecret()) {
            return null;
        }

        // Generar URL para el código QR
        $issuer = 'GVOps';
        $accountName = $user->getEmail();
        $secret = $user->getTotpSecret();

        $qrCodeUrl = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            urlencode($issuer),
            urlencode($accountName),
            $secret,
            urlencode($issuer)
        );

        return $qrCodeUrl;
    }
}