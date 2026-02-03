<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Valida estado del usuario antes y después de autenticación
 * Implementa el patrón oficial de Symfony para validaciones de cuenta
 *
 * @see https://symfony.com/doc/current/security/user_checkers.html
 */
class UserChecker implements UserCheckerInterface
{
    /**
     * Validaciones ANTES de verificar contraseña
     * Se ejecuta después de cargar el usuario pero antes de autenticar
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Validar que el usuario esté activo
        // Mensaje genérico por seguridad (no revelar estado de cuenta)
        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException(
                'Credenciales inválidas.'
            );
        }
    }

    /**
     * Validaciones DESPUÉS de verificar contraseña
     * Extensible para verificaciones adicionales post-auth
     * Ejemplo: verificar email confirmado, cuenta no expirada, etc.
     */
    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Extensible: agregar validaciones post-autenticación aquí
        // Ejemplo: verificar si el email está confirmado
        // Ejemplo: verificar si la cuenta no ha expirado
    }
}
