<?php

namespace App\Security\Handler;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Custom Authentication Failure Handler for AJAX Login
 * Returns JSON response for AJAX requests with translated error messages
 */
class AjaxAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    private TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        // Check if this is an AJAX request
        if ($request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            // Translate error message to Spanish
            $errorMessage = $this->translator->trans($exception->getMessageKey(), $exception->getMessageData(), 'security');

            // Fallback to more user-friendly messages
            if (empty($errorMessage) || $errorMessage === $exception->getMessageKey()) {
                $errorMessage = 'Usuario o contraseña incorrectos. Por favor, intente nuevamente.';
            }

            // Return JSON response for AJAX
            return new JsonResponse([
                'error' => $errorMessage,
                'login_ok' => false,
            ], Response::HTTP_UNAUTHORIZED);
        }

        // For non-AJAX requests, let Symfony handle it normally
        // This will redirect back to login page with error
        return new Response('', Response::HTTP_FOUND, [
            'Location' => '/login'
        ]);
    }
}
