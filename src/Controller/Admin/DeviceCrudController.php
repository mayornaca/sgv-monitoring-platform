<?php

namespace App\Controller\Admin;

use App\Entity\Device;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

class DeviceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Device::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Dispositivo')
            ->setEntityLabelInPlural('Dispositivos')
            ->setSearchFields(['nombre', 'descripcion', 'ip', 'idExterno'])
            ->setDefaultSort(['orden' => 'ASC', 'nombre' => 'ASC'])
            ->setPaginatorPageSize(30);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('nombre', 'Nombre')->setColumns(6),
            TextField::new('idExterno', 'ID Externo')->setColumns(6),
            IntegerField::new('idTipo', 'Tipo de Dispositivo')->setColumns(4),
            TextField::new('ip', 'Dirección IP')->setColumns(4),
            IntegerField::new('orden', 'Orden')->setColumns(4),
            
            TextField::new('descripcion', 'Descripción')->setColumns(12),
            
            NumberField::new('km', 'Kilómetro')
                ->setNumDecimals(3)
                ->setColumns(3),
            IntegerField::new('eje', 'Eje')->setColumns(3),
            IntegerField::new('tramo', 'Tramo')->setColumns(3),
            ChoiceField::new('orientacion', 'Orientación')
                ->setChoices([
                    'Norte' => 'N',
                    'Sur' => 'S',
                    'Este' => 'E',
                    'Oeste' => 'O'
                ])
                ->setColumns(3),
            
            ChoiceField::new('estado', 'Estado')
                ->setChoices([
                    'Inactivo' => 0,
                    'Activo' => 1,
                    'En Mantenimiento' => 2,
                    'Falla' => 3
                ])
                ->setColumns(4),
            IntegerField::new('nFallos', 'Número de Fallos')
                ->setColumns(4),
            TextField::new('critical', 'Criticidad')
                ->setColumns(4),
            
            BooleanField::new('supervisado', 'Supervisado')->setColumns(6),
            IntegerField::new('concesionaria', 'Concesionaria')->setColumns(6),
            
            TextareaField::new('atributos', 'Atributos JSON')
                ->hideOnIndex()
                ->setColumns(12),
            
            BooleanField::new('regStatus', 'Estado Registro')
                ->hideOnIndex(),
            DateTimeField::new('createdAt', 'Creado')->hideOnForm(),
            DateTimeField::new('updatedAt', 'Actualizado')->hideOnForm(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('nombre'))
            ->add(TextFilter::new('ip'))
            ->add(NumericFilter::new('idTipo'))
            ->add(NumericFilter::new('estado'))
            ->add(BooleanFilter::new('supervisado'))
            ->add(NumericFilter::new('concesionaria'));
    }
}