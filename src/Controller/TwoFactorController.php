<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\OtpService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/auth/2fa', name: 'app_2fa_')]
class TwoFactorController extends AbstractController
{
    public function __construct(
        private OtpService $otpService,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/choose', name: 'choose')]
    public function choose(SessionInterface $session): Response
    {
        // Check if user is in the middle of 2FA process
        if (!$session->has('2fa_user_id')) {
            return $this->redirectToRoute('app_login');
        }

        $userId = $session->get('2fa_user_id');
        $user = $this->entityManager->getRepository(User::class)->find($userId);
        
        if (!$user) {
            $session->remove('2fa_user_id');
            return $this->redirectToRoute('app_login');
        }

        // Check if user has phone for WhatsApp option
        $hasPhone = method_exists($user, 'getPhone') && $user->getPhone();

        return $this->render('two_factor/choose.html.twig', [
            'user' => $user,
            'hasPhone' => $hasPhone
        ]);
    }

    #[Route('/send', name: 'send', methods: ['POST'])]
    public function send(Request $request, SessionInterface $session): Response
    {
        if (!$session->has('2fa_user_id')) {
            return $this->redirectToRoute('app_login');
        }

        $userId = $session->get('2fa_user_id');
        $user = $this->entityManager->getRepository(User::class)->find($userId);
        
        if (!$user) {
            $session->remove('2fa_user_id');
            return $this->redirectToRoute('app_login');
        }

        $channel = $request->request->get('channel', 'email');
        
        if (!in_array($channel, ['email', 'whatsapp'])) {
            $this->addFlash('error', 'Canal de verificación inválido');
            return $this->redirectToRoute('app_2fa_choose');
        }

        try {
            $ipAddress = $request->getClientIp();
            $userAgent = $request->headers->get('User-Agent');

            $otpCode = $this->otpService->generateOtpCode(
                $user->getEmail(),
                $channel,
                $ipAddress,
                $userAgent
            );

            $sent = $this->otpService->sendOtpCode($otpCode);

            if ($sent) {
                $session->set('2fa_channel', $channel);
                $channelName = $channel === 'email' ? 'correo electrónico' : 'WhatsApp';
                $this->addFlash('success', "Código enviado por {$channelName}");
                
                return $this->redirectToRoute('app_2fa_verify');
            } else {
                $this->addFlash('error', 'Error al enviar el código. Intenta nuevamente.');
            }

        } catch (\Exception $e) {
            $this->addFlash('error', 'Error al procesar la solicitud. Por favor, intente nuevamente.');
        }

        return $this->redirectToRoute('app_2fa_choose');
    }

    #[Route('/verify', name: 'verify')]
    public function verify(Request $request, SessionInterface $session): Response
    {
        if (!$session->has('2fa_user_id')) {
            return $this->redirectToRoute('app_login');
        }

        $userId = $session->get('2fa_user_id');
        $user = $this->entityManager->getRepository(User::class)->find($userId);
        $channel = $session->get('2fa_channel', 'email');
        
        if (!$user) {
            $session->remove('2fa_user_id');
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $code = $request->request->get('code');
            
            if (empty($code)) {
                $this->addFlash('error', 'Por favor ingresa el código de verificación');
                return $this->render('two_factor/verify.html.twig', [
                    'user' => $user,
                    'channel' => $channel
                ]);
            }

            try {
                $isValid = $this->otpService->validateOtpCode($user->getEmail(), $code, $channel);

                if ($isValid) {
                    // Clear 2FA session data
                    $session->remove('2fa_user_id');
                    $session->remove('2fa_channel');
                    
                    // Set authenticated user in session 
                    $session->set('_security_main', serialize([
                        'user' => $user,
                        'authenticated' => true
                    ]));

                    $this->addFlash('success', 'Autenticación exitosa');
                    
                    // Redirect to intended page or dashboard
                    $targetPath = $session->get('_security.main.target_path');
                    if ($targetPath) {
                        $session->remove('_security.main.target_path');
                        return $this->redirect($targetPath);
                    }

                    return $this->redirectToRoute('app_dashboard');
                } else {
                    $this->addFlash('error', 'Código inválido o expirado');
                }

            } catch (\Exception $e) {
                $this->addFlash('error', 'Error al verificar el código. Por favor, intente nuevamente.');
            }
        }

        return $this->render('two_factor/verify.html.twig', [
            'user' => $user,
            'channel' => $channel,
            'channelName' => $channel === 'email' ? 'correo electrónico' : 'WhatsApp'
        ]);
    }

    #[Route('/resend', name: 'resend', methods: ['POST'])]
    public function resend(SessionInterface $session, Request $request): Response
    {
        if (!$session->has('2fa_user_id')) {
            return $this->redirectToRoute('app_login');
        }

        $userId = $session->get('2fa_user_id');
        $user = $this->entityManager->getRepository(User::class)->find($userId);
        $channel = $session->get('2fa_channel', 'email');
        
        if (!$user) {
            $session->remove('2fa_user_id');
            return $this->redirectToRoute('app_login');
        }

        try {
            $ipAddress = $request->getClientIp();
            $userAgent = $request->headers->get('User-Agent');

            $otpCode = $this->otpService->generateOtpCode(
                $user->getEmail(),
                $channel,
                $ipAddress,
                $userAgent
            );

            $sent = $this->otpService->sendOtpCode($otpCode);

            if ($sent) {
                $channelName = $channel === 'email' ? 'correo electrónico' : 'WhatsApp';
                $this->addFlash('success', "Nuevo código enviado por {$channelName}");
            } else {
                $this->addFlash('error', 'Error al reenviar el código');
            }

        } catch (\Exception $e) {
            $this->addFlash('error', 'Error al procesar la solicitud. Por favor, intente nuevamente.');
        }

        return $this->redirectToRoute('app_2fa_verify');
    }
}