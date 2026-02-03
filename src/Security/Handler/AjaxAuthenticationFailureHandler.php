<?php

namespace App\Security\Handler;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Custom Authentication Failure Handler for AJAX Login
 *
 * Implementa seguridad estricta: mensajes genéricos para errores de autenticación
 * para prevenir user enumeration y revelación de estado de cuentas.
 */
class AjaxAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    // Mensaje genérico para errores de autenticación (seguridad)
    private const GENERIC_AUTH_ERROR = 'Credenciales inválidas.';

    // Mensajes específicos para errores que NO revelan información del usuario
    private const CAPTCHA_ERROR = 'Complete la verificación de seguridad.';
    private const CSRF_ERROR = 'Error de sesión. Por favor, recargue la página.';
    private const TOO_MANY_ATTEMPTS_ERROR = 'Demasiados intentos. Intente más tarde.';
    private const GENERIC_ERROR = 'Error al procesar la solicitud.';

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $errorMessage = $this->resolveErrorMessage($exception);

        // Check if this is an AJAX request
        if ($request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return new JsonResponse([
                'error' => $errorMessage,
                'login_ok' => false,
            ], Response::HTTP_UNAUTHORIZED);
        }

        // For non-AJAX requests, redirect back to login
        return new Response('', Response::HTTP_FOUND, [
            'Location' => '/login'
        ]);
    }

    /**
     * Resuelve el mensaje de error según el tipo de excepción
     * Política de seguridad estricta: no revelar información interna
     */
    private function resolveErrorMessage(AuthenticationException $exception): string
    {
        // Errores de autenticación → mensaje genérico (seguridad)
        if ($exception instanceof UserNotFoundException ||
            $exception instanceof BadCredentialsException ||
            $exception instanceof CustomUserMessageAccountStatusException) {
            return self::GENERIC_AUTH_ERROR;
        }

        // reCAPTCHA inválido → mensaje específico (no revela info del usuario)
        if ($exception instanceof CustomUserMessageAuthenticationException) {
            $message = $exception->getMessage();
            // Solo mostrar si es sobre reCAPTCHA
            if (str_contains($message, 'reCAPTCHA') || str_contains($message, 'verificación')) {
                return self::CAPTCHA_ERROR;
            }
            // Cualquier otro CustomUserMessage → genérico por seguridad
            return self::GENERIC_AUTH_ERROR;
        }

        // CSRF inválido → mensaje técnico (no revela info del usuario)
        if ($exception instanceof InvalidCsrfTokenException) {
            return self::CSRF_ERROR;
        }

        // Rate limiting → mensaje específico (no revela info del usuario)
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return self::TOO_MANY_ATTEMPTS_ERROR;
        }

        // Cualquier otro error → genérico
        return self::GENERIC_ERROR;
    }
}
