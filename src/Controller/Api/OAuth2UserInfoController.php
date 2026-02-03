<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoint OpenID Connect UserInfo para OAuth2
 * Usado por Grafana y otras aplicaciones para obtener información del usuario autenticado
 */
class OAuth2UserInfoController extends AbstractController
{
    #[Route('/api/userinfo', name: 'api_oauth2_userinfo', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function userinfo(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }

        // Construir respuesta compatible con OpenID Connect y Grafana
        $userInfo = [
            'sub' => (string) $user->getId(),
            'email' => $user->getEmail(),
            'email_verified' => true,
            'preferred_username' => $user->getEmail(),
            'login' => $user->getEmail(),
        ];

        // Añadir nombre si está disponible
        if (method_exists($user, 'getFirstName') && method_exists($user, 'getLastName')) {
            $firstName = $user->getFirstName() ?? '';
            $lastName = $user->getLastName() ?? '';

            if ($firstName || $lastName) {
                $userInfo['name'] = trim($firstName . ' ' . $lastName);
                $userInfo['given_name'] = $firstName;
                $userInfo['family_name'] = $lastName;
            }
        }

        // Añadir roles para Grafana (mapeo de roles)
        $roles = $user->getRoles();

        // Determinar rol de Grafana basado en roles de Symfony
        if (in_array('ROLE_SUPER_ADMIN', $roles)) {
            $userInfo['grafana_role'] = 'Admin';
        } elseif (in_array('ROLE_ADMIN', $roles)) {
            $userInfo['grafana_role'] = 'Editor';
        } else {
            $userInfo['grafana_role'] = 'Viewer';
        }

        // Grupos/equipos opcionales
        $userInfo['groups'] = $roles;

        return new JsonResponse($userInfo);
    }
}
