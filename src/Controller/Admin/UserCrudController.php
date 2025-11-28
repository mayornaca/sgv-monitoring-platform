<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\Tbl14Personal;
use App\Entity\Tbl06Concesionaria;
use App\Repository\Tbl06ConcesionariaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

#[IsGranted('ROLE_ADMIN')]
class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager
    ) {}

    public static function getEntityFqcn(): string
    {
        return User::class;
    }
    
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Usuario')
            ->setEntityLabelInPlural('Usuarios')
            ->setSearchFields(['email', 'username', 'firstName', 'lastName'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(20)
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        $fields = [
            IdField::new('id')->hideOnForm(),
            EmailField::new('email', 'Correo Electrónico')
                ->setHelp('El email será usado para el inicio de sesión'),
            TextField::new('username', 'Usuario')
                ->setHelp('Nombre de usuario único en el sistema'),
            TextField::new('firstName', 'Nombre'),
            TextField::new('lastName', 'Apellido'),
        ];
        
        // Campo password con configuración mejorada
        if ($pageName === Crud::PAGE_NEW) {
            // Para nuevos usuarios: campo obligatorio con validación
            $fields[] = TextField::new('password', 'Contraseña')
                ->setFormType(RepeatedType::class)
                ->setFormTypeOptions([
                    'type' => PasswordType::class,
                    'first_options' => [
                        'label' => 'Contraseña',
                        'attr' => [
                            'autocomplete' => 'new-password',
                            'placeholder' => 'Mínimo 8 caracteres',
                            'class' => 'form-control',
                        ],
                        'help' => 'La contraseña debe tener al menos 8 caracteres, incluir mayúsculas, minúsculas y números',
                    ],
                    'second_options' => [
                        'label' => 'Confirmar Contraseña',
                        'attr' => [
                            'autocomplete' => 'new-password',
                            'placeholder' => 'Repita la contraseña',
                            'class' => 'form-control',
                        ],
                    ],
                    'invalid_message' => 'Las contraseñas no coinciden',
                    'mapped' => false,
                    'required' => true,
                    'constraints' => [
                        new Assert\NotBlank(),
                        new Assert\Length([
                            'min' => 8,
                            'minMessage' => 'La contraseña debe tener al menos {{ limit }} caracteres',
                        ]),
                        new Assert\Regex([
                            'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
                            'message' => 'La contraseña debe contener al menos una letra minúscula, una mayúscula y un número',
                        ]),
                    ],
                ])
                ->setRequired(true)
                ->onlyOnForms();
        } elseif ($pageName === Crud::PAGE_EDIT) {
            // Para edición: campo opcional con texto explicativo
            $fields[] = TextField::new('password', 'Nueva Contraseña (Opcional)')
                ->setFormType(RepeatedType::class)
                ->setFormTypeOptions([
                    'type' => PasswordType::class,
                    'first_options' => [
                        'label' => 'Nueva Contraseña',
                        'attr' => [
                            'autocomplete' => 'new-password',
                            'placeholder' => 'Dejar vacío para mantener la actual',
                            'class' => 'form-control',
                        ],
                        'help' => 'Solo complete si desea cambiar la contraseña actual',
                    ],
                    'second_options' => [
                        'label' => 'Confirmar Nueva Contraseña',
                        'attr' => [
                            'autocomplete' => 'new-password',
                            'placeholder' => 'Repita la nueva contraseña',
                            'class' => 'form-control',
                        ],
                    ],
                    'invalid_message' => 'Las contraseñas no coinciden',
                    'mapped' => false,
                    'required' => false,
                ])
                ->setRequired(false)
                ->onlyOnForms();
        }
        
        $fields = array_merge($fields, [
            ChoiceField::new('roles', 'Roles')
                ->setChoices([
                    'Roles Básicos' => [
                        'Usuario' => 'ROLE_USER',
                    ],
                    'Administración' => [
                        'Administrador' => 'ROLE_ADMIN',
                        'Super Admin' => 'ROLE_SUPER_ADMIN',
                    ],
                    'Centro de Operaciones (COT)' => [
                        'Operador COT' => 'ROLE_OPERATOR_COT',
                        'Admin COT' => 'ROLE_ADMIN_COT',
                        'Supervisor COT' => 'ROLE_SU_COT',
                    ],
                    'Monitoreo y Reportes' => [
                        'Monitor Gerencia' => 'ROLE_MONITOR_GERENCIA',
                        'Monitor Gerencia Reportes' => 'ROLE_MONITOR_GERENCIA_REPORTES',
                    ],
                    'Vespucio Sur (VS)' => [
                        'Usuario VS' => 'ROLE_USUARIO_VS',
                    ],
                    'Fiscalización' => [
                        'Inspector Fiscal' => 'ROLE_FISCAL_INSPECTOR',
                    ],
                    'Operaciones Especializadas' => [
                        'Operador SCADA' => 'ROLE_OPERATOR_SCADA',
                        'Operador Pórtico' => 'ROLE_OPERATOR_PORTICO',
                        'Operador Incidentes' => 'ROLE_OPERATOR_INCIDENTS',
                    ],
                ])
                ->allowMultipleChoices()
                ->renderExpanded(false)
                ->setHelp('Mantenga presionado Ctrl/Cmd para seleccionar múltiples roles'),
                
            BooleanField::new('isActive', 'Activo')
                ->setHelp('Desactive para bloquear el acceso del usuario'),
            
            ChoiceField::new('locale', 'Idioma')
                ->setChoices([
                    'Español' => 'es',
                    'English' => 'en',
                    'Português' => 'pt',
                ])
                ->setHelp('Idioma preferido del usuario'),
            // Campo Personal con búsqueda
            AssociationField::new('idStaff', 'Personal Asociado')
                ->setFormTypeOptions([
                    'placeholder' => 'Seleccione un empleado',
                    'required' => false,
                    'attr' => [
                        'data-widget' => 'select2',
                    ],
                ])
                ->formatValue(function ($value) {
                    if ($value instanceof \App\Entity\Tbl14Personal) {
                        return sprintf('%s %s (RUT: %s)',
                            $value->getApellidos(),
                            $value->getNombres(),
                            $value->getRut()
                        );
                    }
                    return '';
                })
                ->setHelp('Busque y seleccione el empleado por nombre o RUT')
                ->hideOnIndex(),
        ]);

        // Campo Concesiones con selección múltiple (configuración dinámica)
        $concessionsField = ChoiceField::new('concessionsList', 'Concesiones')
            ->setFormTypeOptions([
                'choices' => $this->getConcesionesChoices(),
                'multiple' => true,
                'expanded' => false,
                'mapped' => false,
                'attr' => [
                    'data-widget' => 'select2',
                    'data-placeholder' => 'Seleccione las concesiones',
                ],
            ])
            ->setHelp('Seleccione las concesiones a las que el usuario tiene acceso')
            ->hideOnIndex()
            ->onlyOnForms();

        // En modo edición, pre-cargar los valores actuales del usuario
        if ($pageName === Crud::PAGE_EDIT) {
            $user = $this->getContext()?->getEntity()?->getInstance();
            if ($user && $user->getConcessions()) {
                $currentConcessions = array_map('intval', explode(',', $user->getConcessions()));
                $concessionsField->setFormTypeOption('data', $currentConcessions);
            }
        }

        $fields[] = $concessionsField;

        $fields = array_merge($fields, [
                
            DateTimeField::new('lastLogin', 'Último Acceso')
                ->hideOnForm(),
            DateTimeField::new('createdAt', 'Creado')
                ->hideOnForm(),
            DateTimeField::new('updatedAt', 'Actualizado')
                ->hideOnForm(),
        ]);
        
        return $fields;
    }
    
    public function createNewFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $formBuilder = parent::createNewFormBuilder($entityDto, $formOptions, $context);
        return $this->addPasswordEventListener($formBuilder);
    }

    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $formBuilder = parent::createEditFormBuilder($entityDto, $formOptions, $context);
        return $this->addPasswordEventListener($formBuilder);
    }

    private function addPasswordEventListener(FormBuilderInterface $formBuilder): FormBuilderInterface
    {
        return $formBuilder->addEventListener(FormEvents::POST_SUBMIT, $this->hashPassword());
    }

    private function hashPassword()
    {
        return function (FormEvent $event) {
            $form = $event->getForm();
            if (!$form->isValid()) {
                return;
            }
            $password = $form->get('password')->getData();
            if ($password === null) {
                return;
            }

            $hash = $this->passwordHasher->hashPassword($form->getData(), $password);
            $form->getData()->setPassword($hash);
        };
    }
    
    /**
     * Personaliza la presentación de los usuarios en las listas
     */
    public function createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters): \Doctrine\ORM\QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        
        // Ordenar por usuarios activos primero, luego por fecha de creación
        $queryBuilder->orderBy('entity.isActive', 'DESC')
                    ->addOrderBy('entity.createdAt', 'DESC');
        
        return $queryBuilder;
    }
    
    /**
     * Obtiene las opciones de concesiones para el campo de selección
     */
    private function getConcesionesChoices(): array
    {
        try {
            // Obtener todas las concesionarias activas
            $concesionarias = $this->entityManager
                ->getRepository(Tbl06Concesionaria::class)
                ->findAll();

            $choices = [];
            foreach ($concesionarias as $concesionaria) {
                $choices[$concesionaria->getNombre()] = $concesionaria->getIdConcesionaria();
            }

            // Ordenar alfabéticamente por nombre
            ksort($choices);

            return $choices;
        } catch (\Exception $e) {
            // Si hay error, retornar array vacío
            return [];
        }
    }
    
    /**
     * Procesa los datos del formulario antes de persistir
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            // Procesar las concesiones seleccionadas
            $request = $this->getContext()->getRequest();
            $formData = $request->request->all('User');
            
            if (isset($formData['concessionsList']) && is_array($formData['concessionsList'])) {
                $concessions = implode(',', $formData['concessionsList']);
                $entityInstance->setConcessions($concessions);
            }
        }
        
        parent::persistEntity($entityManager, $entityInstance);
    }
    
    /**
     * Procesa los datos del formulario antes de actualizar
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            // Procesar las concesiones seleccionadas
            $request = $this->getContext()->getRequest();
            $formData = $request->request->all('User');
            
            if (isset($formData['concessionsList'])) {
                if (is_array($formData['concessionsList']) && !empty($formData['concessionsList'])) {
                    $concessions = implode(',', $formData['concessionsList']);
                    $entityInstance->setConcessions($concessions);
                } else {
                    $entityInstance->setConcessions(null);
                }
            }
        }
        
        parent::updateEntity($entityManager, $entityInstance);
    }
}
