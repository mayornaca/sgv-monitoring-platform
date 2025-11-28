<?php

namespace App\Controller\Admin\WhatsApp;

use App\Entity\WhatsApp\Recipient;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

class RecipientCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Recipient::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Destinatario WhatsApp')
            ->setEntityLabelInPlural('Destinatarios WhatsApp')
            ->setSearchFields(['nombre', 'telefono', 'email'])
            ->setDefaultSort(['nombre' => 'ASC'])
            ->setPaginatorPageSize(30);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('activo'))
            ->add('grupos');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();

        yield TextField::new('nombre')
            ->setColumns(6)
            ->setRequired(true);

        yield TextField::new('telefono')
            ->setColumns(6)
            ->setHelp('Formato internacional: +56972126016')
            ->setRequired(true);

        yield EmailField::new('email')
            ->setColumns(6);

        yield BooleanField::new('activo')
            ->setColumns(6);

        yield AssociationField::new('grupos')
            ->setLabel('Grupos de Alertas')
            ->setHelp('Seleccione los grupos de alertas a los que pertenece');

        yield TextareaField::new('notas')
            ->setColumns(12)
            ->hideOnIndex();

        yield DateTimeField::new('createdAt')
            ->setLabel('Creado')
            ->onlyOnDetail();

        yield DateTimeField::new('updatedAt')
            ->setLabel('Actualizado')
            ->onlyOnDetail();
    }
}
