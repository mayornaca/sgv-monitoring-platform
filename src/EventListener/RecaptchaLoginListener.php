<?php

namespace App\EventListener;

use App\Service\RecaptchaService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * Valida reCAPTCHA durante el proceso de login
 *
 * Se integra con el sistema de autenticación de Symfony interceptando
 * el evento CheckPassport antes de verificar credenciales.
 */
class RecaptchaLoginListener implements EventSubscriberInterface
{
    public function __construct(
        private RecaptchaService $recaptchaService,
        private RequestStack $requestStack
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Alta prioridad para validar antes de otras verificaciones
            CheckPassportEvent::class => ['onCheckPassport', 512],
        ];
    }

    /**
     * Valida reCAPTCHA antes de verificar contraseña
     */
    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return;
        }

        // Solo validar en la ruta de login
        if ($request->attributes->get('_route') !== 'app_login') {
            return;
        }

        // Skip si reCAPTCHA no está configurado
        if (!$this->recaptchaService->isEnabled()) {
            return;
        }

        $recaptchaResponse = $request->request->get('g-recaptcha-response');
        $remoteIp = $request->getClientIp();

        if (!$this->recaptchaService->validate($recaptchaResponse, $remoteIp)) {
            throw new CustomUserMessageAuthenticationException(
                'Por favor complete la verificación de seguridad (reCAPTCHA).'
            );
        }
    }
}
