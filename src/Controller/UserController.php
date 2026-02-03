<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_USER')]
class UserController extends AbstractController
{
    #[Route('/', name: 'app_user_list')]
    public function list(): Response
    {
        // Redirigir a EasyAdmin User CRUD
        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/{id}/edit', name: 'app_user_edit')]
    public function edit(
        #[MapEntity(id: 'id')] User $user,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if ($request->isMethod('POST')) {
            $user->setEmail($request->request->get('email'));
            $user->setUsername($request->request->get('username'));
            $user->setFirstName($request->request->get('firstName'));
            $user->setLastName($request->request->get('lastName'));
            
            $roles = $request->request->all('roles');
            $user->setRoles($roles ?: ['ROLE_USER']);
            
            $user->setUpdatedAt(new \DateTime());
            
            $em->flush();
            
            $this->addFlash('success', 'Usuario actualizado correctamente');
            
            return $this->redirectToRoute('app_user_list');
        }
        
        return $this->render('user/edit.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/change-password', name: 'app_user_change_password')]
    public function changePassword(
        #[MapEntity(id: 'id')] User $user,
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response {
        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('new_password');
            $confirmPassword = $request->request->get('confirm_password');
            
            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Las contraseñas no coinciden');
            } elseif (strlen($newPassword) < 8) {
                $this->addFlash('error', 'La contraseña debe tener al menos 8 caracteres');
            } else {
                $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashedPassword);
                $user->setPasswordChangedAt(new \DateTime());
                $user->setMustChangePassword(false);
                
                $em->flush();
                
                $this->addFlash('success', 'Contraseña actualizada correctamente');
                
                return $this->redirectToRoute('app_user_edit', ['id' => $user->getId()]);
            }
        }
        
        return $this->render('user/change_password.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/toggle-active', name: 'app_user_toggle_active', methods: ['POST'])]
    public function toggleActive(
        #[MapEntity(id: 'id')] User $user,
        EntityManagerInterface $em
    ): Response {
        $user->setIsActive(!$user->isActive());
        $em->flush();
        
        $status = $user->isActive() ? 'activado' : 'desactivado';
        $this->addFlash('success', "Usuario {$status} correctamente");
        
        return $this->redirectToRoute('app_user_list');
    }

    #[Route('/{id}/delete', name: 'app_user_delete', methods: ['POST'])]
    public function delete(
        #[MapEntity(id: 'id')] User $user,
        EntityManagerInterface $em
    ): Response {
        // Prevent deleting yourself
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'No puedes eliminar tu propio usuario');
            return $this->redirectToRoute('app_user_list');
        }
        
        $em->remove($user);
        $em->flush();
        
        $this->addFlash('success', 'Usuario eliminado correctamente');
        
        return $this->redirectToRoute('app_user_list');
    }
}
