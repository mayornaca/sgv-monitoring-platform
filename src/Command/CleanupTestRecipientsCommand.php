<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-test-recipients',
    description: 'Limpia recipients y grupos de prueba duplicados creados desde el dashboard'
)]
class CleanupTestRecipientsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Limpieza de Destinatarios y Grupos de Prueba');

        // Contar antes de limpiar
        $testRecipients = $this->entityManager->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from('App\Entity\WhatsApp\Recipient', 'r')
            ->where('r.nombre = :nombre')
            ->setParameter('nombre', 'Test desde Dashboard')
            ->getQuery()
            ->getSingleScalarResult();

        $testGroups = $this->entityManager->createQueryBuilder()
            ->select('COUNT(g.id)')
            ->from('App\Entity\WhatsApp\RecipientGroup', 'g')
            ->where('g.slug LIKE :pattern')
            ->setParameter('pattern', 'test_group_%')
            ->getQuery()
            ->getSingleScalarResult();

        $io->section('Estado actual');
        $io->listing([
            "Recipients de prueba: {$testRecipients}",
            "Grupos de prueba: {$testGroups}"
        ]);

        if ($testRecipients === 0 && $testGroups === 0) {
            $io->success('No hay datos de prueba para limpiar');
            return Command::SUCCESS;
        }

        if (!$io->confirm('¿Desea proceder con la limpieza?', false)) {
            $io->warning('Operación cancelada');
            return Command::SUCCESS;
        }

        // Eliminar grupos de prueba temporales
        $io->section('Eliminando grupos de prueba temporales...');
        $deletedGroups = $this->entityManager->createQueryBuilder()
            ->delete('App\Entity\WhatsApp\RecipientGroup', 'g')
            ->where('g.slug LIKE :pattern')
            ->setParameter('pattern', 'test_group_%')
            ->getQuery()
            ->execute();

        $io->success(sprintf('Eliminados %d grupos de prueba', $deletedGroups));

        // Eliminar recipients "Test desde Dashboard" que NO tienen mensajes
        $io->section('Eliminando recipients de prueba sin mensajes...');

        $conn = $this->entityManager->getConnection();
        $sql = 'DELETE r FROM whatsapp_recipients r
                LEFT JOIN whatsapp_messages m ON m.recipient_id = r.id
                WHERE r.nombre = :nombre AND m.id IS NULL';

        $deletedRecipients = $conn->executeStatement($sql, ['nombre' => 'Test desde Dashboard']);

        $io->success(sprintf('Eliminados %d recipients de prueba sin mensajes', $deletedRecipients));

        // Contar recipients de prueba que aún tienen mensajes
        $remainingWithMessages = $this->entityManager->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from('App\Entity\WhatsApp\Recipient', 'r')
            ->where('r.nombre = :nombre')
            ->setParameter('nombre', 'Test desde Dashboard')
            ->getQuery()
            ->getSingleScalarResult();

        if ($remainingWithMessages > 0) {
            $io->warning(sprintf(
                '%d recipients de prueba tienen mensajes asociados y no fueron eliminados',
                $remainingWithMessages
            ));
            $io->note('Use la consolidación de mensajes si desea eliminar estos recipients');
        }

        $io->success('Limpieza completada');

        return Command::SUCCESS;
    }
}