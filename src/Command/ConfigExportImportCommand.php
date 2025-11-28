<?php

namespace App\Command;

use App\Entity\AppSetting;
use App\Repository\AppSettingRepository;
use App\Service\ConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:config:export-import',
    description: 'Exporta o importa configuraciones del sistema para portabilidad entre entornos'
)]
class ConfigExportImportCommand extends Command
{
    public function __construct(
        private readonly AppSettingRepository $settingRepository,
        private readonly ConfigurationService $configService,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Acción: export o import')
            ->addArgument('file', InputArgument::OPTIONAL, 'Ruta del archivo JSON', 'config/settings-export.json')
            ->addOption('category', 'c', InputOption::VALUE_REQUIRED, 'Exportar/importar solo una categoría específica')
            ->addOption('include-encrypted', null, InputOption::VALUE_NONE, 'Incluir valores encriptados en la exportación (ADVERTENCIA: sensible)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Sobrescribir valores existentes al importar')
            ->setHelp(<<<'HELP'
Exporta configuraciones a JSON o importa desde JSON para portabilidad entre entornos.

Exportar todas las configuraciones:
  php bin/console app:config:export-import export

Exportar solo configuraciones de WhatsApp:
  php bin/console app:config:export-import export --category=whatsapp

Exportar incluyendo valores encriptados (CUIDADO):
  php bin/console app:config:export-import export --include-encrypted

Importar configuraciones:
  php bin/console app:config:export-import import

Importar forzando sobrescritura:
  php bin/console app:config:export-import import --force

ADVERTENCIA: Exportar con --include-encrypted expone secretos. Solo usar en entornos seguros.
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');
        $file = $input->getArgument('file');
        $category = $input->getOption('category');
        $includeEncrypted = $input->getOption('include-encrypted');
        $force = $input->getOption('force');

        return match(strtolower($action)) {
            'export' => $this->exportSettings($io, $file, $category, $includeEncrypted),
            'import' => $this->importSettings($io, $file, $force),
            default => throw new \InvalidArgumentException("Acción inválida. Usa 'export' o 'import'"),
        };
    }

    private function exportSettings(SymfonyStyle $io, string $file, ?string $category, bool $includeEncrypted): int
    {
        $io->title('Exportación de Configuraciones');

        if ($includeEncrypted) {
            $io->warning([
                'ADVERTENCIA: Vas a exportar valores encriptados en texto plano',
                'Este archivo contendrá secretos y credenciales sensibles',
                'Asegúrate de proteger este archivo adecuadamente'
            ]);

            if (!$io->confirm('¿Estás seguro de continuar?', false)) {
                $io->note('Exportación cancelada');
                return Command::SUCCESS;
            }
        }

        // Obtener settings
        $settings = $category
            ? $this->settingRepository->findByCategory($category)
            : $this->settingRepository->findAll();

        if (empty($settings)) {
            $io->warning('No se encontraron configuraciones para exportar');
            return Command::SUCCESS;
        }

        $exportData = [
            'exported_at' => date('Y-m-d H:i:s'),
            'category' => $category ?? 'all',
            'include_encrypted' => $includeEncrypted,
            'settings' => []
        ];

        foreach ($settings as $setting) {
            $value = $setting->getValue();

            // Manejar valores encriptados
            if ($setting->getType() === AppSetting::TYPE_ENCRYPTED) {
                if ($includeEncrypted) {
                    // Desencriptar para exportar
                    $decrypted = $this->configService->get($setting->getKey(), null, false);
                    $value = $decrypted;
                } else {
                    // Omitir valor encriptado
                    $value = null;
                }
            }

            $exportData['settings'][] = [
                'key' => $setting->getKey(),
                'value' => $value,
                'type' => $setting->getType(),
                'category' => $setting->getCategory(),
                'description' => $setting->getDescription(),
                'is_public' => $setting->isPublic(),
            ];
        }

        // Crear directorio si no existe
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Guardar archivo
        $json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($file, $json);

        $io->success([
            'Configuraciones exportadas exitosamente',
            "Archivo: {$file}",
            "Total de configuraciones: " . count($exportData['settings']),
            $category ? "Categoría: {$category}" : "Categoría: Todas",
        ]);

        if (!$includeEncrypted) {
            $io->note('Los valores encriptados NO fueron incluidos. Deberás configurarlos manualmente en el entorno de destino.');
        }

        return Command::SUCCESS;
    }

    private function importSettings(SymfonyStyle $io, string $file, bool $force): int
    {
        $io->title('Importación de Configuraciones');

        if (!file_exists($file)) {
            $io->error("El archivo {$file} no existe");
            return Command::FAILURE;
        }

        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $io->error('Error al parsear JSON: ' . json_last_error_msg());
            return Command::FAILURE;
        }

        if (!isset($data['settings']) || !is_array($data['settings'])) {
            $io->error('Formato de archivo inválido');
            return Command::FAILURE;
        }

        $io->text([
            "Archivo exportado: " . ($data['exported_at'] ?? 'desconocido'),
            "Categoría: " . ($data['category'] ?? 'all'),
            "Total de configuraciones: " . count($data['settings']),
        ]);
        $io->newLine();

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($data['settings'] as $settingData) {
            $key = $settingData['key'];
            $exists = $this->configService->has($key);

            if ($exists && !$force) {
                $io->text("⏭️  <comment>{$key}</comment>: Ya existe (usa --force para sobrescribir)");
                $skipped++;
                continue;
            }

            try {
                // Si el valor es null y el tipo es encrypted, omitir
                if ($settingData['type'] === AppSetting::TYPE_ENCRYPTED && $settingData['value'] === null) {
                    $io->text("⚠️  <comment>{$key}</comment>: Valor encriptado omitido - configurar manualmente");
                    $skipped++;
                    continue;
                }

                // Buscar o crear setting
                $setting = $this->settingRepository->findByKey($key);
                if (!$setting) {
                    $setting = new AppSetting();
                    $setting->setKey($key);
                }

                $setting->setType($settingData['type']);
                $setting->setCategory($settingData['category'] ?? AppSetting::CATEGORY_GENERAL);
                $setting->setDescription($settingData['description'] ?? null);
                $setting->setIsPublic($settingData['is_public'] ?? false);

                // Establecer valor
                if ($settingData['type'] === AppSetting::TYPE_ENCRYPTED && $settingData['value'] !== null) {
                    // Re-encriptar con la clave del entorno actual
                    $this->configService->set($key, $settingData['value'], $settingData['type'], $settingData['category']);
                } else {
                    $setting->setValue($settingData['value']);
                    $this->entityManager->persist($setting);
                }

                $action = $exists ? 'Actualizada' : 'Creada';
                $io->text("✅ <info>{$key}</info> ({$action})");
                $imported++;

            } catch (\Exception $e) {
                $io->error("Error al importar {$key}: " . $e->getMessage());
                $errors++;
            }
        }

        if ($imported > 0) {
            $this->entityManager->flush();
            $this->configService->clearCache();
        }

        $io->newLine();
        $io->section('Resumen de Importación');

        $io->definitionList(
            ['Configuraciones importadas' => $imported],
            ['Configuraciones omitidas' => $skipped],
            ['Errores' => $errors],
        );

        if ($imported > 0) {
            $io->success("Se importaron {$imported} configuraciones exitosamente");
        } else {
            $io->warning('No se importó ninguna configuración nueva');
        }

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
