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
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

#[AsCommand(
    name: 'app:whatsapp:revalidate',
    description: 'Re-valida un número de teléfono de WhatsApp Business usando el código PIN de 2FA'
)]
class RevalidateWhatsAppPhoneCommand extends Command
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
            ->addOption('phone', 'p', InputOption::VALUE_REQUIRED, '¿Qué número re-validar?', 'primary')
            ->addArgument('pin', InputArgument::REQUIRED, 'Código PIN de 2FA (6 dígitos recibido en WhatsApp Business)')
            ->setHelp(<<<'HELP'
Este comando re-valida un número de WhatsApp Business cuando su estado está EXPIRED.

Pasos:
1. Abre WhatsApp Business Manager (business.facebook.com)
2. Ve a tu número de teléfono en Phone Numbers
3. Solicita el código PIN de verificación (te llegará por WhatsApp)
4. Ejecuta este comando con el PIN recibido

Ejemplos:
  # Re-validar número principal
  php bin/console app:whatsapp:revalidate 123456

  # Re-validar número backup
  php bin/console app:whatsapp:revalidate --phone=backup 123456

HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $pin = $input->getArgument('pin');
        $phoneOption = $input->getOption('phone');

        if (!preg_match('/^\d{6}$/', $pin)) {
            $io->error('El PIN debe ser de 6 dígitos numéricos');
            return Command::FAILURE;
        }

        $phoneConfig = $this->getPhoneConfig($phoneOption);
        if (!$phoneConfig) {
            $io->error("Configuración de phone '{$phoneOption}' no encontrada");
            return Command::FAILURE;
        }

        $io->title("Re-validación de número WhatsApp Business");
        $io->section("Configuración:");
        $io->text([
            "  Phone ID: {$phoneConfig['phone_number_id']}",
            "  Tipo: " . ucfirst($phoneOption),
            "  PIN: {$pin}"
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
                $io->warning('El número ya está VERIFICADO. No es necesario re-validar.');
                $io->text('Si quieres re-validarlo de todas formas, presiona Enter para continuar o Ctrl+C para cancelar.');
                fgets(STDIN);
            }

            $io->section("Enviando solicitud de re-registro a Meta API...");
            $result = $this->registerPhone($phoneConfig, $pin);

            if ($result['success']) {
                $io->success([
                    'Número re-validado exitosamente!',
                    "Nuevo estado: VERIFIED",
                ]);
                return Command::SUCCESS;
            } else {
                $io->error([
                    'Error al re-validar el número:',
                    $result['error'] ?? 'Error desconocido'
                ]);
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $io->error([
                'Error durante la re-validación:',
                $e->getMessage()
            ]);
            return Command::FAILURE;
        }
    }

    private function getPhoneConfig(string $type): ?array
    {
        // Leer configuración desde ConfigurationService
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

    private function registerPhone(array $phoneConfig, string $pin): array
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
                    'pin' => $pin
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
