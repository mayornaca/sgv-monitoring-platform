<?php

namespace App\Twig\Components;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[AsLiveComponent]
class PasswordChange extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public User $user;

    #[LiveProp(writable: true)]
    public string $newPassword = '';

    #[LiveProp(writable: true)]
    public string $confirmPassword = '';

    #[LiveProp]
    public array $errors = [];

    #[LiveProp]
    public bool $isValid = false;

    #[LiveProp]
    public string $successMessage = '';

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager
    ) {}

    public function validatePasswords(): void
    {
        $this->errors = [];
        $this->isValid = false;

        if (empty($this->newPassword)) {
            $this->errors[] = 'La contraseña es requerida';
            return;
        }

        if (strlen($this->newPassword) < 8) {
            $this->errors[] = 'La contraseña debe tener al menos 8 caracteres';
        }

        if (!preg_match('/[A-Z]/', $this->newPassword)) {
            $this->errors[] = 'La contraseña debe contener al menos una mayúscula';
        }

        if (!preg_match('/[a-z]/', $this->newPassword)) {
            $this->errors[] = 'La contraseña debe contener al menos una minúscula';
        }

        if (!preg_match('/[0-9]/', $this->newPassword)) {
            $this->errors[] = 'La contraseña debe contener al menos un número';
        }

        if ($this->newPassword !== $this->confirmPassword) {
            $this->errors[] = 'Las contraseñas no coinciden';
        }

        $this->isValid = empty($this->errors);
    }

    #[LiveAction]
    public function save(): void
    {
        $this->validatePasswords();

        if (!$this->isValid) {
            return;
        }

        try {
            $hashedPassword = $this->passwordHasher->hashPassword($this->user, $this->newPassword);
            $this->user->setPassword($hashedPassword);
            $this->user->setPasswordChangedAt(new \DateTime());
            $this->user->setMustChangePassword(false);
            
            $this->entityManager->flush();
            
            $this->successMessage = 'Contraseña actualizada correctamente';
            $this->newPassword = '';
            $this->confirmPassword = '';
        } catch (\Exception $e) {
            $this->errors[] = 'Error al actualizar la contraseña. Inténtalo de nuevo.';
        }
    }

    public function getPasswordStrength(): array
    {
        if (empty($this->newPassword)) {
            return ['strength' => 0, 'label' => '', 'class' => ''];
        }

        $score = 0;
        $checks = [
            'length' => strlen($this->newPassword) >= 8,
            'uppercase' => preg_match('/[A-Z]/', $this->newPassword),
            'lowercase' => preg_match('/[a-z]/', $this->newPassword),
            'numbers' => preg_match('/[0-9]/', $this->newPassword),
            'special' => preg_match('/[^A-Za-z0-9]/', $this->newPassword),
        ];

        $score = array_sum($checks);

        $strength = [
            0 => ['strength' => 0, 'label' => '', 'class' => ''],
            1 => ['strength' => 20, 'label' => 'Muy débil', 'class' => 'progress-error'],
            2 => ['strength' => 40, 'label' => 'Débil', 'class' => 'progress-warning'],
            3 => ['strength' => 60, 'label' => 'Regular', 'class' => 'progress-info'],
            4 => ['strength' => 80, 'label' => 'Fuerte', 'class' => 'progress-success'],
            5 => ['strength' => 100, 'label' => 'Muy fuerte', 'class' => 'progress-success'],
        ];

        return $strength[$score] ?? $strength[0];
    }

    public function getPasswordChecks(): array
    {
        if (empty($this->newPassword)) {
            return [
                'length' => false,
                'uppercase' => false,
                'lowercase' => false,
                'numbers' => false,
                'special' => false,
            ];
        }

        return [
            'length' => strlen($this->newPassword) >= 8,
            'uppercase' => preg_match('/[A-Z]/', $this->newPassword),
            'lowercase' => preg_match('/[a-z]/', $this->newPassword),
            'numbers' => preg_match('/[0-9]/', $this->newPassword),
            'special' => preg_match('/[^A-Za-z0-9]/', $this->newPassword),
        ];
    }
}