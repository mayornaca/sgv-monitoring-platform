<?php

namespace App\Controller\Api;

use App\Repository\WhatsApp\RecipientGroupRepository;
use App\Repository\WhatsApp\TemplateRepository;
use App\Service\WhatsAppNotificationService;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/api/cot', name: 'api_cot_')]
class CotAlertsController extends AbstractController
{
    private const BEARER_TOKEN = 'b6ef5d2419cd945cfb7b70e67c976b5302e3d92ac0c565ad191d9f6ecdf34362';
    private const CACHE_KEY_HASH = 'spire_alert_last_hash';
    private const UMBRAL_PERDIDA = 3; // Porcentaje de pérdida de datos que dispara alerta

    public function __construct(
        private Connection $connection,
        private WhatsAppNotificationService $whatsAppService,
        private TemplateRepository $templateRepository,
        private RecipientGroupRepository $groupRepository,
        private CacheInterface $cache,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Detecta espiras con pérdida de datos y envía alertas WhatsApp
     * Solo envía si los dispositivos afectados cambiaron (hash-based deduplication)
     */
    #[Route('/spire_general_alert', name: 'spire_general_alert', methods: ['GET', 'POST'])]
    public function spireGeneralAlert(Request $request): JsonResponse
    {
        // Validar Bearer token
        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->json(['error' => 'Missing or invalid Authorization header'], 401);
        }

        $token = substr($authHeader, 7); // Remove "Bearer " prefix
        if ($token !== self::BEARER_TOKEN) {
            $this->logger->warning('Intento de acceso con token inválido a spire_general_alert');
            return $this->json(['error' => 'Invalid bearer token'], 403);
        }

        $this->logger->info('Ejecutando detección de espiras con pérdida de datos');

        // Calcular período analizado
        $fechaInicio = date('Y-m-d');
        $fechaInicio_d_m_Y = date('d-m-Y');
        $inicioDia = new \DateTime('00:00:00');
        $ahora = new \DateTime();
        $minutosTranscurridos = (int) (($ahora->getTimestamp() - $inicioDia->getTimestamp()) / 60);
        $inicioDiaStr = $inicioDia->format('H:i');
        $ahoraStr = $ahora->format('H:i');
        $periodoTexto = "{$inicioDiaStr} a {$ahoraStr} del {$fechaInicio_d_m_Y}";

        // Query SQL para encontrar espiras con pérdida de datos
        $sql = "
            SELECT
                t.id,
                t.id_espira,
                tcd.nombre as id_dispositivo,
                ROUND(AVG(t.verde)*(100/{$minutosTranscurridos}),0) as ver,
                ROUND(AVG(t.amarillo)*(100/{$minutosTranscurridos}),0) as ama,
                ROUND(AVG(t.rojo)*(100/{$minutosTranscurridos}),0) as roj
            FROM tbl_cot_10_acumulado_resumen_espiras t
            LEFT JOIN tbl_cot_02_dispositivos tcd ON t.id_espira = tcd.id
            WHERE 1=1
                AND t.created_at = :fecha_inicio
                AND t.id_espira != 0
            GROUP BY t.id_espira
            HAVING ROUND(AVG(t.rojo)*(100/{$minutosTranscurridos}),0) >= :umbral
            ORDER BY t.rojo DESC
            LIMIT 10
        ";

        $stmt = $this->connection->prepare($sql);
        $result = $stmt->executeQuery([
            'fecha_inicio' => $fechaInicio,
            'umbral' => self::UMBRAL_PERDIDA
        ]);
        $arr_spires = $result->fetchAllAssociative();

        // Si no hay espiras con problemas, retornar sin enviar
        if (empty($arr_spires)) {
            $this->logger->info('No se encontraron espiras con pérdida de datos >= ' . self::UMBRAL_PERDIDA . '%');
            return $this->json([
                'status' => 'ok',
                'message' => 'Sin espiras con pérdida de datos',
                'umbral' => self::UMBRAL_PERDIDA,
                'periodo' => $periodoTexto
            ]);
        }

        // Calcular hash de los dispositivos afectados
        $hashData = [];
        foreach ($arr_spires as $spire) {
            $hashData[] = $spire['id_dispositivo'];
        }
        sort($hashData); // Orden alfabético para que el orden no influya
        $currentHash = md5(implode(';', $hashData));

        // Obtener hash anterior del cache
        $lastHash = $this->cache->get(self::CACHE_KEY_HASH, function (ItemInterface $item) {
            $item->expiresAfter(86400); // 24 horas
            return null;
        });

        // Comparar con hash anterior - si no cambió, no enviamos alertas
        if ($currentHash === $lastHash) {
            $this->logger->info('Hash sin cambios, no se envían alertas', [
                'dispositivos_afectados' => count($arr_spires),
                'hash' => $currentHash
            ]);
            return $this->json([
                'status' => 'sin cambios',
                'message' => 'Los dispositivos afectados no han cambiado desde la última alerta',
                'dispositivos_count' => count($arr_spires)
            ]);
        }

        // Guardar nuevo hash en cache
        $this->cache->get(self::CACHE_KEY_HASH, function (ItemInterface $item) use ($currentHash) {
            $item->expiresAfter(86400); // 24 horas
            return $currentHash;
        });

        // Buscar template y grupo
        $template = $this->templateRepository->findOneBy(['metaTemplateId' => 'card_transaction_alert_1']);
        if (!$template) {
            $this->logger->error('Template card_transaction_alert_1 no encontrado');
            return $this->json([
                'status' => 'error',
                'message' => 'WhatsApp template not configured'
            ], 500);
        }

        $group = $this->groupRepository->findOneBy(['slug' => 'spire_alerts']);
        if (!$group) {
            $this->logger->error('Grupo spire_alerts no encontrado');
            return $this->json([
                'status' => 'error',
                'message' => 'Recipient group not configured'
            ], 500);
        }

        // Formatear líneas de WhatsApp (máximo 3 dispositivos)
        $lineas_whatsapp = [];
        $count = 0;
        foreach ($arr_spires as $spire) {
            if ($count >= 3) break; // Solo primeros 3 dispositivos
            $lineas_whatsapp[] = sprintf(
                '- %s con %s%%',
                $spire['id_dispositivo'],
                $spire['roj']
            );
            $count++;
        }

        // Completar con guiones si hay menos de 3 dispositivos
        while (count($lineas_whatsapp) < 3) {
            $lineas_whatsapp[] = '-';
        }

        // Preparar parámetros para el template
        $parameters = [
            $periodoTexto,
            $lineas_whatsapp[0],
            $lineas_whatsapp[1],
            $lineas_whatsapp[2]
        ];

        try {
            // Enviar alertas WhatsApp
            $messages = $this->whatsAppService->sendTemplateMessage(
                $template,
                $parameters,
                $group,
                'spire_data_loss_alert'
            );

            $this->logger->info('Alertas de espiras enviadas exitosamente', [
                'dispositivos_afectados' => count($arr_spires),
                'mensajes_enviados' => count($messages),
                'hash' => $currentHash
            ]);

            return $this->json([
                'status' => 'ok',
                'message' => sprintf(
                    'Alertas enviadas: %d espiras con pérdida >= %d%%',
                    count($arr_spires),
                    self::UMBRAL_PERDIDA
                ),
                'dispositivos_afectados' => count($arr_spires),
                'mensajes_enviados' => count($messages),
                'periodo' => $periodoTexto,
                'hash' => $currentHash
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error enviando alertas de espiras: ' . $e->getMessage());
            return $this->json([
                'status' => 'error',
                'message' => 'Error sending WhatsApp alerts: ' . $e->getMessage()
            ], 500);
        }
    }
}
