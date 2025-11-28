<?php

namespace App\Controller\Admin;

use App\Entity\AlertRule;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;

class AlertRuleCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AlertRule::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Regla de Alerta')
            ->setEntityLabelInPlural('Reglas de Alerta')
            ->setSearchFields(['name', 'description'])
            ->setDefaultSort(['active' => 'DESC', 'name' => 'ASC'])
            ->setPageTitle('index', 'Reglas de Alerta Automáticas')
            ->setPageTitle('new', 'Crear Regla de Alerta')
            ->setPageTitle('edit', 'Editar Regla de Alerta')
            ->setHelp('index', 'Las reglas definen qué alertas se generan automáticamente. La prioridad determina qué canales se usan según la configuración del sistema.')
            ->setHelp('new', 'Configure una regla para generar alertas automáticas cuando ocurran eventos específicos.');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setIcon('fa fa-plus')->setLabel('Nueva Regla');
            });
    }

    public function configureFields(string $pageName): iterable
    {
        // Según notifier.yaml, los canales se asignan por importancia:
        // urgent: ['email', 'browser']
        // high: ['email', 'browser']
        // medium: ['email', 'browser']
        // low: ['browser']
        
        $importanceInfo = '
            <ul style="margin: 0; padding-left: 20px; font-size: 12px;">
                <li><strong>Crítica (urgent):</strong> Email + Navegador</li>
                <li><strong>Alta (high):</strong> Email + Navegador</li>
                <li><strong>Media (medium):</strong> Email + Navegador</li>
                <li><strong>Baja (low):</strong> Solo Navegador</li>
            </ul>
        ';
        
        return [
            IdField::new('id')->hideOnForm(),
            
            TextField::new('name', 'Nombre de la Regla')
                ->setRequired(true)
                ->setHelp('Nombre descriptivo para identificar esta regla'),
            
            BooleanField::new('active', 'Activa')
                ->renderAsSwitch()
                ->setHelp('Las reglas inactivas no generan alertas'),
            
            TextareaField::new('description', 'Descripción')
                ->hideOnIndex()
                ->setHelp('Explique qué eventos monitorea esta regla'),
            
            ChoiceField::new('sourceType', 'Tipo de Origen')
                ->setChoices([
                    'Sistema' => 'system',
                    'Dispositivo' => 'device', 
                    'Red' => 'network',
                    'Seguridad' => 'security',
                    'Rendimiento' => 'performance'
                ])
                ->setRequired(true)
                ->setHelp('¿Qué componente genera estas alertas?'),
            
            ChoiceField::new('alertType', 'Tipo de Alerta')
                ->setChoices([
                    'Error' => 'error',
                    'Advertencia' => 'warning',
                    'Información' => 'info',
                    'Crítico' => 'critical',
                    'Mantenimiento' => 'maintenance'
                ])
                ->setRequired(true)
                ->setHelp('Categoría del evento'),
            
            ChoiceField::new('priority', 'Prioridad / Importancia')
                ->setChoices([
                    'Crítica (urgent)' => 'critical',
                    'Alta (high)' => 'high',
                    'Media (medium)' => 'medium',
                    'Baja (low)' => 'low'
                ])
                ->renderAsBadges([
                    'critical' => 'danger',
                    'high' => 'warning',
                    'medium' => 'primary',
                    'low' => 'secondary'
                ])
                ->setRequired(true)
                ->setHelp('La prioridad determina los canales de notificación:' . $importanceInfo),
            
            DateTimeField::new('createdAt', 'Creada')
                ->hideOnForm()
                ->setFormat('dd/MM/yyyy HH:mm'),
            
            DateTimeField::new('updatedAt', 'Actualizada')
                ->hideOnForm()
                ->hideOnIndex(),
        ];
    }
}