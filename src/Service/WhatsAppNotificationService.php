<?php

namespace App\Service;

use App\Entity\WhatsApp\Message;
use App\Entity\WhatsApp\Recipient;
use App\Entity\WhatsApp\RecipientGroup;
use App\Entity\WhatsApp\Template;
use App\Repository\WhatsApp\MessageRepository;
use App\Service\ConfigurationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;

class WhatsAppNotificationService
{
    private string $apiVersion;
    private array $primaryConfig;
    private array $backupConfig;
    private int $failoverThreshold;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageRepository $messageRepository,
        private LoggerInterface $logger,
        private ConfigurationService $configService
    ) {
        // Leer configuración desde ConfigurationService (con fallback a .env)
        $this->apiVersion = $this->configService->get('whatsapp.api_version', 'v22.0');

        $this->primaryConfig = [
            'name' => 'primary',
            'access_token' => $this->configService->get('whatsapp.primary.token'),
            'phone_number_id' => $this->configService->get('whatsapp.primary.phone_id')
        ];

        $this->backupConfig = [
            'name' => 'backup',
            'access_token' => $this->configService->get('whatsapp.backup.token'),
            'phone_number_id' => $this->configService->get('whatsapp.backup.phone_id')
        ];

        $this->failoverThreshold = $this->configService->get('whatsapp.failover_threshold', 3);

        $this->logger->info('WhatsApp Service initialized from ConfigurationService', [
            'primary_phone_id' => $this->primaryConfig['phone_number_id'],
            'backup_phone_id' => $this->backupConfig['phone_number_id'],
            'failover_threshold' => $this->failoverThreshold,
            'api_version' => $this->apiVersion
        ]);
    }

    /**
     * Determina qué configuración usar según el retry_count
     */
    private function getPhoneConfig(int $retryCount): array
    {
        if ($retryCount >= $this->failoverThreshold) {
            $this->logger->info('Using BACKUP phone number', [
                'retry_count' => $retryCount,
                'threshold' => $this->failoverThreshold,
                'phone_id' => $this->backupConfig['phone_number_id']
            ]);
            return $this->backupConfig;
        }

        return $this->primaryConfig;
    }

    /**
     * Envía un mensaje de template a un grupo de destinatarios
     *
     * @param Template $template Template de WhatsApp aprobado en Meta
     * @param array $parameters Array de parámetros para el template
     * @param RecipientGroup $group Grupo de destinatarios
     * @param string|null $context Contexto adicional para logging
     * @return array Array de entidades Message creadas
     */
    public function sendTemplateMessage(
        Template $template,
        array $parameters,
        RecipientGroup $group,
        ?string $context = null
    ): array {
        // Validar que el template esté activo
        if (!$template->isActivo()) {
            throw new \InvalidArgumentException("El template '{$template->getNombre()}' no está activo");
        }

        // Validar que el grupo esté activo
        if (!$group->isActivo()) {
            throw new \InvalidArgumentException("El grupo '{$group->getNombre()}' no está activo");
        }

        // Validar cantidad de parámetros
        if (count($parameters) !== $template->getParametrosCount()) {
            throw new \InvalidArgumentException(
                sprintf(
                    'El template requiere %d parámetros, se recibieron %d',
                    $template->getParametrosCount(),
                    count($parameters)
                )
            );
        }

        $messages = [];
        $activeRecipients = $group->getActiveRecipients();

        if (empty($activeRecipients)) {
            $this->logger->warning("El grupo '{$group->getNombre()}' no tiene destinatarios activos");
            return [];
        }

        $this->logger->info(sprintf(
            'Enviando template "%s" a %d destinatarios del grupo "%s"',
            $template->getNombre(),
            count($activeRecipients),
            $group->getNombre()
        ));

        foreach ($activeRecipients as $recipient) {
            try {
                $message = $this->sendToRecipient($recipient, $template, $parameters, $context);
                $messages[] = $message;

                // Sleep entre envíos para no saturar la API
                sleep(1);
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'Error enviando a %s: %s',
                    $recipient->getTelefono(),
                    $e->getMessage()
                ));
            }
        }

        return $messages;
    }

    /**
     * Envía mensaje a un destinatario individual
     */
    private function sendToRecipient(
        Recipient $recipient,
        Template $template,
        array $parameters,
        ?string $context
    ): Message {
        // Crear entidad Message en estado pending
        $message = new Message();
        $message->setRecipient($recipient);
        $message->setTemplate($template);
        $message->setParametros($parameters);
        $message->setMensajeTexto($this->buildMessagePreview($template, $parameters));
        $message->setEstado(Message::STATUS_PENDING);
        $message->setContext($context);

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        try {
            // Determinar qué configuración usar según retry_count
            $phoneConfig = $this->getPhoneConfig($message->getRetryCount());

            // Guardar qué phone number se está usando
            $message->setPhoneNumberUsed($phoneConfig['phone_number_id']);

            // Construir el payload para Meta API
            $payload = $this->buildMetaPayload($recipient->getTelefono(), $template, $parameters);

            // Enviar a Meta API con la configuración seleccionada
            $response = $this->callMetaApi($payload, $phoneConfig);

            // Guardar respuesta completa de Meta para debugging
            $message->setMetaResponse($response);

            // Procesar respuesta
            if (isset($response['messages'][0]['id'])) {
                $metaMessageId = $response['messages'][0]['id'];
                $message->setMetaMessageId($metaMessageId);
                $message->setEstado(Message::STATUS_SENT);

                $this->logger->info(sprintf(
                    'Mensaje enviado exitosamente a %s (Meta ID: %s)',
                    $recipient->getTelefono(),
                    $metaMessageId
                ));
            } else {
                throw new \RuntimeException('Respuesta de Meta API no contiene message ID: ' . json_encode($response));
            }
        } catch (\Exception $e) {
            $message->setEstado(Message::STATUS_FAILED);
            $message->setErrorMessage($e->getMessage());

            $this->logger->error(sprintf(
                'Fallo al enviar mensaje a %s: %s',
                $recipient->getTelefono(),
                $e->getMessage()
            ));
        }

        $this->entityManager->flush();
        return $message;
    }

    /**
     * Construye el payload para Meta WhatsApp Cloud API
     */
    private function buildMetaPayload(string $phoneNumber, Template $template, array $parameters): array
    {
        $parametersFormatted = [];
        foreach ($parameters as $param) {
            $parametersFormatted[] = [
                'type' => 'text',
                'text' => (string) $param
            ];
        }

        return [
            'messaging_product' => 'whatsapp',
            'to' => $phoneNumber,
            'type' => 'template',
            'template' => [
                'name' => $template->getMetaTemplateId(),
                'language' => [
                    'code' => $template->getLanguage()
                ],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => $parametersFormatted
                    ]
                ]
            ]
        ];
    }

    /**
     * Llama a Meta WhatsApp Cloud API
     */
    private function callMetaApi(array $payload, array $phoneConfig): array
    {
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $this->apiVersion,
            $phoneConfig['phone_number_id']
        );

        $this->logger->debug('Calling Meta API', [
            'phone_config' => $phoneConfig['name'],
            'phone_id' => $phoneConfig['phone_number_id'],
            'url' => $url
        ]);

        $client = HttpClient::create();
        $response = $client->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $phoneConfig['access_token'],
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
            'timeout' => 30,
        ]);

        $statusCode = $response->getStatusCode();
        $content = $response->toArray(false);

        if ($statusCode !== 200) {
            throw new \RuntimeException(sprintf(
                'Meta API error (HTTP %d): %s',
                $statusCode,
                json_encode($content)
            ));
        }

        return $content;
    }

    /**
     * Construye un preview del mensaje para almacenar
     */
    private function buildMessagePreview(Template $template, array $parameters): string
    {
        $preview = sprintf('Template: %s', $template->getNombre());
        foreach ($parameters as $index => $param) {
            $preview .= sprintf("\n{{%d}}: %s", $index + 1, $param);
        }
        return $preview;
    }

    /**
     * Actualiza el estado de un mensaje desde webhook de Meta
     */
    public function updateMessageStatus(string $metaMessageId, string $status): ?Message
    {
        $message = $this->messageRepository->findOneByMetaMessageId($metaMessageId);

        if (!$message) {
            $this->logger->warning("No se encontró mensaje con Meta ID: {$metaMessageId}");
            return null;
        }

        $validStatuses = [
            Message::STATUS_SENT,
            Message::STATUS_DELIVERED,
            Message::STATUS_READ,
            Message::STATUS_FAILED
        ];

        if (!in_array($status, $validStatuses)) {
            $this->logger->warning("Estado inválido recibido: {$status}");
            return null;
        }

        $message->setEstado($status);
        $this->entityManager->flush();

        $this->logger->info(sprintf(
            'Mensaje %s actualizado a estado: %s',
            $metaMessageId,
            $status
        ));

        return $message;
    }
}
