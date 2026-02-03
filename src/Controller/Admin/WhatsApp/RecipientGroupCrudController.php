<?php

namespace App\Controller\Admin\WhatsApp;

use App\Entity\WhatsApp\RecipientGroup;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

class RecipientGroupCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return RecipientGroup::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Grupo de Destinatarios')
            ->setEntityLabelInPlural('Grupos de Destinatarios')
            ->setSearchFields(['nombre', 'slug', 'descripcion'])
            ->setDefaultSort(['nombre' => 'ASC'])
            ->setPaginatorPageSize(30);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('activo'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();

        yield TextField::new('nombre')
            ->setColumns(6)
            ->setRequired(true);

        yield TextField::new('slug')
            ->setColumns(6)
            ->setHelp('Identificador único (solo minúsculas, números y _). Ej: prometheus_alerts')
            ->setRequired(true);

        yield TextareaField::new('descripcion')
            ->setColumns(12)
            ->setHelp('Descripción del propósito de este grupo');

        yield BooleanField::new('activo')
            ->setColumns(6);

        yield AssociationField::new('recipients')
            ->setLabel('Destinatarios')
            ->setHelp('Destinatarios que pertenecen a este grupo')
            ->autocomplete();

        yield DateTimeField::new('createdAt')
            ->setLabel('Creado')
            ->onlyOnDetail();

        yield DateTimeField::new('updatedAt')
            ->setLabel('Actualizado')
            ->onlyOnDetail();
    }
}
