<?php

namespace App\Security\Handler;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Custom Authentication Success Handler for AJAX Login
 * Returns JSON response for AJAX requests, regular redirect for standard requests
 */
class AjaxAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        // Check if this is an AJAX request
        if ($request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            // Get redirect URL from target_path or default to admin
            $redirectUrl = $request->getSession()->get('_security.main.target_path');

            if (!$redirectUrl) {
                $redirectUrl = $this->urlGenerator->generate('admin');
            }

            // Return JSON response for AJAX
            return new JsonResponse([
                'login_ok' => true,
                'redirect_url' => $redirectUrl,
                'username' => $token->getUserIdentifier(),
            ]);
        }

        // For non-AJAX requests, use default Symfony behavior
        // This will be handled by the default_target_path in security.yaml
        return new Response('', Response::HTTP_FOUND, [
            'Location' => $this->urlGenerator->generate('admin')
        ]);
    }
}
