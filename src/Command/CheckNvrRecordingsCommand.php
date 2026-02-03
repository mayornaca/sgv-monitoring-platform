<?php

namespace App\Command;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Environment;

#[AsCommand(
    name: 'app:check-nvr-recordings',
    description: 'Verifica grabaciones NVR y envía alerta por email si hay discrepancias',
)]
class CheckNvrRecordingsCommand extends Command
{
    private const CACHE_KEY_CAMERAS = 'nvr_recording_alert_cameras';
    private const CACHE_TTL = 86400; // 24 horas
    private function getAlertEmails(): array
    {
        $emails = $_ENV['NVR_ALERT_EMAILS'] ?? 'sgv@gesvial.cl';
        return array_map('trim', explode(',', $emails));
    }

    private function getFromEmail(): string
    {
        return $_ENV['MAILER_FROM'] ?? 'sgv@gesvial.cl';
    }

    public function __construct(
        private ManagerRegistry $doctrine,
        private MailerInterface $mailer,
        private CacheInterface $cache,
        private Environment $twig,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'No enviar email, solo mostrar resultados')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Ignorar deduplicación y enviar siempre')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        try {
            // Obtener conexión default
            $conn = $this->doctrine->getConnection('default');

            // Ejecutar query de verificación NVR
            $sql = "
                SELECT
                    a.ip_servidor,
                    a.nombre_servidor,
                    a.id_camara,
                    a.ip_camara,
                    a.fecha,
                    a.bloque,
                    a.path_server,
                    a.q_archivos_esperados,
                    a.q_real,
                    a.updated_at,
                    b.nombre as nombre_camara,
                    b.descripcion as descripcion_camara,
                    ABS(a.q_archivos_esperados - a.q_real) as diferencia
                FROM tbl_cot_15_control_grabaciones_nvr_siv a
                LEFT JOIN tbl_cot_02_dispositivos b ON a.id_camara = b.id_externo AND b.id_tipo = 3
                WHERE ABS(a.q_archivos_esperados - a.q_real) > 2
                AND a.updated_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                ORDER BY a.bloque
            ";

            $result = $conn->executeQuery($sql);
            $cameras = $result->fetchAllAssociative();

            // Obtener set de cámaras previamente alertadas desde cache
            $lastCameraIds = $this->cache->get(self::CACHE_KEY_CAMERAS, function (ItemInterface $item) {
                $item->expiresAfter(self::CACHE_TTL);
                return [];
            });

            if (empty($cameras)) {
                // Sin discrepancias: limpiar cache para que futuros fallos alerten de nuevo
                if (!empty($lastCameraIds)) {
                    $this->cache->delete(self::CACHE_KEY_CAMERAS);
                    $this->logger->info('NVR check: todas las cámaras recuperadas, cache limpiado');
                }
                $io->success('Sin discrepancias en grabaciones NVR');
                return Command::SUCCESS;
            }

            $io->warning(sprintf('Encontradas %d cámara(s) con discrepancias', count($cameras)));

            // Obtener set actual de IDs de cámaras afectadas
            $currentCameraIds = array_unique(array_map(fn($c) => (int) $c['id_camara'], $cameras));
            sort($currentCameraIds);

            // Comparar sets: enviar solo si el set de cámaras cambió
            $lastSet = $lastCameraIds;
            sort($lastSet);

            if (!$force && $currentCameraIds === $lastSet) {
                $io->note('Set de cámaras sin cambios, no se envía alerta');
                $this->logger->info('NVR check: mismo set de cámaras, no se envía alerta', [
                    'camaras' => $currentCameraIds
                ]);
                return Command::SUCCESS;
            }

            // Mostrar resumen
            $io->table(
                ['Cámara', 'IP', 'Bloque', 'Esperados', 'Reales', 'Diferencia'],
                array_map(fn($c) => [
                    $c['nombre_camara'] ?? $c['id_camara'],
                    $c['ip_camara'] ?? '-',
                    $c['bloque'],
                    $c['q_archivos_esperados'],
                    $c['q_real'],
                    $c['diferencia']
                ], $cameras)
            );

            if ($dryRun) {
                $io->note('Modo dry-run: no se envía email');
                return Command::SUCCESS;
            }

            // Enviar email
            $htmlContent = $this->twig->render('emails/nvr_recording_alert.html.twig', [
                'cameras' => $cameras,
                'timestamp' => new \DateTime('now', new \DateTimeZone('America/Santiago')),
                'total' => count($cameras)
            ]);

            $email = (new Email())
                ->from($this->getFromEmail())
                ->to(...$this->getAlertEmails())
                ->subject(sprintf('[ALERTA NVR] %d cámara(s) con discrepancias de grabación', count($cameras)))
                ->html($htmlContent);

            $this->mailer->send($email);

            // Guardar set de cámaras en cache después de enviar exitosamente
            $this->cache->delete(self::CACHE_KEY_CAMERAS);
            $this->cache->get(self::CACHE_KEY_CAMERAS, function (ItemInterface $item) use ($currentCameraIds) {
                $item->expiresAfter(self::CACHE_TTL);
                return $currentCameraIds;
            });

            $recipients = implode(', ', $this->getAlertEmails());
            $io->success(sprintf('Email enviado a %s', $recipients));
            $this->logger->info('NVR check: alerta enviada', [
                'camaras_afectadas' => count($cameras),
                'camaras_ids' => $currentCameraIds,
                'camaras_previas' => $lastCameraIds,
                'destinatarios' => $this->getAlertEmails()
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            $this->logger->error('NVR check error: ' . $e->getMessage(), [
                'exception' => $e
            ]);
            return Command::FAILURE;
        }
    }
}
