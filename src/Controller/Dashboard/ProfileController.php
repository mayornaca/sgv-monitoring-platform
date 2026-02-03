<?php

namespace App\Controller\Dashboard;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[AdminRoute('/profile', name: 'app_profile_show')]
    public function show(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $action = $request->request->get('action');

        // Manejar POST (edición o cambio de contraseña)
        if ($request->isMethod('POST')) {
            // Verificar CSRF token
            $token = $request->request->get('_token');

            if ($action === 'edit') {
                if (!$this->isCsrfTokenValid('profile_edit', $token)) {
                    $this->addFlash('danger', 'Token de seguridad inválido');
                    return $this->redirectToRoute('admin_app_profile_show');
                }

                // Actualizar datos del perfil
                $user->setFirstName($request->request->get('firstName'));
                $user->setLastName($request->request->get('lastName'));
                $user->setEmail($request->request->get('email'));
                $user->setUsername($request->request->get('username'));
                $user->setLocale($request->request->get('locale'));
                $user->setUpdatedAt(new \DateTime());

                $em->flush();
                $this->addFlash('success', 'Tu perfil ha sido actualizado correctamente.');

            } elseif ($action === 'change_password') {
                if (!$this->isCsrfTokenValid('profile_password', $token)) {
                    $this->addFlash('danger', 'Token de seguridad inválido');
                    return $this->redirectToRoute('admin_app_profile_show');
                }

                $currentPassword = $request->request->get('currentPassword');
                $newPassword = $request->request->get('newPassword');
                $confirmPassword = $request->request->get('confirmPassword');

                // Verificar contraseña actual
                if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                    $this->addFlash('danger', 'La contraseña actual no es correcta');
                    return $this->redirectToRoute('admin_app_profile_show');
                }

                // Verificar que las nuevas contraseñas coincidan
                if ($newPassword !== $confirmPassword) {
                    $this->addFlash('danger', 'Las contraseñas nuevas no coinciden');
                    return $this->redirectToRoute('admin_app_profile_show');
                }

                // Validar longitud y complejidad
                if (strlen($newPassword) < 8 ||
                    !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $newPassword)) {
                    $this->addFlash('danger', 'La contraseña debe tener al menos 8 caracteres, incluir mayúsculas, minúsculas y números');
                    return $this->redirectToRoute('admin_app_profile_show');
                }

                // Hashear y guardar nueva contraseña
                $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashedPassword);
                $user->setUpdatedAt(new \DateTime());

                $em->flush();
                $this->addFlash('success', 'Tu contraseña ha sido cambiada correctamente');

            } elseif ($action === 'change_locale') {
                if (!$this->isCsrfTokenValid('profile_locale', $token)) {
                    $this->addFlash('danger', 'Token de seguridad inválido');
                    return $this->redirectToRoute('admin_app_profile_show');
                }

                $locale = $request->request->get('locale');

                // Validar que el idioma sea válido
                if (!in_array($locale, ['es', 'en'])) {
                    $this->addFlash('danger', 'Idioma no válido');
                    return $this->redirectToRoute('admin_app_profile_show');
                }

                $user->setLocale($locale);
                $user->setUpdatedAt(new \DateTime());

                $em->flush();
                $this->addFlash('success', 'Tus preferencias de idioma han sido guardadas correctamente');
            }

            return $this->redirectToRoute('admin_app_profile_show');
        }

        // Mostrar perfil (GET)
        return $this->render('profile/show.html.twig', [
            'user' => $user,
        ]);
    }
}
