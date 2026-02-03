<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Repository\UserRepository;
use App\Service\RecaptchaService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;

#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    public function __construct(
        private TokenGeneratorInterface $tokenGenerator,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private UserPasswordHasherInterface $passwordHasher,
        private RecaptchaService $recaptchaService,
        private UserRepository $userRepository
    ) {}

    #[Route('', name: 'app_forgot_password')]
    public function request(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            // Validar reCAPTCHA si está habilitado
            if ($this->recaptchaService->isEnabled()) {
                $recaptchaResponse = $request->request->get('g-recaptcha-response');
                if (!$this->recaptchaService->validate($recaptchaResponse, $request->getClientIp())) {
                    $this->addFlash('error', 'Por favor complete la verificación de seguridad (reCAPTCHA).');
                    return $this->redirectToRoute('app_forgot_password');
                }
            }

            $identifier = trim($request->request->get('email', ''));

            if (empty($identifier)) {
                $this->addFlash('error', 'Por favor ingresa tu email o nombre de usuario.');
                return $this->redirectToRoute('app_forgot_password');
            }

            // Buscar por email O username usando el método del repositorio
            $user = $this->userRepository->findByIdentifier($identifier);

            if ($user && $user->isActive()) {
                // Generate reset token
                $resetToken = $this->tokenGenerator->generateToken();
                $user->setResetToken($resetToken);
                $user->setResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));

                $this->entityManager->flush();

                // Send email
                $resetUrl = $this->generateUrl('app_reset_password',
                    ['token' => $resetToken],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $emailMessage = (new Email())
                    ->from('sgv@gesvial.cl')
                    ->to($user->getEmail())
                    ->subject('Recuperación de contraseña - SGV')
                    ->html(sprintf(
                        '<p>Hola %s,</p>
                        <p>Para restablecer tu contraseña, haz clic en el siguiente enlace:</p>
                        <p><a href="%s">%s</a></p>
                        <p>Este enlace expirará en 1 hora.</p>
                        <p>Si no solicitaste este cambio, ignora este mensaje.</p>',
                        $user->getFirstName() ?: $user->getUsername(),
                        $resetUrl,
                        $resetUrl
                    ));

                $this->mailer->send($emailMessage);
            }

            // Always show success message to prevent user enumeration
            $this->addFlash('success', 'Si existe una cuenta con ese email o usuario, recibirás instrucciones para restablecer tu contraseña.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password_request.html.twig', [
            'recaptcha_site_key' => $this->recaptchaService->getSiteKey(),
            'recaptcha_enabled' => $this->recaptchaService->isEnabled(),
        ]);
    }

    #[Route('/reset/{token}', name: 'app_reset_password')]
    public function reset(Request $request, string $token): Response
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['resetToken' => $token]);
        
        if (!$user || !$user->getResetTokenExpiresAt() || $user->getResetTokenExpiresAt() < new \DateTimeImmutable()) {
            $this->addFlash('error', 'El enlace de recuperación es inválido o ha expirado.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Update password
            $user->setPassword(
                $this->passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );
            
            // Clear reset token
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);
            $user->setMustChangePassword(false);
            
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Tu contraseña ha sido actualizada exitosamente.');
            
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }
}