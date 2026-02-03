<?php

namespace App\Command;

use App\Entity\AppSetting;
use App\Service\ConfigurationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:config:migrate-env',
    description: 'Migra configuraciones desde .env a la base de datos'
)]
class MigrateEnvToDbCommand extends Command
{
    private const CONFIG_MAPPINGS = [
        // WhatsApp
        'whatsapp.primary.token' => [
            'env' => 'WHATSAPP_PRIMARY_TOKEN',
            'type' => AppSetting::TYPE_ENCRYPTED,
            'category' => AppSetting::CATEGORY_WHATSAPP,
            'description' => 'Token de acceso para número principal de WhatsApp',
        ],
        'whatsapp.primary.phone_id' => [
            'env' => 'WHATSAPP_PRIMARY_PHONE_ID',
            'type' => AppSetting::TYPE_STRING,
            'category' => AppSetting::CATEGORY_WHATSAPP,
            'description' => 'Phone Number ID del número principal',
        ],
        'whatsapp.backup.token' => [
            'env' => 'WHATSAPP_BACKUP_TOKEN',
            'type' => AppSetting::TYPE_ENCRYPTED,
            'category' => AppSetting::CATEGORY_WHATSAPP,
            'description' => 'Token de acceso para número backup de WhatsApp',
        ],
        'whatsapp.backup.phone_id' => [
            'env' => 'WHATSAPP_BACKUP_PHONE_ID',
            'type' => AppSetting::TYPE_STRING,
            'category' => AppSetting::CATEGORY_WHATSAPP,
            'description' => 'Phone Number ID del número backup',
        ],
        'whatsapp.failover_threshold' => [
            'env' => 'WHATSAPP_FAILOVER_THRESHOLD',
            'type' => AppSetting::TYPE_INTEGER,
            'category' => AppSetting::CATEGORY_WHATSAPP,
            'description' => 'Número de reintentos antes de cambiar a número backup',
        ],
        'whatsapp.max_retries' => [
            'env' => 'WHATSAPP_MAX_RETRIES',
            'type' => AppSetting::TYPE_INTEGER,
            'category' => AppSetting::CATEGORY_WHATSAPP,
            'description' => 'Número máximo de reintentos para envío de mensajes',
        ],
        'whatsapp.webhook_verify_token' => [
            'env' => 'WHATSAPP_WEBHOOK_VERIFY_TOKEN',
            'type' => AppSetting::TYPE_ENCRYPTED,
            'category' => AppSetting::CATEGORY_WHATSAPP,
            'description' => 'Token de verificación para webhook de WhatsApp',
        ],

        // Email/SMTP
        'mailer.dsn' => [
            'env' => 'MAILER_DSN',
            'type' => AppSetting::TYPE_ENCRYPTED,
            'category' => AppSetting::CATEGORY_EMAIL,
            'description' => 'DSN de configuración SMTP para envío de emails',
        ],

        // Seguridad
        'security.cron_auth_token' => [
            'env' => 'CRON_AUTH_TOKEN',
            'type' => AppSetting::TYPE_ENCRYPTED,
            'category' => AppSetting::CATEGORY_SECURITY,
            'description' => 'Token de autenticación para endpoints de cronjobs',
        ],

        // Sistema
        'system.timezone' => [
            'env' => 'LOCALTIMEZONE',
            'type' => AppSetting::TYPE_STRING,
            'category' => AppSetting::CATEGORY_SYSTEM,
            'description' => 'Zona horaria del sistema',
            'param' => 'localtimezone',
        ],
        'system.locale' => [
            'env' => 'LOCALE',
            'type' => AppSetting::TYPE_STRING,
            'category' => AppSetting::CATEGORY_SYSTEM,
            'description' => 'Idioma/locale del sistema',
            'param' => 'locale',
        ],
    ];

    public function __construct(
        private readonly ConfigurationService $configService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Sobrescribir valores existentes en BD')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simular migración sin guardar en BD')
            ->setHelp(<<<'HELP'
Este comando migra configuraciones desde variables de entorno (.env) a la base de datos.

Uso:
  # Simular migración (no guarda)
  php bin/console app:config:migrate-env --dry-run

  # Migrar solo valores que no existen en BD
  php bin/console app:config:migrate-env

  # Forzar migración sobrescribiendo valores existentes
  php bin/console app:config:migrate-env --force

Las configuraciones migradas incluyen:
- Credenciales de WhatsApp (tokens, phone IDs)
- Configuración SMTP/Email
- Tokens de seguridad
- Configuración del sistema
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $io->warning('Modo DRY-RUN activado - No se guardarán cambios en la base de datos');
        }

        $io->title('Migración de Configuraciones: .env → Base de Datos');

        $migrated = 0;
        $skipped = 0;
        $errors = 0;

        foreach (self::CONFIG_MAPPINGS as $key => $config) {
            $envKey = $config['env'];
            $envValue = $_ENV[$envKey] ?? getenv($envKey);

            // Si la variable de entorno no existe, continuar
            if ($envValue === false || $envValue === null || $envValue === '') {
                $io->text("⏭️  <comment>{$key}</comment>: No definida en .env");
                $skipped++;
                continue;
            }

            // Verificar si ya existe en BD
            $exists = $this->configService->has($key);

            if ($exists && !$force) {
                $io->text("⏭️  <comment>{$key}</comment>: Ya existe en BD (usa --force para sobrescribir)");
                $skipped++;
                continue;
            }

            try {
                // Parsear DSN de WhatsApp si es necesario
                if (str_contains($key, 'whatsapp') && str_contains($envValue, 'meta-whatsapp://')) {
                    $parsedValue = $this->parseWhatsAppDsn($envValue, $key);
                    if ($parsedValue === null) {
                        $io->text("⚠️  <comment>{$key}</comment>: No se pudo parsear DSN");
                        $skipped++;
                        continue;
                    }
                    $envValue = $parsedValue;
                }

                if (!$dryRun) {
                    $this->configService->set(
                        $key,
                        $envValue,
                        $config['type'],
                        $config['category']
                    );

                    // Actualizar descripción si existe
                    if (isset($config['description'])) {
                        $setting = $this->configService->get($key);
                        // La descripción se debe establecer a nivel de entidad
                    }
                }

                $action = $exists ? 'Actualizada' : 'Creada';
                $valuePreview = $this->getValuePreview($envValue, $config['type']);
                $io->text("✅ <info>{$key}</info> ({$action}): {$valuePreview}");
                $migrated++;

            } catch (\Exception $e) {
                $io->error("Error al migrar {$key}: " . $e->getMessage());
                $errors++;
            }
        }

        $io->newLine();
        $io->section('Resumen de Migración');

        $io->definitionList(
            ['Configuraciones migradas' => $migrated],
            ['Configuraciones omitidas' => $skipped],
            ['Errores' => $errors],
        );

        if ($dryRun) {
            $io->note('Esto fue una simulación. Ejecuta sin --dry-run para guardar los cambios.');
        } elseif ($migrated > 0) {
            $io->success([
                'Migración completada exitosamente',
                "Se migraron {$migrated} configuraciones a la base de datos",
                'Ahora puedes gestionar estas configuraciones desde EasyAdmin en Admin → Configuraciones'
            ]);
        } else {
            $io->warning('No se migró ninguna configuración nueva');
        }

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function parseWhatsAppDsn(string $dsn, string $key): ?string
    {
        // Extraer token y phone_id del DSN
        // Formato: meta-whatsapp://TOKEN@default?phone_number_id=PHONE_ID
        if (!preg_match('/meta-whatsapp:\/\/([^@]+)@default\?phone_number_id=(\d+)/', $dsn, $matches)) {
            return null;
        }

        if (str_contains($key, 'token')) {
            return $matches[1]; // Retornar solo el token
        } elseif (str_contains($key, 'phone_id')) {
            return $matches[2]; // Retornar solo el phone_id
        }

        return null;
    }

    private function getValuePreview(string $value, string $type): string
    {
        if ($type === AppSetting::TYPE_ENCRYPTED) {
            $length = strlen($value);
            return "****** ({$length} caracteres)";
        }

        if (strlen($value) > 50) {
            return substr($value, 0, 47) . '...';
        }

        return $value;
    }
}
