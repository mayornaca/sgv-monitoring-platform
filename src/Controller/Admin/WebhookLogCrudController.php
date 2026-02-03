<?php

namespace App\Controller\Admin;

use App\Entity\WebhookLog;
use App\Service\ConcessionMappingService;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class WebhookLogCrudController extends AbstractCrudController
{
    public function __construct(
        private ConcessionMappingService $concessionMappingService
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return WebhookLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Webhook Log')
            ->setEntityLabelInPlural('Webhook Logs')
            ->setSearchFields(['source', 'endpoint', 'metaMessageId', 'ipAddress', 'errorMessage'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(50)
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        $sourceChoices = [
            'Alertmanager' => WebhookLog::SOURCE_ALERTMANAGER,
            'Grafana' => WebhookLog::SOURCE_GRAFANA,
            'Prometheus' => WebhookLog::SOURCE_PROMETHEUS,
            'WhatsApp Status' => WebhookLog::SOURCE_WHATSAPP_STATUS,
            'WhatsApp Message' => WebhookLog::SOURCE_WHATSAPP_MESSAGE,
            'WhatsApp Error' => WebhookLog::SOURCE_WHATSAPP_ERROR,
            'Unknown' => WebhookLog::SOURCE_UNKNOWN,
        ];

        $statusChoices = [
            'Recibido' => WebhookLog::STATUS_RECEIVED,
            'En Cola' => WebhookLog::STATUS_QUEUED,
            'Procesando' => WebhookLog::STATUS_PROCESSING,
            'Completado' => WebhookLog::STATUS_COMPLETED,
            'Fallido' => WebhookLog::STATUS_FAILED,
        ];

        $concessionChoices = [];
        foreach ($this->concessionMappingService->getAllConcessionsWithNames() as $code => $name) {
            $concessionChoices[$name] = $code;
        }

        $fields = [
            IdField::new('id')->hideOnForm(),

            ChoiceField::new('source', 'Origen')
                ->setChoices($sourceChoices)
                ->renderAsBadges([
                    WebhookLog::SOURCE_ALERTMANAGER => 'warning',
                    WebhookLog::SOURCE_GRAFANA => 'info',
                    WebhookLog::SOURCE_PROMETHEUS => 'primary',
                    WebhookLog::SOURCE_WHATSAPP_STATUS => 'success',
                    WebhookLog::SOURCE_WHATSAPP_MESSAGE => 'success',
                    WebhookLog::SOURCE_WHATSAPP_ERROR => 'danger',
                    WebhookLog::SOURCE_UNKNOWN => 'secondary',
                ]),

            TextField::new('endpoint', 'Endpoint')
                ->setMaxLength(50),

            ChoiceField::new('processingStatus', 'Estado')
                ->setChoices($statusChoices)
                ->renderAsBadges([
                    WebhookLog::STATUS_RECEIVED => 'secondary',
                    WebhookLog::STATUS_QUEUED => 'info',
                    WebhookLog::STATUS_PROCESSING => 'warning',
                    WebhookLog::STATUS_COMPLETED => 'success',
                    WebhookLog::STATUS_FAILED => 'danger',
                ]),

            TextField::new('concessionCode', 'Concesión')
                ->setMaxLength(10),

            TextField::new('metaMessageId', 'Meta Message ID')
                ->setMaxLength(30)
                ->hideOnIndex(),

            TextField::new('method', 'Método HTTP')
                ->hideOnIndex(),

            TextField::new('ipAddress', 'IP')
                ->hideOnIndex(),

            TextField::new('userAgent', 'User Agent')
                ->setMaxLength(100)
                ->hideOnIndex(),

            IntegerField::new('retryCount', 'Reintentos')
                ->hideOnIndex(),

            DateTimeField::new('createdAt', 'Recibido')
                ->setFormat('dd/MM/yyyy HH:mm:ss'),

            DateTimeField::new('processedAt', 'Procesado')
                ->setFormat('dd/MM/yyyy HH:mm:ss')
                ->hideOnIndex(),

            TextField::new('shortSummary', 'Resumen')
                ->hideOnForm()
                ->hideOnDetail(),

            TextareaField::new('errorMessage', 'Error')
                ->hideOnIndex()
                ->setDisabled(),

            TextField::new('relatedEntityType', 'Entidad Relacionada')
                ->hideOnIndex(),

            IntegerField::new('relatedEntityId', 'ID Entidad')
                ->hideOnIndex(),
        ];

        // Show raw payload on detail page
        if ($pageName === Crud::PAGE_DETAIL) {
            $fields[] = CodeEditorField::new('rawPayload', 'Payload Raw')
                ->setLanguage('js')
                ->setNumOfRows(20)
                ->hideOnIndex()
                ->hideOnForm();

            $fields[] = CodeEditorField::new('headersJson', 'Headers')
                ->setLanguage('js')
                ->setNumOfRows(5)
                ->hideOnIndex()
                ->hideOnForm()
                ->formatValue(function ($value, $entity) {
                    return json_encode($entity->getHeaders(), JSON_PRETTY_PRINT);
                });

            $fields[] = CodeEditorField::new('processingResultJson', 'Resultado')
                ->setLanguage('js')
                ->setNumOfRows(10)
                ->hideOnIndex()
                ->hideOnForm()
                ->formatValue(function ($value, $entity) {
                    return json_encode($entity->getProcessingResult(), JSON_PRETTY_PRINT);
                });
        }

        return $fields;
    }

    public function configureFilters(Filters $filters): Filters
    {
        $concessionChoices = [];
        foreach ($this->concessionMappingService->getAllConcessionsWithNames() as $code => $name) {
            $concessionChoices[$name] = $code;
        }

        return $filters
            ->add(ChoiceFilter::new('source', 'Origen')->setChoices([
                'Alertmanager' => WebhookLog::SOURCE_ALERTMANAGER,
                'Grafana' => WebhookLog::SOURCE_GRAFANA,
                'Prometheus' => WebhookLog::SOURCE_PROMETHEUS,
                'WhatsApp Status' => WebhookLog::SOURCE_WHATSAPP_STATUS,
                'WhatsApp Message' => WebhookLog::SOURCE_WHATSAPP_MESSAGE,
                'WhatsApp Error' => WebhookLog::SOURCE_WHATSAPP_ERROR,
            ]))
            ->add(ChoiceFilter::new('processingStatus', 'Estado')->setChoices([
                'Recibido' => WebhookLog::STATUS_RECEIVED,
                'Completado' => WebhookLog::STATUS_COMPLETED,
                'Fallido' => WebhookLog::STATUS_FAILED,
            ]))
            ->add(ChoiceFilter::new('concessionCode', 'Concesión')->setChoices($concessionChoices))
            ->add(TextFilter::new('metaMessageId', 'Meta Message ID'))
            ->add(TextFilter::new('errorMessage', 'Error'))
            ->add(DateTimeFilter::new('createdAt', 'Fecha'))
            ->add(TextFilter::new('relatedEntityType', 'Tipo Entidad'))
            ->add(TextFilter::new('relatedEntityId', 'ID Entidad'));
    }
}
