<?php

namespace App\Controller\Admin;

use App\Repository\WhatsApp\RecipientRepository;
use App\Repository\WhatsApp\TemplateRepository;
use App\Service\WhatsAppDiagnosticService;
use App\Service\WhatsAppNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WhatsAppDiagnosticController extends AbstractController
{
    public function __construct(
        private readonly WhatsAppDiagnosticService $diagnosticService,
        private readonly WhatsAppNotificationService $notificationService,
        private readonly TemplateRepository $templateRepository,
        private readonly RecipientRepository $recipientRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[AdminRoute('/whatsapp/diagnostic', name: 'whatsapp_diagnostic')]
    public function diagnostic(): Response
    {
        // Obtener estado de ambos números
        $primaryStatus = $this->diagnosticService->getPhoneStatus('primary');
        $backupStatus = $this->diagnosticService->getPhoneStatus('backup');

        // Obtener configuración enmascarada
        $configuration = $this->diagnosticService->getMaskedConfiguration();

        // Validar configuración
        $validation = $this->diagnosticService->validateConfiguration();

        // Obtener métricas de últimos 7 días
        $metrics = $this->diagnosticService->getMetrics(7);

        // Obtener últimos 10 mensajes
        $recentMessages = $this->diagnosticService->getRecentMessages(10);

        // Obtener templates activos para el formulario de prueba
        $templates = $this->templateRepository->findBy(['activo' => true], ['nombre' => 'ASC']);

        // Obtener destinatarios activos
        $recipients = $this->recipientRepository->findActive();

        return $this->render('admin/whatsapp/diagnostic.html.twig', [
            'primary_status' => $primaryStatus,
            'backup_status' => $backupStatus,
            'configuration' => $configuration,
            'validation' => $validation,
            'metrics' => $metrics,
            'recent_messages' => $recentMessages,
            'templates' => $templates,
            'recipients' => $recipients,
        ]);
    }

    #[Route('/send-test', name: 'admin_whatsapp_send_test', methods: ['POST'])]
    public function sendTest(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!$data) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Datos inválidos'
                ], 400);
            }

            $templateId = $data['template_id'] ?? null;
            $recipientId = $data['recipient_id'] ?? null;
            $params = $data['params'] ?? [];

            if (!$templateId || !$recipientId) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Template y destinatario son requeridos'
                ], 400);
            }

            // Buscar recipient existente
            $recipient = $this->recipientRepository->find($recipientId);
            if (!$recipient || !$recipient->isActivo()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Destinatario no encontrado o inactivo'
                ], 404);
            }

            // Buscar template
            $template = $this->templateRepository->find($templateId);
            if (!$template) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Template no encontrado'
                ], 404);
            }

            // Validar cantidad de parámetros
            if (count($params) !== $template->getParametrosCount()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => sprintf(
                        'El template requiere %d parámetros, se recibieron %d',
                        $template->getParametrosCount(),
                        count($params)
                    )
                ], 400);
            }

            // Buscar o crear grupo persistente para mensajes de prueba
            $groupRepo = $this->entityManager->getRepository(\App\Entity\WhatsApp\RecipientGroup::class);
            $group = $groupRepo->findOneBy(['slug' => 'dashboard_test_messages']);

            if (!$group) {
                $group = new \App\Entity\WhatsApp\RecipientGroup();
                $group->setNombre('Dashboard Test Messages');
                $group->setSlug('dashboard_test_messages');
                $group->setDescripcion('Grupo para mensajes de prueba desde dashboard');
                $group->setActivo(true);
                $this->entityManager->persist($group);
                $this->entityManager->flush();
            }

            // Agregar recipient al grupo si no está
            if (!$group->getRecipients()->contains($recipient)) {
                $group->addRecipient($recipient);
                $this->entityManager->flush();
            }

            // Enviar mensaje usando recipient existente
            $messages = $this->notificationService->sendTemplateMessage(
                $template,
                $params,
                $group,
                'dashboard_test'
            );

            if (empty($messages)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'No se pudo enviar el mensaje'
                ], 500);
            }

            $message = $messages[0];

            return new JsonResponse([
                'success' => true,
                'message_id' => $message->getId(),
                'meta_message_id' => $message->getMetaMessageId(),
                'status' => $message->getEstado(),
                'phone_used' => $message->getPhoneNumberUsed(),
                'recipient_name' => $recipient->getNombre()
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
