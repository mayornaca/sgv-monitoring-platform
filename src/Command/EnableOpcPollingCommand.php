<?php

namespace App\Command;

use App\Repository\DeviceTypeRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Activa el flag consultar=1 para tipos de dispositivo con metodo_monitoreo=3 (OPC Daemon).
 *
 * El servicio OPC Daemon externo lee este flag para decidir qué dispositivos pollear.
 * Este comando actúa como safety net complementario a la activación inmediata que
 * ocurre cuando un usuario accede a las vistas COT (indexAction, vsIndexAction).
 *
 * Uso recomendado en crontab: * /5 * * * * php bin/console app:cot:enable-opc-polling
 */
#[AsCommand(
    name: 'app:cot:enable-opc-polling',
    description: 'Activa flag consultar=1 para dispositivos OPC Daemon (metodo_monitoreo=3)',
)]
class EnableOpcPollingCommand extends Command
{
    public function __construct(
        private DeviceTypeRepository $deviceTypeRepository,
        private LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('concesionaria', 'c', InputOption::VALUE_OPTIONAL, 'ID de concesionaria (sin este flag activa todas)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $concesionaria = $input->getOption('concesionaria');

        if ($concesionaria !== null) {
            $concesionaria = (int) $concesionaria;
        }

        try {
            $count = $this->deviceTypeRepository->enableOpcPolling($concesionaria);

            $scope = $concesionaria !== null ? "concesionaria=$concesionaria" : 'todas las concesionarias';
            $message = "OPC polling activado: $count tipo(s) de dispositivo actualizados ($scope)";

            $io->success($message);
            $this->logger->info($message);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error activando OPC polling: ' . $e->getMessage());
            $this->logger->error('Error en enable-opc-polling: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
