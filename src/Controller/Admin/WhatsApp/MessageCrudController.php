<?php

namespace App\Controller\Admin\WhatsApp;

use App\Controller\Admin\WebhookLogCrudController;
use App\Entity\WebhookLog;
use App\Entity\WhatsApp\Message;
use App\Repository\WebhookLogRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class MessageCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly WebhookLogRepository $webhookLogRepository,
        private readonly AdminUrlGenerator $adminUrlGenerator
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Message::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Mensaje WhatsApp')
            ->setEntityLabelInPlural('Mensajes WhatsApp')
            ->setSearchFields(['recipient.nombre', 'recipient.telefono', 'metaMessageId'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(50)
            ->setPageTitle(Crud::PAGE_INDEX, 'Historial de Mensajes WhatsApp')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Detalle del Mensaje')
            ->setHelp(
                Crud::PAGE_INDEX,
                'Tracking completo de mensajes enviados vía WhatsApp Meta Business API'
            );
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewWebhooksAction = Action::new('viewWebhooks', 'Ver Webhooks')
            ->linkToCrudAction('viewRelatedWebhooks')
            ->displayIf(fn (Message $message) => $message->getMetaMessageId() !== null);

        return $actions
            // Solo permitir ver y listar (no crear/editar/eliminar)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $viewWebhooksAction);
    }

    /**
     * Redirige al listado de webhooks filtrado por este mensaje
     */
    public function viewRelatedWebhooks(AdminContext $context): Response
    {
        /** @var Message $message */
        $message = $context->getEntity()->getInstance();

        $url = $this->adminUrlGenerator
            ->setController(WebhookLogCrudController::class)
            ->setAction(Action::INDEX)
            ->set('filters[relatedEntityType][comparison]', '=')
            ->set('filters[relatedEntityType][value]', 'whatsapp_message')
            ->set('filters[relatedEntityId][comparison]', '=')
            ->set('filters[relatedEntityId][value]', $message->getId())
            ->generateUrl();

        return $this->redirect($url);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('estado')
                ->setChoices([
                    'Pendiente' => Message::STATUS_PENDING,
                    'Enviado' => Message::STATUS_SENT,
                    'Entregado' => Message::STATUS_DELIVERED,
                    'Leído' => Message::STATUS_READ,
                    'Fallido' => Message::STATUS_FAILED,
                ]))
            ->add(EntityFilter::new('recipient'))
            ->add(EntityFilter::new('template'))
            ->add('context');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->setLabel('#');

        yield AssociationField::new('recipient')
            ->setLabel('Destinatario')
            ->setColumns(3);

        yield AssociationField::new('template')
            ->setLabel('Template')
            ->setColumns(3);

        yield ChoiceField::new('estado')
            ->setChoices([
                'Pendiente' => Message::STATUS_PENDING,
                'Enviado' => Message::STATUS_SENT,
                'Entregado' => Message::STATUS_DELIVERED,
                'Leído' => Message::STATUS_READ,
                'Fallido' => Message::STATUS_FAILED,
            ])
            ->setColumns(2)
            ->renderAsBadges([
                Message::STATUS_PENDING => 'secondary',
                Message::STATUS_SENT => 'info',
                Message::STATUS_DELIVERED => 'success',
                Message::STATUS_READ => 'primary',
                Message::STATUS_FAILED => 'danger',
            ]);

        yield TextField::new('context')
            ->setLabel('Contexto')
            ->setColumns(2)
            ->hideOnIndex();

        yield DateTimeField::new('createdAt')
            ->setLabel('Creado')
            ->setColumns(2);

        yield DateTimeField::new('sentAt')
            ->setLabel('Enviado')
            ->setColumns(2)
            ->hideOnIndex();

        yield DateTimeField::new('deliveredAt')
            ->setLabel('Entregado')
            ->setColumns(2)
            ->hideOnIndex();

        yield DateTimeField::new('readAt')
            ->setLabel('Leído')
            ->setColumns(2)
            ->hideOnIndex();

        yield TextareaField::new('mensajeTexto')
            ->setLabel('Mensaje de Texto')
            ->onlyOnDetail();

        yield ArrayField::new('parametros')
            ->setLabel('Parámetros del Template')
            ->onlyOnDetail();

        yield TextField::new('metaMessageId')
            ->setLabel('ID Meta')
            ->onlyOnDetail();

        yield IntegerField::new('retryCount')
            ->setLabel('Intentos')
            ->onlyOnDetail();

        yield TextareaField::new('errorMessage')
            ->setLabel('Mensaje de Error')
            ->onlyOnDetail();

        yield ArrayField::new('metaResponse')
            ->setLabel('Respuesta de Meta API')
            ->onlyOnDetail();

        // Campo virtual para mostrar el historial de webhooks
        if ($pageName === Crud::PAGE_DETAIL) {
            yield TextField::new('webhookSummary')
                ->setLabel('Webhooks Relacionados')
                ->setVirtual(true)
                ->formatValue(function ($value, ?Message $entity) {
                    if ($entity === null || $entity->getId() === null) {
                        return '-';
                    }

                    $webhooks = $this->webhookLogRepository->findByRelatedEntity(
                        'whatsapp_message',
                        $entity->getId()
                    );

                    if (empty($webhooks)) {
                        return 'No hay webhooks registrados';
                    }

                    $statusCount = 0;
                    $errorCount = 0;
                    foreach ($webhooks as $webhook) {
                        if ($webhook->getSource() === WebhookLog::SOURCE_WHATSAPP_STATUS) {
                            $statusCount++;
                        }
                        if ($webhook->getProcessingStatus() === WebhookLog::STATUS_FAILED) {
                            $errorCount++;
                        }
                    }

                    $summary = sprintf('%d webhook(s) total', count($webhooks));
                    if ($statusCount > 0) {
                        $summary .= sprintf(' | %d status update(s)', $statusCount);
                    }
                    if ($errorCount > 0) {
                        $summary .= sprintf(' | %d error(s)', $errorCount);
                    }

                    return $summary;
                });
        }
    }
}
