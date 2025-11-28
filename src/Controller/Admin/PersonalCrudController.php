<?php

namespace App\Controller\Admin;

use App\Entity\Tbl14Personal;
use App\Entity\Tbl06Concesionaria;
use App\Entity\Tbl07CentroDeCosto;
use App\Entity\Tbl08Areas;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
class PersonalCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Tbl14Personal::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Personal')
            ->setEntityLabelInPlural('Personal')
            ->setPageTitle('index', 'Gestión de Personal')
            ->setPageTitle('new', 'Crear Nuevo Personal')
            ->setPageTitle('edit', 'Editar Personal')
            ->setPageTitle('detail', 'Detalle del Personal')
            ->setSearchFields(['nombres', 'apellidos', 'rut', 'correoElectronico', 'idFicha'])
            ->setDefaultSort(['apellidos' => 'ASC', 'nombres' => 'ASC'])
            ->setPaginatorPageSize(50)
            ->setPaginatorRangeSize(4)
            ->setEntityPermission('ROLE_SUPER_ADMIN')
            ->showEntityActionsInlined();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('idConcesionaria', 'Concesionaria'))
            ->add(EntityFilter::new('idCentroDeCosto', 'Centro de Costo'))
            ->add(EntityFilter::new('idArea', 'Área'))
            ->add(TextFilter::new('rut', 'RUT'))
            ->add(TextFilter::new('nombres', 'Nombres'))
            ->add(TextFilter::new('apellidos', 'Apellidos'))
            ->add(BooleanFilter::new('regStatus', 'Activo'))
            ->add(BooleanFilter::new('estadoLicenciaConducir', 'Licencia Suspendida'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        // Campos básicos de identificación
        yield IdField::new('idPersonal', 'ID')->onlyOnIndex();
        yield TextField::new('rut', 'RUT')->setRequired(true);
        yield TextField::new('nombres', 'Nombres')->setRequired(true);
        yield TextField::new('apellidos', 'Apellidos')->setRequired(true);
        yield EmailField::new('correoElectronico', 'Correo Electrónico');

        // Foto del personal
        yield ImageField::new('photo', 'Foto')
            ->setBasePath('/uploads/personal')
            ->setUploadDir('public/uploads/personal')
            ->setUploadedFileNamePattern('[slug]-[contenthash].[extension]')
            ->setHelp('Formato: JPG, PNG. Tamaño máximo: 2MB')
            ->hideOnIndex();

        // Datos organizacionales
        yield AssociationField::new('idConcesionaria', 'Concesionaria')
            ->setRequired(false);
        yield AssociationField::new('idCentroDeCosto', 'Centro de Costo')
            ->setRequired(false);
        yield AssociationField::new('idArea', 'Área')
            ->setRequired(false);

        // Información laboral
        yield TextField::new('cargo', 'Cargo')->hideOnIndex();
        yield TextField::new('idFicha', 'ID Ficha')->hideOnIndex();
        yield TextField::new('telefono', 'Teléfono')->hideOnIndex();
        yield TextField::new('direccion', 'Dirección')->hideOnIndex();

        // Licencia de conducir
        yield TextField::new('licenciasConducir', 'Licencias de Conducir')->hideOnIndex();
        yield DateField::new('fechaEmisionLicencia', 'Fecha Emisión Licencia')->hideOnIndex();
        yield DateField::new('fechaVencimientoLicencia', 'Fecha Vencimiento Licencia')->hideOnIndex();
        yield BooleanField::new('estadoLicenciaConducir', 'Licencia Suspendida')
            ->renderAsSwitch(false);

        // Fechas de contrato
        yield DateField::new('fechaIngreso', 'Fecha de Ingreso')->hideOnIndex();
        yield DateField::new('fechaSalida', 'Fecha de Salida')->hideOnIndex();

        // Estado del registro
        yield BooleanField::new('regStatus', 'Activo')
            ->renderAsSwitch(false);
    }
}
