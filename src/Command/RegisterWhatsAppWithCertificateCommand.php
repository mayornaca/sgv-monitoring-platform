<?php

namespace App\Command;

use App\Service\ConfigurationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

#[AsCommand(
    name: 'app:whatsapp:register-cert',
    description: 'Registra un número de WhatsApp Business usando el certificado de registro de Meta'
)]
class RegisterWhatsAppWithCertificateCommand extends Command
{
    private const API_VERSION = 'v22.0';

    public function __construct(
        private readonly ConfigurationService $configService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('phone', 'p', InputOption::VALUE_REQUIRED, '¿Qué número registrar?', 'primary')
            ->addArgument('certificate', InputArgument::REQUIRED, 'Certificado de registro de Meta (código largo copiado desde Business Manager)')
            ->setHelp(<<<'HELP'
Este comando registra/revalida un número de WhatsApp Business usando el certificado
de registro proporcionado por Meta Business Manager.

Pasos:
1. Ve a Meta Business Manager → Phone Numbers
2. Selecciona tu número de teléfono
3. Copia el certificado de registro (código largo codificado)
4. Ejecuta este comando con el certificado

Ejemplos:
  # Registrar número primary con certificado
  php bin/console app:whatsapp:register-cert "Cm8KKwj4idLVzJnb..."

  # Registrar número backup
  php bin/console app:whatsapp:register-cert --phone=backup "Cm8KKwj4idLVzJnb..."

HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $certificate = $input->getArgument('certificate');
        $phoneOption = $input->getOption('phone');

        // Validar que el certificado no esté vacío
        if (empty($certificate) || strlen($certificate) < 20) {
            $io->error('El certificado parece inválido o demasiado corto');
            return Command::FAILURE;
        }

        $phoneConfig = $this->getPhoneConfig($phoneOption);
        if (!$phoneConfig) {
            $io->error("Configuración de phone '{$phoneOption}' no encontrada");
            return Command::FAILURE;
        }

        $io->title("Registro de número WhatsApp Business con Certificado");
        $io->section("Configuración:");
        $io->text([
            "  Phone ID: {$phoneConfig['phone_number_id']}",
            "  Tipo: " . ucfirst($phoneOption),
            "  Certificado: " . substr($certificate, 0, 20) . "... (" . strlen($certificate) . " caracteres)"
        ]);

        try {
            $io->section("Verificando estado actual del número...");
            $currentStatus = $this->checkPhoneStatus($phoneConfig);

            $io->text([
                "  Display Name: {$currentStatus['display_phone_number']}",
                "  Estado Verificación: {$currentStatus['code_verification_status']}",
                "  Calidad: {$currentStatus['quality_rating']}",
            ]);

            if ($currentStatus['code_verification_status'] === 'VERIFIED') {
                $io->warning('El número ya está VERIFICADO.');
                $io->text('¿Quieres re-registrarlo de todas formas? Presiona Enter para continuar o Ctrl+C para cancelar.');
                fgets(STDIN);
            }

            $io->section("Enviando solicitud de registro a Meta API...");
            $result = $this->registerPhone($phoneConfig, $certificate);

            if ($result['success']) {
                $io->success([
                    'Número registrado exitosamente!',
                    "Nuevo estado: VERIFIED",
                ]);

                // Verificar el estado actualizado
                $io->section("Verificando estado actualizado...");
                $newStatus = $this->checkPhoneStatus($phoneConfig);
                $io->text([
                    "  Estado Verificación: {$newStatus['code_verification_status']}",
                    "  Calidad: {$newStatus['quality_rating']}",
                ]);

                return Command::SUCCESS;
            } else {
                $io->error([
                    'Error al registrar el número:',
                    $result['error'] ?? 'Error desconocido'
                ]);
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $io->error([
                'Error durante el registro:',
                $e->getMessage()
            ]);
            return Command::FAILURE;
        }
    }

    private function getPhoneConfig(string $type): ?array
    {
        $tokenKey = "whatsapp.{$type}.token";
        $phoneIdKey = "whatsapp.{$type}.phone_id";

        $accessToken = $this->configService->get($tokenKey);
        $phoneNumberId = $this->configService->get($phoneIdKey);

        if (!$accessToken || !$phoneNumberId) {
            return null;
        }

        return [
            'access_token' => $accessToken,
            'phone_number_id' => $phoneNumberId,
            'type' => $type
        ];
    }

    private function checkPhoneStatus(array $phoneConfig): array
    {
        $client = HttpClient::create();
        $url = sprintf(
            'https://graph.facebook.com/%s/%s?fields=display_phone_number,code_verification_status,quality_rating,platform_type',
            self::API_VERSION,
            $phoneConfig['phone_number_id']
        );

        $response = $client->request('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $phoneConfig['access_token'],
            ],
        ]);

        return $response->toArray();
    }

    private function registerPhone(array $phoneConfig, string $certificate): array
    {
        $client = HttpClient::create();
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/register',
            self::API_VERSION,
            $phoneConfig['phone_number_id']
        );

        try {
            $response = $client->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $phoneConfig['access_token'],
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'code' => $certificate
                ],
                'timeout' => 30,
            ]);

            $data = $response->toArray();

            return [
                'success' => $data['success'] ?? false,
                'data' => $data
            ];

        } catch (ClientExceptionInterface | ServerExceptionInterface $e) {
            $errorData = json_decode($e->getResponse()->getContent(false), true);
            return [
                'success' => false,
                'error' => $errorData['error']['message'] ?? $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
