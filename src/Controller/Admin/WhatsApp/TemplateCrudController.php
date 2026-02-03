<?php

namespace App\Controller\Admin\WhatsApp;

use App\Entity\WhatsApp\Template;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

class TemplateCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Template::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Template WhatsApp')
            ->setEntityLabelInPlural('Templates WhatsApp')
            ->setSearchFields(['nombre', 'metaTemplateId', 'descripcion'])
            ->setDefaultSort(['nombre' => 'ASC'])
            ->setPaginatorPageSize(30);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('activo'))
            ->add('language');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();

        yield TextField::new('nombre')
            ->setColumns(6)
            ->setHelp('Nombre interno del template (ej: prometheus_alert_firing)')
            ->setRequired(true);

        yield TextField::new('metaTemplateId')
            ->setColumns(6)
            ->setLabel('ID Template Meta')
            ->setHelp('ID del template en Meta Business Manager')
            ->setRequired(true);

        yield TextareaField::new('descripcion')
            ->setColumns(12)
            ->setHelp('Descripción del propósito de este template');

        yield IntegerField::new('parametrosCount')
            ->setColumns(3)
            ->setLabel('Cantidad de parámetros')
            ->setHelp('Número de variables {{1}}, {{2}}, etc.')
            ->setRequired(true);

        yield ChoiceField::new('language')
            ->setColumns(3)
            ->setLabel('Idioma')
            ->setChoices([
                'Español' => 'es',
                'Inglés' => 'en',
                'Portugués' => 'pt',
            ]);

        yield BooleanField::new('activo')
            ->setColumns(3);

        yield ArrayField::new('parametrosDescripcion')
            ->setLabel('Descripción de parámetros')
            ->setHelp('JSON con descripción de cada parámetro. Ej: ["Nombre alerta", "Severidad", "Resumen"]')
            ->hideOnIndex();

        yield DateTimeField::new('createdAt')
            ->setLabel('Creado')
            ->onlyOnDetail();

        yield DateTimeField::new('updatedAt')
            ->setLabel('Actualizado')
            ->onlyOnDetail();
    }
}
