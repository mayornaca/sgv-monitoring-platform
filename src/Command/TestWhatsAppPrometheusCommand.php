<?php

namespace App\Command;

use App\Repository\WhatsApp\RecipientGroupRepository;
use App\Repository\WhatsApp\TemplateRepository;
use App\Service\WhatsAppNotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-whatsapp-prometheus',
    description: 'Envía mensaje de prueba de WhatsApp simulando alerta de Prometheus'
)]
class TestWhatsAppPrometheusCommand extends Command
{
    public function __construct(
        private WhatsAppNotificationService $whatsAppService,
        private TemplateRepository $templateRepository,
        private RecipientGroupRepository $groupRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('alert-name', null, InputOption::VALUE_OPTIONAL, 'Nombre de la alerta', 'TestAlert')
            ->addOption('severity', null, InputOption::VALUE_OPTIONAL, 'Severidad', 'critical')
            ->addOption('summary', null, InputOption::VALUE_OPTIONAL, 'Resumen de la alerta', 'Prueba de alerta WhatsApp desde comando CLI')
            ->addOption('instance', null, InputOption::VALUE_OPTIONAL, 'Instancia afectada', 'test-server-01')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Prueba de envío de alerta WhatsApp (Prometheus)');

        // Obtener parámetros
        $alertName = $input->getOption('alert-name');
        $severity = $input->getOption('severity');
        $summary = $input->getOption('summary');
        $instance = $input->getOption('instance');

        // Buscar template
        $io->section('Paso 1: Verificando configuración');
        $template = $this->templateRepository->findOneBy(['metaTemplateId' => 'prometheus_alert_firing']);

        if (!$template) {
            $io->error('Template "prometheus_alert_firing" no encontrado en la base de datos');
            $io->note('Verifica que el template exista en EasyAdmin (Sección WhatsApp > Templates)');
            return Command::FAILURE;
        }

        $io->success(sprintf('✓ Template encontrado: %s', $template->getNombre()));
        $io->writeln(sprintf('  Meta Template ID: %s', $template->getMetaTemplateId()));
        $io->writeln(sprintf('  Parámetros requeridos: %d', $template->getParametrosCount()));
        $io->writeln(sprintf('  Activo: %s', $template->isActivo() ? 'Sí' : 'No'));

        // Buscar grupo
        $group = $this->groupRepository->findOneBy(['slug' => 'prometheus_alerts']);

        if (!$group) {
            $io->error('Grupo "prometheus_alerts" no encontrado en la base de datos');
            $io->note('Verifica que el grupo exista en EasyAdmin (Sección WhatsApp > Grupos de Destinatarios)');
            return Command::FAILURE;
        }

        $io->success(sprintf('✓ Grupo encontrado: %s', $group->getNombre()));
        $io->writeln(sprintf('  Slug: %s', $group->getSlug()));
        $io->writeln(sprintf('  Activo: %s', $group->isActivo() ? 'Sí' : 'No'));

        // Obtener destinatarios activos
        $recipients = $group->getActiveRecipients();
        $io->writeln(sprintf('  Destinatarios activos: %d', count($recipients)));

        if (empty($recipients)) {
            $io->error('El grupo no tiene destinatarios activos');
            $io->note('Agrega destinatarios al grupo en EasyAdmin');
            return Command::FAILURE;
        }

        $io->listing(array_map(function($r) {
            return sprintf('%s (%s)', $r->getNombre(), $r->getTelefono());
        }, $recipients));

        // Confirmar envío
        $io->section('Paso 2: Preparando mensaje');

        $parameters = [$alertName, $severity, $summary, $instance];

        $io->definitionList(
            ['Parámetro 1 (alertname)' => $parameters[0]],
            ['Parámetro 2 (severity)' => $parameters[1]],
            ['Parámetro 3 (summary)' => $parameters[2]],
            ['Parámetro 4 (instance)' => $parameters[3]]
        );

        if (!$io->confirm(sprintf('¿Enviar mensaje de prueba a %d destinatario(s)?', count($recipients)), true)) {
            $io->info('Operación cancelada');
            return Command::SUCCESS;
        }

        // Enviar mensaje
        $io->section('Paso 3: Enviando mensaje');
        $io->writeln('Esto puede tomar unos segundos (sleep de 1s entre destinatarios)...');
        $io->newLine();

        try {
            $startTime = microtime(true);

            $messages = $this->whatsAppService->sendTemplateMessage(
                $template,
                $parameters,
                $group,
                'test_prometheus_cli'
            );

            $duration = round(microtime(true) - $startTime, 2);

            $io->success(sprintf('✓ Mensajes enviados exitosamente en %s segundos', $duration));

            $io->table(
                ['ID', 'Destinatario', 'Teléfono', 'Estado', 'Meta Message ID'],
                array_map(function($msg) {
                    return [
                        $msg->getId(),
                        $msg->getRecipient()->getNombre(),
                        $msg->getRecipient()->getTelefono(),
                        $msg->getEstado(),
                        $msg->getMetaMessageId() ?? 'N/A'
                    ];
                }, $messages)
            );

            $io->section('Próximos pasos');
            $io->listing([
                'Verifica que recibiste el mensaje en WhatsApp',
                'Revisa el estado en EasyAdmin: /admin → Mensajes WhatsApp',
                'Si configuraste el webhook correctamente, el estado se actualizará a "delivered" automáticamente',
                'Puedes ver los logs con: tail -f var/log/prod.log | grep -i whatsapp'
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Error al enviar mensajes: ' . $e->getMessage());
            $io->writeln('Detalles del error:');
            $io->writeln($e->getTraceAsString());

            $io->section('Posibles causas');
            $io->listing([
                'Token de acceso de Meta inválido o expirado',
                'Phone Number ID incorrecto',
                'Template no aprobado en Meta Business Manager',
                'Número de teléfono no registrado en WhatsApp',
                'Error de conectividad con graph.facebook.com'
            ]);

            return Command::FAILURE;
        }
    }
}
