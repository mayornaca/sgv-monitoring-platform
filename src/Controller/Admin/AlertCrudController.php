<?php

namespace App\Controller\Admin;

use App\Entity\Alert;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;

class AlertCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Alert::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Alerta')
            ->setEntityLabelInPlural('Alertas')
            ->setSearchFields(['title', 'description', 'sourceId'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(25)
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setLabel('Nueva Alerta')->setIcon('fa fa-bell');
            });
    }

    public function configureFields(string $pageName): iterable
    {
        $severityChoices = [
            'Crítica' => 'critical',
            'Alta' => 'high',
            'Media' => 'medium',
            'Baja' => 'low',
            'Información' => 'info'
        ];

        $statusChoices = [
            'Activa' => 'active',
            'Reconocida' => 'acknowledged',
            'Resuelta' => 'resolved',
            'Cerrada' => 'closed',
            'Escalada' => 'escalated'
        ];

        $alertTypeChoices = [
            'Sistema' => 'system',
            'Dispositivo' => 'device',
            'Red' => 'network',
            'Seguridad' => 'security',
            'Rendimiento' => 'performance',
            'Mantenimiento' => 'maintenance'
        ];

        return [
            IdField::new('id')->hideOnForm(),
            
            TextField::new('title', 'Título')
                ->setColumns(8),
            ChoiceField::new('severity', 'Severidad')
                ->setChoices($severityChoices)
                ->renderAsBadges([
                    'critical' => 'danger',
                    'high' => 'warning',
                    'medium' => 'primary',
                    'low' => 'info',
                    'info' => 'secondary'
                ])
                ->setColumns(4),
            
            TextareaField::new('description', 'Descripción')
                ->setColumns(12)
                ->hideOnIndex(),
            
            ChoiceField::new('alertType', 'Tipo de Alerta')
                ->setChoices($alertTypeChoices)
                ->setColumns(4),
            TextField::new('sourceType', 'Tipo de Origen')
                ->setColumns(4),
            TextField::new('sourceId', 'ID de Origen')
                ->setColumns(4),
            
            ChoiceField::new('status', 'Estado')
                ->setChoices($statusChoices)
                ->renderAsBadges([
                    'active' => 'danger',
                    'acknowledged' => 'warning',
                    'resolved' => 'success',
                    'closed' => 'secondary',
                    'escalated' => 'danger'
                ])
                ->setColumns(6),
            TextField::new('workflowState', 'Estado del Flujo')
                ->setColumns(6)
                ->hideOnIndex(),
            
            ArrayField::new('tags', 'Etiquetas')
                ->setColumns(6)
                ->hideOnIndex(),
            IntegerField::new('escalationLevel', 'Nivel de Escalamiento')
                ->setColumns(6)
                ->hideOnIndex(),
            
            DateTimeField::new('createdAt', 'Creada')
                ->hideOnForm()
                ->setFormat('dd/MM/yyyy HH:mm'),
            DateTimeField::new('acknowledgedAt', 'Reconocida')
                ->hideOnForm()
                ->hideOnIndex()
                ->setFormat('dd/MM/yyyy HH:mm'),
            DateTimeField::new('resolvedAt', 'Resuelta')
                ->hideOnForm()
                ->hideOnIndex()
                ->setFormat('dd/MM/yyyy HH:mm'),
            DateTimeField::new('lastEscalatedAt', 'Última Escalación')
                ->hideOnForm()
                ->hideOnIndex()
                ->setFormat('dd/MM/yyyy HH:mm'),
            
            TextareaField::new('resolutionNotes', 'Notas de Resolución')
                ->setColumns(12)
                ->hideOnIndex()
                ->hideOnForm(),
            
            ArrayField::new('metadata', 'Metadatos')
                ->setColumns(12)
                ->hideOnIndex()
                ->hideOnForm(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('title', 'Título'))
            ->add(ChoiceFilter::new('severity', 'Severidad')->setChoices([
                'Crítica' => 'critical',
                'Alta' => 'high',
                'Media' => 'medium',
                'Baja' => 'low',
                'Información' => 'info'
            ]))
            ->add(ChoiceFilter::new('status', 'Estado')->setChoices([
                'Activa' => 'active',
                'Reconocida' => 'acknowledged',
                'Resuelta' => 'resolved',
                'Cerrada' => 'closed',
                'Escalada' => 'escalated'
            ]))
            ->add(ChoiceFilter::new('alertType', 'Tipo')->setChoices([
                'Sistema' => 'system',
                'Dispositivo' => 'device',
                'Red' => 'network',
                'Seguridad' => 'security',
                'Rendimiento' => 'performance',
                'Mantenimiento' => 'maintenance'
            ]))
            ->add(DateTimeFilter::new('createdAt', 'Fecha de Creación'));
    }
}