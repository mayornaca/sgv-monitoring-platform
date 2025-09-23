<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class CotNetworkController extends AbstractCrudController
{
    public function __construct(
        private AdminUrlGenerator $adminUrlGenerator
    ) {}

    public static function getEntityFqcn(): string
    {
        // Usamos User como entidad dummy ya que necesitamos algo
        return \App\Entity\User::class;
    }

    #[Route('/admin/cot/network', name: 'cot_network')]
    public function network(): Response
    {
        // Redirigir al index del CRUD con un template personalizado
        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction('index')
            ->set('customView', 'network')
            ->generateUrl();
            
        return $this->redirect($url);
    }
    
    public function index(AdminContext $context)
    {
        // Si es la vista personalizada de network
        if ($context->getRequest()->get('customView') === 'network') {
            return $this->renderNetworkView($context);
        }
        
        // Vista normal del CRUD
        return parent::index($context);
    }
    
    private function renderNetworkView(AdminContext $context): Response
    {
        return $this->render('admin/cot/network.html.twig', [
            'context' => $context,
        ]);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Dispositivo')
            ->setEntityLabelInPlural('Red de Dispositivos')
            ->setPageTitle('index', 'COT Network - Estado de Dispositivos')
            ->setPageTitle('new', 'Agregar Dispositivo')
            ->setPageTitle('edit', 'Editar Dispositivo')
            ->setPageTitle('detail', 'Detalle del Dispositivo')
            ->setDefaultSort(['idDispositivo' => 'DESC'])
            ->setPaginatorPageSize(50);
    }

    public function configureActions(Actions $actions): Actions
    {
        // Acción personalizada para ver el mapa de red
        $viewNetwork = Action::new('viewNetwork', 'Ver Mapa de Red', 'fa fa-network-wired')
            ->linkToRoute('cot_network')
            ->createAsGlobalAction();

        // Acción para actualizar estado
        $refreshStatus = Action::new('refreshStatus', 'Actualizar Estado', 'fa fa-sync')
            ->linkToCrudAction('refreshDeviceStatus')
            ->addCssClass('btn btn-info');

        return $actions
            ->add(Crud::PAGE_INDEX, $viewNetwork)
            ->add(Crud::PAGE_INDEX, $refreshStatus)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setIcon('fa fa-plus')->setLabel('Agregar Dispositivo');
            })
            ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action->setIcon('fa fa-edit');
            })
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setIcon('fa fa-trash');
            });
    }

    public function refreshDeviceStatus(): Response
    {
        // TODO: Implementar lógica para actualizar el estado de los dispositivos
        $this->addFlash('success', 'Estado de dispositivos actualizado');
        
        return $this->redirectToRoute('cot_network');
    }

    public function configureFields(string $pageName): iterable
    {
        // TODO: Configurar campos cuando tengamos la entidad correcta
        return [];
    }
}