<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:migrate-users',
    description: 'Migra usuarios desde tbl_00_users a security_user',
)]
class MigrateUsersCommand extends Command
{
    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Solo muestra lo que se haría sin ejecutar cambios')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Fuerza la migración sobrescribiendo usuarios existentes')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limita el número de usuarios a migrar', 0)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');
        $limit = (int) $input->getOption('limit');

        $io->title('Migración de Usuarios desde tbl_00_users');

        // Verificar que la tabla antigua existe
        try {
            $this->connection->fetchOne('SELECT COUNT(*) FROM tbl_00_users');
        } catch (\Exception $e) {
            $io->error('La tabla tbl_00_users no existe o no es accesible');
            return Command::FAILURE;
        }

        // Obtener usuarios de la tabla antigua
        $query = 'SELECT * FROM tbl_00_users WHERE reg_state = 1 ORDER BY id';
        if ($limit > 0) {
            $query .= ' LIMIT ' . $limit;
        }

        $oldUsers = $this->connection->fetchAllAssociative($query);
        
        if (empty($oldUsers)) {
            $io->info('No se encontraron usuarios para migrar');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Se encontraron %d usuarios para migrar', count($oldUsers)));

        $migrated = 0;
        $skipped = 0;
        $errors = 0;

        $io->progressStart(count($oldUsers));

        foreach ($oldUsers as $oldUser) {
            $io->progressAdvance();

            try {
                // Verificar si el usuario ya existe
                $existingUser = $this->userRepository->findOneBy([
                    'username' => $oldUser['username']
                ]);

                if ($existingUser && !$force) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $io->writeln(sprintf(
                        '[DRY RUN] Migraría usuario: %s (%s) - %s %s',
                        $oldUser['username'],
                        $oldUser['email'] ?? 'sin email',
                        $oldUser['first_name'],
                        $oldUser['last_name']
                    ), OutputInterface::VERBOSITY_VERBOSE);
                    $migrated++;
                    continue;
                }

                // Crear o actualizar usuario
                $user = $existingUser ?? new User();

                $user->setUsername($oldUser['username']);
                $user->setEmail($oldUser['email'] ?? $oldUser['username'] . '@example.com');
                $user->setFirstName($oldUser['first_name']);
                $user->setLastName($oldUser['last_name']);

                // Mapear roles antiguos a nuevos
                $roles = $this->mapRoles($oldUser['role']);
                $user->setRoles($roles);

                // Solo actualizar contraseña si es un usuario nuevo o si se fuerza
                if (!$existingUser || $force) {
                    // La contraseña antigua ya está hasheada, así que la usamos directamente
                    // pero marcamos que debe cambiarla
                    $user->setPassword($oldUser['password']);
                    $user->setMustChangePassword(true);
                }

                $user->setIsActive((bool) $oldUser['is_active']);
                $user->setLocale($oldUser['locale'] ?? 'es');

                // Mapear fechas (solo updatedAt, createdAt se establece en constructor)
                if ($oldUser['updated_at']) {
                    $user->setUpdatedAt(new \DateTime($oldUser['updated_at']));
                }

                // Campos adicionales del sistema antiguo
                $user->setIdStaff($oldUser['id_staff']);
                $user->setConcessions($oldUser['concessions']);
                $user->setApiToken($oldUser['api_token']);

                $this->entityManager->persist($user);
                $migrated++;

                // Hacer flush cada 50 registros para evitar problemas de memoria
                if ($migrated % 50 === 0) {
                    $this->entityManager->flush();
                }

            } catch (\Exception $e) {
                $errors++;
                $io->writeln(sprintf(
                    'Error migrando usuario %s: %s',
                    $oldUser['username'],
                    $e->getMessage()
                ), OutputInterface::VERBOSITY_VERBOSE);
            }
        }

        $io->progressFinish();

        // Flush final
        if (!$dryRun) {
            $this->entityManager->flush();
        }

        $io->newLine(2);
        
        if ($dryRun) {
            $io->success('Migración simulada completada:');
        } else {
            $io->success('Migración completada:');
        }

        $io->table(
            ['Resultado', 'Cantidad'],
            [
                ['Migrados', $migrated],
                ['Omitidos', $skipped],
                ['Errores', $errors],
                ['Total procesados', count($oldUsers)]
            ]
        );

        if (!$dryRun && $migrated > 0) {
            $io->note([
                'Los usuarios migrados tienen la contraseña original del sistema antiguo.',
                'Se recomienda que cambien su contraseña en el primer acceso.',
                'Todos los usuarios migrados tienen mustChangePassword = true.'
            ]);
        }

        return Command::SUCCESS;
    }

    private function mapRoles(?string $oldRole): array
    {
        if (!$oldRole) {
            return ['ROLE_USER'];
        }

        $roleMap = [
            'ROLE_ADMIN' => ['ROLE_ADMIN', 'ROLE_USER'],
            'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN', 'ROLE_USER'],
            'ROLE_USER' => ['ROLE_USER'],
            'ROLE_SUPERVISOR' => ['ROLE_MODERATOR', 'ROLE_USER'],
            'ROLE_FUEL_SUPPLY' => ['ROLE_USER'],
            'ROLE_RESPONSIBLE' => ['ROLE_MODERATOR', 'ROLE_USER'],
            'ROLE_ADMIN_COT' => ['ROLE_ADMIN', 'ROLE_USER'],
            'ROLE_OPERATOR_COT' => ['ROLE_USER'],
            'ROLE_SU_COT' => ['ROLE_ADMIN', 'ROLE_USER'],
            'ROLE_OPERATOR_SCADA' => ['ROLE_USER'],
            'ROLE_OPERATOR_PORTICO' => ['ROLE_USER'],
            'ROLE_OPERATOR_INCIDENTS' => ['ROLE_USER'],
            'ROLE_FISCAL_INSPECTOR' => ['ROLE_VIEWER', 'ROLE_USER'],
        ];

        return $roleMap[$oldRole] ?? ['ROLE_USER'];
    }
}