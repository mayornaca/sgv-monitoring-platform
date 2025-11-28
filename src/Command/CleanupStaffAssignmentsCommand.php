<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

#[AsCommand(
    name: 'app:cleanup-staff',
    description: 'Limpia asignaciones de id_staff donde el nombre del usuario no coincide con el personal',
)]
class CleanupStaffAssignmentsCommand extends Command
{
    // Lista de IDs de usuarios con asignaciones problemáticas
    private const PROBLEMATIC_USER_IDS = [
        17,  // amunoz (Andrea → Fanny Bodero)
        52,  // aoyanedel (Anibal → Jose Aralla)
        20,  // candrade (Carolina → Veronica Cacerez)
        43,  // electronicos (Electrónicos → Elias Cantero)
        47,  // hcastro (Holmert → jose barrera)
        50,  // mcorvalan (Mauricio → Esteban Gutierrez)
        4,   // miguel.farias (revisar)
        51,  // mmendez (Marcelo → Richard Echeverria)
        15,  // pruebas (Prueba → prueba NNN NNN)
    ];

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Solo muestra lo que se haría sin ejecutar cambios')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Ejecutar sin confirmación')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        if ($dryRun) {
            $io->warning('Modo DRY-RUN activado - No se guardarán cambios');
        }

        $io->title('Limpieza de Asignaciones de Personal Problemáticas');

        // 1. Obtener usuarios problemáticos
        $problematicUsers = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.id IN (:ids)')
            ->andWhere('u.idStaff IS NOT NULL')
            ->setParameter('ids', self::PROBLEMATIC_USER_IDS)
            ->getQuery()
            ->getResult();

        $totalToClean = count($problematicUsers);

        if ($totalToClean === 0) {
            $io->success('No hay asignaciones problemáticas que limpiar');
            return Command::SUCCESS;
        }

        $io->section(sprintf('Asignaciones problemáticas encontradas: %d', $totalToClean));

        // 2. Mostrar tabla de usuarios a limpiar
        $table = new Table($output);
        $table->setHeaders(['User ID', 'Username', 'Nombre Usuario', 'Personal Actual', 'Email']);

        foreach ($problematicUsers as $user) {
            $staff = $user->getIdStaff();
            $staffName = $staff ? $staff->getNombres() . ' ' . $staff->getApellidos() : 'N/A';

            $table->addRow([
                $user->getId(),
                $user->getUsername(),
                $user->getFirstName() . ' ' . $user->getLastName(),
                $staffName,
                $user->getEmail()
            ]);
        }

        $table->render();

        // 3. Confirmar antes de proceder
        if (!$dryRun && !$force) {
            $io->warning([
                'Esta operación eliminará la asignación de id_staff para estos usuarios.',
                'Los usuarios podrán seguir accediendo al sistema, pero sin personal asociado.'
            ]);

            if (!$io->confirm(sprintf('¿Proceder con la limpieza de %d usuarios?', $totalToClean), false)) {
                $io->info('Operación cancelada');
                return Command::SUCCESS;
            }
        }

        // 4. Realizar limpieza
        $io->section('Limpiando asignaciones...');
        $io->progressStart($totalToClean);

        $cleaned = 0;
        $errors = [];

        foreach ($problematicUsers as $user) {
            try {
                $oldStaff = $user->getIdStaff();
                $oldStaffName = $oldStaff ? $oldStaff->getNombres() . ' ' . $oldStaff->getApellidos() : 'N/A';

                if (!$dryRun) {
                    $user->setIdStaff(null);
                    $this->entityManager->persist($user);
                }

                $cleaned++;

                if ($dryRun) {
                    $io->writeln(sprintf(
                        '  [DRY-RUN] Limpiaría usuario %s (ID: %d) - Personal: %s',
                        $user->getUsername(),
                        $user->getId(),
                        $oldStaffName
                    ), OutputInterface::VERBOSITY_VERBOSE);
                }

            } catch (\Exception $e) {
                $errors[] = sprintf('Error limpiando usuario %s (ID: %d): %s',
                    $user->getUsername(),
                    $user->getId(),
                    $e->getMessage()
                );
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        // Flush final
        if (!$dryRun && $cleaned > 0) {
            $this->entityManager->flush();
        }

        $io->newLine();

        // 5. Resultados
        if ($dryRun) {
            $io->info(sprintf('DRY-RUN: Se limpiarían %d asignaciones', $cleaned));
        } else {
            $io->success(sprintf('Limpieza completada: %d asignaciones eliminadas', $cleaned));
        }

        if (!empty($errors)) {
            $io->warning(sprintf('Errores durante la limpieza: %d', count($errors)));
            foreach ($errors as $error) {
                $io->writeln('  - ' . $error);
            }
        }

        // 6. Mostrar estadísticas finales
        if (!$dryRun) {
            $this->showFinalStatistics($io);
        }

        return empty($errors) ? Command::SUCCESS : Command::FAILURE;
    }

    private function showFinalStatistics(SymfonyStyle $io): void
    {
        $totalUsers = $this->entityManager->getRepository(User::class)
            ->count([]);

        $usersWithStaff = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.idStaff IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $usersWithoutStaff = $totalUsers - $usersWithStaff;

        $io->section('Estadísticas Finales');

        $table = new Table($io);
        $table->setHeaders(['Categoría', 'Cantidad']);
        $table->addRows([
            ['Total usuarios', $totalUsers],
            ['Con personal asociado', $usersWithStaff],
            ['Sin personal asociado', $usersWithoutStaff],
        ]);
        $table->render();

        // Verificar asignaciones correctas
        $correctAssignments = $this->verifyCorrectAssignments();

        if ($correctAssignments['total'] > 0) {
            $io->success(sprintf(
                'Verificación: %d/%d asignaciones restantes tienen nombres coincidentes',
                $correctAssignments['correct'],
                $correctAssignments['total']
            ));

            if ($correctAssignments['incorrect'] > 0) {
                $io->warning(sprintf(
                    'Aún hay %d asignaciones con nombres no coincidentes',
                    $correctAssignments['incorrect']
                ));
            }
        }
    }

    private function verifyCorrectAssignments(): array
    {
        $usersWithStaff = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.idStaff IS NOT NULL')
            ->getQuery()
            ->getResult();

        $total = count($usersWithStaff);
        $correct = 0;
        $incorrect = 0;

        foreach ($usersWithStaff as $user) {
            $staff = $user->getIdStaff();
            if (!$staff) {
                continue;
            }

            $userName = strtolower($user->getFirstName() . ' ' . $user->getLastName());
            $staffName = strtolower($staff->getNombres() . ' ' . $staff->getApellidos());

            // Verificar si los nombres son similares
            $isSimilar = (
                stripos($userName, strtolower($staff->getNombres())) !== false ||
                stripos($userName, strtolower($staff->getApellidos())) !== false ||
                stripos($staffName, strtolower($user->getFirstName())) !== false ||
                stripos($staffName, strtolower($user->getLastName())) !== false ||
                $userName === $staffName
            );

            if ($isSimilar) {
                $correct++;
            } else {
                $incorrect++;
            }
        }

        return [
            'total' => $total,
            'correct' => $correct,
            'incorrect' => $incorrect
        ];
    }
}
