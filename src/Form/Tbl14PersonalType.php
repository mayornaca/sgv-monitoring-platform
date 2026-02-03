<?php

namespace App\Form;

use App\Entity\Tbl14Personal;
use App\Entity\Tbl06Concesionaria;
use App\Entity\Tbl07CentroDeCosto;
use App\Entity\Tbl08Areas;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Tbl14PersonalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('idPersonal', IntegerType::class, [
                'required' => false,
                'disabled' => true,
                'label' => false,
                'attr' => ['style' => 'display: none;']
            ])
            ->add('nombres', TextType::class, [
                'label' => 'Nombres',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('apellidos', TextType::class, [
                'label' => 'Apellidos',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('rut', TextType::class, [
                'label' => 'RUT',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('correoElectronico', EmailType::class, [
                'label' => 'Correo Electrónico',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('autorizaOt', CheckboxType::class, [
                'label' => 'Autoriza OT',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('fono', TextType::class, [
                'label' => 'Teléfono',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('anexo', TextType::class, [
                'label' => 'Anexo',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('fechaEmisionLicencia', DateType::class, [
                'label' => 'Fecha Emisión Licencia',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('fechaVencimientoLicencia', DateType::class, [
                'label' => 'Fecha Vencimiento Licencia',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('parent', EntityType::class, [
                'class' => Tbl14Personal::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('ql')
                        ->where('ql.idPersonal >= :ninguno')
                        ->setParameter('ninguno', '0');
                },
                'choice_label' => 'getFullName',
                'placeholder' => 'Seleccione...',
                'required' => false,
                'label' => 'Superior/Jefe',
                'attr' => ['class' => 'form-select']
            ])
            ->add('idConcesionaria', EntityType::class, [
                'class' => Tbl06Concesionaria::class,
                'choice_label' => 'getNombre',
                'placeholder' => 'Seleccione...',
                'required' => false,
                'label' => 'Concesionaria',
                'attr' => ['class' => 'form-select']
            ])
            ->add('idCentroDeCosto', EntityType::class, [
                'class' => Tbl07CentroDeCosto::class,
                'choice_label' => 'getNombreCentroDeCosto',
                'placeholder' => 'Seleccione...',
                'required' => false,
                'label' => 'Centro de Costo',
                'attr' => ['class' => 'form-select']
            ])
            ->add('idArea', EntityType::class, [
                'class' => Tbl08Areas::class,
                'choice_label' => 'getNombreArea',
                'placeholder' => 'Seleccione...',
                'required' => false,
                'label' => 'Área',
                'attr' => ['class' => 'form-select']
            ])
            ->add('licenciasConducir', HiddenType::class, [
                'label' => false,
                'required' => false
            ])
            ->add('estadoLicenciaConducir', CheckboxType::class, [
                'label' => 'Estado Licencia Conducir',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('fechaInicioEstadoLicenciaConducir', DateTimeType::class, [
                'label' => 'Fecha Inicio Estado Licencia',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('fechaTerminoEstadoLicenciaConducir', DateTimeType::class, [
                'label' => 'Fecha Término Estado Licencia',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control']
            ])
            // TODO: Migrar collection cuando se complete migración de Tbl10Vehiculos y Tbl24ConductoresAsignados
            /*
            ->add('tbl24ConductoresAsignados', CollectionType::class, [
                'entry_type' => Tbl24ConductoresAsignadosType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
            ])
            */
            ->add('photo', FileType::class, [
                'label' => 'Imagen de perfil (Archivo de imagen)',
                'data_class' => null,
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Guardar',
                'attr' => ['class' => 'btn btn-success', 'style' => 'display: none;']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tbl14Personal::class,
        ]);
    }
}
