<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Tbl14Personal;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

#[AsCommand(
    name: 'app:assign-staff',
    description: 'Asigna personal (id_staff) a usuarios que no lo tienen',
)]
class AssignStaffToUsersCommand extends Command
{
    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Solo muestra lo que se haría sin ejecutar cambios')
            ->addOption('auto-only', 'a', InputOption::VALUE_NONE, 'Solo asignar matches automáticos (por email)')
            ->addOption('show-unmatched', 'u', InputOption::VALUE_NONE, 'Mostrar usuarios sin match automático')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $autoOnly = $input->getOption('auto-only');
        $showUnmatched = $input->getOption('show-unmatched');

        if ($dryRun) {
            $io->warning('Modo DRY-RUN activado - No se guardarán cambios');
        }

        $io->title('Asignación de Personal a Usuarios');

        // 1. Obtener usuarios sin id_staff
        $usersWithoutStaff = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.idStaff IS NULL')
            ->getQuery()
            ->getResult();

        $totalWithoutStaff = count($usersWithoutStaff);

        if ($totalWithoutStaff === 0) {
            $io->success('Todos los usuarios ya tienen personal asociado');
            return Command::SUCCESS;
        }

        $io->section(sprintf('Usuarios sin personal asociado: %d', $totalWithoutStaff));

        // 2. Buscar matches automáticos por email
        $io->text('Buscando matches automáticos por email...');
        $autoMatches = $this->findAutoMatchesByEmail();

        $io->success(sprintf('Encontrados %d matches automáticos por email', count($autoMatches)));

        // 3. Mostrar tabla de matches
        if (!empty($autoMatches)) {
            $table = new Table($output);
            $table->setHeaders(['User ID', 'Username', 'Email', 'Personal ID', 'Nombre Personal', 'RUT']);

            foreach (array_slice($autoMatches, 0, 10) as $match) {
                $table->addRow([
                    $match['user_id'],
                    $match['username'],
                    substr($match['email'], 0, 30),
                    $match['id_personal'],
                    substr($match['nombre_personal'], 0, 30),
                    $match['rut']
                ]);
            }

            if (count($autoMatches) > 10) {
                $table->addRow(['...', '...', '...', '...', '...', '...']);
                $table->addRow([
                    'Total',
                    count($autoMatches) . ' usuarios',
                    '',
                    '',
                    '',
                    ''
                ]);
            }

            $table->render();
        }

        // 4. Confirmar antes de proceder (solo si no es dry-run ni auto-only)
        if (!$dryRun && !$autoOnly) {
            if (!$io->confirm(sprintf('¿Proceder con la asignación de %d usuarios?', count($autoMatches)), true)) {
                $io->warning('Operación cancelada');
                return Command::SUCCESS;
            }
        }

        // 5. Realizar asignaciones automáticas
        $io->section('Asignando personal a usuarios...');
        $io->progressStart(count($autoMatches));

        $assigned = 0;
        $errors = [];

        foreach ($autoMatches as $match) {
            try {
                $user = $this->entityManager->getRepository(User::class)->find($match['user_id']);
                $personal = $this->entityManager->getRepository(Tbl14Personal::class)->find($match['id_personal']);

                if (!$user || !$personal) {
                    $errors[] = sprintf('Usuario ID %d o Personal ID %d no encontrado',
                        $match['user_id'], $match['id_personal']);
                    continue;
                }

                if (!$dryRun) {
                    $user->setIdStaff($personal);
                    $this->entityManager->persist($user);

                    // Flush cada 20 registros
                    if (($assigned + 1) % 20 === 0) {
                        $this->entityManager->flush();
                    }
                }

                $assigned++;
            } catch (\Exception $e) {
                $errors[] = sprintf('Error asignando usuario %s: %s',
                    $match['username'], $e->getMessage());
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        // Flush final
        if (!$dryRun && $assigned > 0) {
            $this->entityManager->flush();
        }

        $io->newLine();

        // 6. Resultados
        if ($dryRun) {
            $io->info(sprintf('DRY-RUN: Se asignarían %d usuarios', $assigned));
        } else {
            $io->success(sprintf('Asignación completada: %d usuarios procesados', $assigned));
        }

        if (!empty($errors)) {
            $io->warning(sprintf('Errores durante la asignación: %d', count($errors)));
            foreach ($errors as $error) {
                $io->writeln('  - ' . $error);
            }
        }

        // 7. Usuarios sin match automático
        $remainingWithoutStaff = $totalWithoutStaff - $assigned;

        if ($remainingWithoutStaff > 0 || $showUnmatched) {
            $io->section(sprintf('Usuarios sin match automático: %d', $remainingWithoutStaff));

            $unmatched = $this->findUnmatchedUsers();

            if (!empty($unmatched) && ($showUnmatched || $remainingWithoutStaff <= 20)) {
                $table = new Table($output);
                $table->setHeaders(['User ID', 'Username', 'Nombre Completo', 'Email']);

                foreach ($unmatched as $user) {
                    $table->addRow([
                        $user['id'],
                        $user['username'],
                        $user['first_name'] . ' ' . $user['last_name'],
                        $user['email']
                    ]);
                }

                $table->render();

                $io->note([
                    'Estos usuarios necesitan asignación manual.',
                    'Puede editarlos en: https://vs.gvops.cl/admin/user/{id}/edit'
                ]);
            }
        }

        // 8. Estadísticas finales
        $this->showFinalStatistics($io, $dryRun);

        return empty($errors) ? Command::SUCCESS : Command::FAILURE;
    }

    private function findAutoMatchesByEmail(): array
    {
        // Solo tomar el primer match por usuario (el id_personal más alto = más reciente)
        $sql = "
            SELECT
                matches.user_id,
                matches.username,
                matches.email,
                matches.id_personal,
                matches.nombre_personal,
                matches.correo_electronico,
                matches.rut
            FROM (
                SELECT
                    u.id as user_id,
                    u.username,
                    u.email,
                    p.id_personal,
                    CONCAT(p.nombres, ' ', p.apellidos) as nombre_personal,
                    p.correo_electronico,
                    p.rut,
                    ROW_NUMBER() OVER (PARTITION BY u.id ORDER BY p.id_personal DESC) as rn
                FROM security_user u
                INNER JOIN tbl_14_personal p ON
                    LOWER(TRIM(u.email)) COLLATE utf8mb4_unicode_ci = LOWER(TRIM(p.correo_electronico)) COLLATE utf8mb4_unicode_ci
                WHERE u.id_staff IS NULL
                AND u.email IS NOT NULL
                AND p.correo_electronico IS NOT NULL
                AND p.reg_status = 1
            ) as matches
            WHERE matches.rn = 1
            ORDER BY matches.username
        ";

        return $this->connection->fetchAllAssociative($sql);
    }

    private function findUnmatchedUsers(): array
    {
        $sql = "
            SELECT
                u.id,
                u.username,
                u.first_name,
                u.last_name,
                u.email
            FROM security_user u
            WHERE u.id_staff IS NULL
            AND u.id NOT IN (
                SELECT u2.id
                FROM security_user u2
                INNER JOIN tbl_14_personal p ON
                    LOWER(TRIM(u2.email)) COLLATE utf8mb4_unicode_ci = LOWER(TRIM(p.correo_electronico)) COLLATE utf8mb4_unicode_ci
                WHERE u2.id_staff IS NULL
                AND u2.email IS NOT NULL
                AND p.correo_electronico IS NOT NULL
                AND p.reg_status = 1
            )
            ORDER BY u.username
        ";

        return $this->connection->fetchAllAssociative($sql);
    }

    private function showFinalStatistics(SymfonyStyle $io, bool $dryRun): void
    {
        $stats = $this->connection->fetchAssociative("
            SELECT
                (SELECT COUNT(*) FROM security_user) as total_users,
                (SELECT COUNT(*) FROM security_user WHERE id_staff IS NOT NULL) as with_staff,
                (SELECT COUNT(*) FROM security_user WHERE id_staff IS NULL) as without_staff
        ");

        $io->section('Estadísticas Finales');

        $table = new Table($io);
        $table->setHeaders(['Categoría', 'Cantidad']);
        $table->addRows([
            ['Total usuarios', $stats['total_users']],
            ['Con personal asociado', $stats['with_staff']],
            ['Sin personal asociado', $stats['without_staff']],
        ]);
        $table->render();

        if ($stats['without_staff'] == 0) {
            $io->success('¡Todos los usuarios tienen personal asociado!');
        }
    }
}
