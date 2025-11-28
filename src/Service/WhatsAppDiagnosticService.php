<?php

namespace App\Service;

use App\Repository\WhatsApp\MessageRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WhatsAppDiagnosticService
{
    private const API_VERSION = 'v22.0';
    private const CACHE_TTL = 300; // 5 minutos

    private array $cache = [];
    private ?HttpClientInterface $httpClient = null;

    public function __construct(
        private readonly ConfigurationService $configService,
        private readonly MessageRepository $messageRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    private function getHttpClient(): HttpClientInterface
    {
        if ($this->httpClient === null) {
            $this->httpClient = HttpClient::create();
        }

        return $this->httpClient;
    }

    /**
     * Obtiene el estado de un número de teléfono desde Meta API
     */
    public function getPhoneStatus(string $phoneType = 'primary'): array
    {
        $cacheKey = "phone_status_{$phoneType}";

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            $token = $this->configService->get("whatsapp.{$phoneType}.token");
            $phoneId = $this->configService->get("whatsapp.{$phoneType}.phone_id");

            if (!$token || !$phoneId) {
                return [
                    'error' => 'Configuración no encontrada',
                    'configured' => false
                ];
            }

            $url = sprintf(
                'https://graph.facebook.com/%s/%s?fields=display_phone_number,code_verification_status,quality_rating,platform_type,account_mode,messaging_limit_tier,name_status,is_official_business_account',
                self::API_VERSION,
                $phoneId
            );

            $response = $this->getHttpClient()->request('GET', $url, [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'timeout' => 10
            ]);

            $data = $response->toArray();
            $data['configured'] = true;
            $data['phone_type'] = $phoneType;

            $this->cache[$cacheKey] = $data;

            return $data;

        } catch (\Exception $e) {
            $this->logger->error('Error obteniendo estado de número WhatsApp', [
                'phone_type' => $phoneType,
                'error' => $e->getMessage()
            ]);

            return [
                'error' => $e->getMessage(),
                'configured' => false,
                'phone_type' => $phoneType
            ];
        }
    }

    /**
     * Obtiene configuración actual enmascarada
     */
    public function getMaskedConfiguration(): array
    {
        return [
            'primary' => [
                'token' => $this->maskToken($this->configService->get('whatsapp.primary.token')),
                'phone_id' => $this->configService->get('whatsapp.primary.phone_id')
            ],
            'backup' => [
                'token' => $this->maskToken($this->configService->get('whatsapp.backup.token')),
                'phone_id' => $this->configService->get('whatsapp.backup.phone_id')
            ],
            'api_version' => $this->configService->get('whatsapp.api_version', 'v22.0'),
            'failover_threshold' => $this->configService->get('whatsapp.failover_threshold', 3),
            'max_retries' => $this->configService->get('whatsapp.max_retries', 5),
            'webhook_token' => $this->maskToken($this->configService->get('whatsapp.webhook_verify_token'))
        ];
    }

    /**
     * Obtiene métricas de mensajes
     */
    public function getMetrics(int $days = 7): array
    {
        $since = new \DateTime("-{$days} days");

        $messages = $this->messageRepository->createQueryBuilder('m')
            ->where('m.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        $total = count($messages);
        $byStatus = [
            'pending' => 0,
            'sent' => 0,
            'delivered' => 0,
            'read' => 0,
            'failed' => 0
        ];

        $byDay = [];

        foreach ($messages as $message) {
            $status = $message->getEstado();
            if (isset($byStatus[$status])) {
                $byStatus[$status]++;
            }

            $day = $message->getCreatedAt()->format('Y-m-d');
            if (!isset($byDay[$day])) {
                $byDay[$day] = ['sent' => 0, 'delivered' => 0, 'failed' => 0];
            }

            if ($status === 'sent' || $status === 'delivered' || $status === 'read') {
                $byDay[$day]['sent']++;
            }
            if ($status === 'delivered' || $status === 'read') {
                $byDay[$day]['delivered']++;
            }
            if ($status === 'failed') {
                $byDay[$day]['failed']++;
            }
        }

        // Calcular tasa de entrega
        $successCount = $byStatus['delivered'] + $byStatus['read'];
        $deliveryRate = $total > 0 ? round(($successCount / $total) * 100, 1) : 0;

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'by_day' => $byDay,
            'delivery_rate' => $deliveryRate,
            'success_count' => $successCount,
            'period_days' => $days
        ];
    }

    /**
     * Obtiene últimos mensajes
     */
    public function getRecentMessages(int $limit = 10): array
    {
        return $this->messageRepository->createQueryBuilder('m')
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Enmascara un token para mostrar de forma segura
     */
    private function maskToken(?string $token): ?string
    {
        if (!$token || strlen($token) < 20) {
            return null;
        }

        $length = strlen($token);
        return substr($token, 0, 10) . '...' . substr($token, -10) . " ({$length} chars)";
    }

    /**
     * Valida que la configuración esté completa
     */
    public function validateConfiguration(): array
    {
        $issues = [];

        $primaryToken = $this->configService->get('whatsapp.primary.token');
        $primaryPhoneId = $this->configService->get('whatsapp.primary.phone_id');
        $backupToken = $this->configService->get('whatsapp.backup.token');
        $backupPhoneId = $this->configService->get('whatsapp.backup.phone_id');

        if (!$primaryToken || strlen($primaryToken) < 50) {
            $issues[] = 'Token PRIMARY inválido o no configurado';
        }

        if (!$primaryPhoneId) {
            $issues[] = 'Phone ID PRIMARY no configurado';
        }

        if (!$backupToken || strlen($backupToken) < 50) {
            $issues[] = 'Token BACKUP inválido o no configurado';
        }

        if (!$backupPhoneId) {
            $issues[] = 'Phone ID BACKUP no configurado';
        }

        return [
            'is_valid' => empty($issues),
            'issues' => $issues
        ];
    }

}
