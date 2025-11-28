<?php

namespace App\Controller\Admin\WhatsApp;

use App\Entity\WhatsApp\Message;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class MessageCrudController extends AbstractCrudController
{
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
        return $actions
            // Solo permitir ver y listar (no crear/editar/eliminar)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
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
    }
}
