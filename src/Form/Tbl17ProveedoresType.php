<?php

namespace App\Form;

use App\Entity\Tbl17Proveedores;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Tbl17ProveedoresType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('razonSocial', TextType::class, [
                'label' => 'Razón Social',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('rutProveedor', TextType::class, [
                'label' => 'RUT Proveedor',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('direccionProveedor', TextType::class, [
                'label' => 'Dirección',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('nombreContacto', TextType::class, [
                'label' => 'Nombre Contacto',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('correoElecContacto', EmailType::class, [
                'label' => 'Correo Electrónico',
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
            'data_class' => Tbl17Proveedores::class,
        ]);
    }
}
