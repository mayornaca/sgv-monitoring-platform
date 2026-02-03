<?php

namespace App\Controller\Admin;

use App\Entity\AppSetting;
use App\Service\ConfigurationService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AppSettingCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigurationService $configService
    ) {}

    public static function getEntityFqcn(): string
    {
        return AppSetting::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Configuración')
            ->setEntityLabelInPlural('Configuraciones del Sistema')
            ->setSearchFields(['key', 'description', 'category'])
            ->setDefaultSort(['category' => 'ASC', 'key' => 'ASC'])
            ->setPaginatorPageSize(30)
            ->showEntityActionsInlined()
            ->setHelp('index', 'Gestiona las configuraciones del sistema de forma centralizada. Los valores encriptados se guardan de forma segura.');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
                return $action->setIcon('fa fa-eye')->setLabel('Ver');
            })
            ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action->setIcon('fa fa-edit')->setLabel('Editar');
            })
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setIcon('fa fa-trash')->setLabel('Eliminar');
            });
    }

    public function configureFields(string $pageName): iterable
    {
        $isDetail = $pageName === Crud::PAGE_DETAIL;
        $isForm = in_array($pageName, [Crud::PAGE_NEW, Crud::PAGE_EDIT]);

        yield IdField::new('id')
            ->hideOnForm();

        $keyField = TextField::new('key', 'Clave')
            ->setHelp('Identificador único. Formato: categoria.subcategoria.nombre (ej: whatsapp.primary.token)')
            ->setRequired(true);
        if ($isForm) {
            $keyField->setColumns(12);
        }
        yield $keyField;

        $categoryField = ChoiceField::new('category', 'Categoría')
            ->setChoices([
                'General' => AppSetting::CATEGORY_GENERAL,
                'WhatsApp' => AppSetting::CATEGORY_WHATSAPP,
                'Email' => AppSetting::CATEGORY_EMAIL,
                'Integraciones' => AppSetting::CATEGORY_INTEGRATIONS,
                'Seguridad' => AppSetting::CATEGORY_SECURITY,
                'Sistema' => AppSetting::CATEGORY_SYSTEM,
            ])
            ->setRequired(false);
        if ($isForm) {
            $categoryField->setColumns(6);
        }
        yield $categoryField;

        $typeField = ChoiceField::new('type', 'Tipo de Dato')
            ->setChoices([
                'Texto' => AppSetting::TYPE_STRING,
                'Número Entero' => AppSetting::TYPE_INTEGER,
                'Booleano (Si/No)' => AppSetting::TYPE_BOOLEAN,
                'JSON' => AppSetting::TYPE_JSON,
                'Encriptado (Seguro)' => AppSetting::TYPE_ENCRYPTED,
            ])
            ->setHelp('Define cómo se almacenará y procesará el valor')
            ->setRequired(true);
        if ($isForm) {
            $typeField->setColumns(6);
        }
        yield $typeField;

        $valueField = TextareaField::new('value', 'Valor')
            ->setHelp('Para JSON usa formato: {"clave":"valor"}. Los valores encriptados se guardan automáticamente de forma segura.')
            ->setRequired(false)
            ->hideOnIndex(); // Ocultar en lista principal para evitar desbordamiento

        if ($isForm) {
            $valueField->setColumns(12);
        }

        if ($isDetail) {
            // En vista detalle, mostrar valores de forma segura
            $valueField->formatValue(function ($value, AppSetting $entity) {
                if ($entity->getType() === AppSetting::TYPE_ENCRYPTED && $value) {
                    return '****** (Encriptado - ' . strlen($value) . ' caracteres)';
                }
                if ($entity->getType() === AppSetting::TYPE_JSON && $value) {
                    return '<pre>' . json_encode(json_decode($value), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
                }
                return $value;
            });
        }

        yield $valueField;

        $descriptionField = TextareaField::new('description', 'Descripción')
            ->setHelp('Descripción del propósito de esta configuración')
            ->setRequired(false)
            ->hideOnIndex();
        if ($isForm) {
            $descriptionField->setColumns(12);
        }
        yield $descriptionField;

        $publicField = BooleanField::new('isPublic', 'Pública')
            ->setHelp('Si está marcada, puede ser accedida vía API pública (no recomendado para credenciales)');
        if ($isForm) {
            $publicField->setColumns(12);
        }
        yield $publicField;

        yield DateTimeField::new('createdAt', 'Fecha de Creación')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnForm();

        yield DateTimeField::new('updatedAt', 'Última Actualización')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnForm()
            ->hideOnIndex();
    }

    public function updateEntity($entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof AppSetting) {
            // Invalidar caché al actualizar
            $this->configService->clearCache($entityInstance->getKey());
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function deleteEntity($entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof AppSetting) {
            // Invalidar caché al eliminar
            $this->configService->clearCache($entityInstance->getKey());
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }
}
