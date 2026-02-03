<?php

namespace App\Controller\Dashboard;

use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Tbl17Proveedores;
use App\Form\Tbl17ProveedoresType;

/*   Used by ACL   */

use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use sgv\UserBundle\Controller\SecurityController;

#[Route('/suppliers', name: 'suppliers_')]
#[IsGranted('ROLE_ADMIN')]
class SuppliersController extends BaseController
{
    
    

    //INDEX ENLISTA LOS REGISTROS DESDE LA BASE DE DATOS
    #[Route('', name: 'index', methods: ['GET', 'POST'])]

    public function indexAction(Request $request): Response
    {
        // -------------------------------------------------------------------------
        // 1. OBTENCIÓN Y PREPARACIÓN DE PARÁMETROS
        // -------------------------------------------------------------------------
        if ($request->getMethod() == 'POST') {
            $params = $request->request->all();
        } else {
            $params = $request->query->all();
        }

        // Inicializamos todas las variables locales con valores por defecto seguros.
        $filterSupplier = $params['filterSupplier'] ?? false;
        $filterRUT = $params['filterRUT'] ?? false;
        $filterAddress = $params['filterAddress'] ?? false;
        $filterContactName = $params['filterContactName'] ?? false;
        $regStatus = (string)($params['regStatus'] ?? 'true');

        $page = (int)($params['page'] ?? 1);
        $rowsPerPage = (int)($params['rowsPerPage'] ?? 100);
        $limit = (int)($params['limit'] ?? $rowsPerPage);

        // -------------------------------------------------------------------------
        // 2. SEGURIDAD Y SERVICIOS
        // TODO: Migrate to new permission system using PermissionService
        // Old entity 'ntty_17' should map to MODULE_SUPPLIERS with VIEW permission
        // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_SUPPLIERS, ModuleVoter::VIEW)
        /*
        // -------------------------------------------------------------------------
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('VIEW', 'ntty_17')) {
            throw new AccessDeniedException();
        }
        */

        $em = $this->entityManager;

        // -------------------------------------------------------------------------
        // 3. CONSTRUCCIÓN DE LA CONSULTA PRINCIPAL (DE FORMA SEGURA)
        // -------------------------------------------------------------------------
        $queryBuilder = $em->getRepository(Tbl17Proveedores::class)->createQueryBuilder('reg');

        // La base del patrón: empezamos con una condición que siempre es verdadera.
        $queryBuilder->where('1=1');

        // Aplicamos cada filtro de forma segura usando andWhere(), solo si la variable tiene contenido.
        if ($filterSupplier) {
            $queryBuilder->andWhere('reg.razonSocial LIKE :razonSocial')
                ->setParameter('razonSocial', '%' . $filterSupplier . '%');
        }
        if ($filterRUT) {
            $queryBuilder->andWhere('reg.rutProveedor LIKE :rutProveedor')
                ->setParameter('rutProveedor', '%' . $filterRUT . '%');
        }
        if ($filterAddress) {
            $queryBuilder->andWhere('reg.direccionProveedor LIKE :direccionProveedor')
                ->setParameter('direccionProveedor', '%' . $filterAddress . '%');
        }
        if ($filterContactName) {
            $queryBuilder->andWhere('reg.nombreContacto LIKE :nombreContacto')
                ->setParameter('nombreContacto', '%' . $filterContactName . '%');
        }

        if ($regStatus === 'true') {
            $queryBuilder->andWhere('reg.regStatus = :status')->setParameter('status', true);
        } elseif ($regStatus === 'false') {
            $queryBuilder->andWhere('reg.regStatus = :status')->setParameter('status', false);
        }

        // -------------------------------------------------------------------------
        // 4. PAGINACIÓN Y RENDERIZADO
        // -------------------------------------------------------------------------
        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $queryBuilder->getQuery(),
            $page,
            $limit,
            [
                'defaultSortFieldName' => 'reg.razonSocial',
                'defaultSortDirection' => 'asc'
            ]
        );

        $deleteFormAjax = $this->createCustomForm(':REG_ID', 'DELETE', 'suppliers_delete');

        // Renderizamos la vista pasando el array explícito con nuestras variables limpias.
        return $this->render('dashboard/Suppliers/index.html.twig', array(
            'pagination' => $pagination,
            'delete_form_ajax' => $deleteFormAjax->createView(),
            'rowsPerPage' => $rowsPerPage,
            'regStatus' => $regStatus,
            'filterSupplier' => $filterSupplier,
            'filterRUT' => $filterRUT,
            'filterAddress' => $filterAddress,
            'filterContactName' => $filterContactName
        ));
    }

    //CREA EL FORMULARIO QUE SE UTILIZARÁ PARA CREAR UN NUEVO REGISTRO
    #[Route('/add', name: 'add', methods: ['GET', 'POST'])]

    public function addAction(): Response
    {
        // TODO: Migrate to new permission system using PermissionService
        // Old entity 'ntty_17' should map to MODULE_SUPPLIERS with CREATE permission
        // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_SUPPLIERS, ModuleVoter::CREATE)
        /*
        //INICIO ACL--
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('CREATE', 'ntty_17')) {
            throw new AccessDeniedException();
        }// FIN ACL
        */
        $suppliers = new Tbl17Proveedores();
        $form = $this->createCreateForm($suppliers);

        return $this->render('dashboard/Suppliers/add.html.twig', array('form' => $form->createView()));
    }

    //FUNCIÓN PARA CREAR EL FORMULARIO
    private function createCreateForm(Tbl17Proveedores $entity)
    {
        $form = $this->createForm(Tbl17ProveedoresType::class, $entity, array(
            'action' => $this->generateUrl('suppliers_create'),
            'method' => 'POST'
        ));
        return $form;
    }

    //FUNCIÓN PARA CREAR EL REGISTRO EN LA BASE DE DATOS
    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]

    public function createAction(Request $request): Response
    {
        $suppliers = new Tbl17Proveedores();

        // Se pasan los valores a la entidad para auditoria
        $current_user = $this->getUser();
        //dump($current_user->getId());
        $suppliers->SetRegStatus(true);
        $suppliers->SetCreatedBy($current_user->getId());
        $suppliers->SetCreatedAt(new \DateTime());
        //----------------------------------------------------

        $form = $this->createCreateForm($suppliers);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->entityManager;
            $em->persist($suppliers);
            $em->flush();

            $successMessage = $this->translator->trans('El proveedor ha sido creado.');
            $this->addFlash('mensaje', $successMessage);

            return $this->redirectToRoute('suppliers_index');
        }

        return $this->render('dashboard/Suppliers/add.html.twig', array('form' => $form->createView()));
    }

    //EDITAR REGISTROS
    #[Route('/edit', name: 'edit', methods: ['GET', 'POST'])]

    public function editAction($id): Response
    {
        // TODO: Migrate to new permission system using PermissionService
        // Old entity 'ntty_17' should map to MODULE_SUPPLIERS with VIEW permission
        // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_SUPPLIERS, ModuleVoter::VIEW)
        /*
        //INICIO ACL--
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('VIEW', 'ntty_17')) {
            throw new AccessDeniedException();
        }// FIN ACL
        */
        $em = $this->entityManager;
        $suppliers = $em->getRepository(Tbl17Proveedores::class)->find($id);

        if (!$suppliers) {
            $messageException = $this->translator->trans('User not found.');
            throw $this->createNotFoundException($messageException);
        }

        $form = $this->createEditForm($suppliers);

        return $this->render('dashboard/Suppliers/edit.html.twig', array('supplier' => $suppliers, 'form' => $form->createView()));

    }

    //CREA FORMULARIO DE EDICIÓN
    private function createEditForm(Tbl17Proveedores $entity)
    {
        $form = $this->createForm(Tbl17ProveedoresType::class, $entity, array('action' => $this->generateUrl('suppliers_update', array('id' => $entity->getidProveedor())), 'method' => 'PUT'));

        return $form;
    }

    //SE ACTUALIZAN LOS VALORES DEL FORMULARIO EN LA BASE DE DATOS
    #[Route('/update', name: 'update', methods: ['GET', 'POST'])]

    public function updateAction($id, Request $request): Response
    {
        //-- Begin For Restore--- -   -   -   -   -   -   -   -   -
        if ($request->request->get('restore') == "true") {
            //INICIO ACL -UNDELETE-
            $securityController = $this->get('sgv_user.security_controller');
            if (false === $securityController->isGrantedCheck('UNDELETE', 'ntty_17')) {
                $successMessage = $this->translator->trans('No tiene permiso para restaurar el registro.');
                return new Response(
                    json_encode(array('restored' => 0, 'mensaje' => $successMessage)),
                    200,
                    array('Content-Type' => 'application/json')
                );
            }
            // FIN ACL
            $restore = true;
        } else {
            $restore = false;
            // TODO: Migrate to new permission system using PermissionService
            // Old entity 'ntty_17' should map to MODULE_SUPPLIERS with EDIT permission
            // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_SUPPLIERS, ModuleVoter::EDIT)
            /*
            //INICIO ACL--
            $securityController = $this->get('sgv_user.security_controller');
            if (false === $securityController->isGrantedCheck('EDIT', 'ntty_17')) {
                throw new AccessDeniedException();
            }
            */
            // FIN ACL
        }

        $current_user = $this->getUser();
        //-- End For Restore--- -   -   -   -   -   -   -   -   -

        $em = $this->entityManager;

        $suppliers = $em->getRepository(Tbl17Proveedores::class)->find($id);

        if (!$suppliers) {
            $messageException = $this->translator->trans('User not found.');
            throw $this->createNotFoundException($messageException);
        }

        $form = $this->createEditForm($suppliers);
        $form->handleRequest($request);

        // Se pasan los valores a la entidad para el update
        //----------------------------------------------------
        //10-01-2017 JN Se agrega respuestas en JSON para confirmar la restauración del registro a travez de AJAX
        if ($restore) {
            $suppliers->SetRegStatus(true);
            $suppliers->setDeletedRestoredBy($current_user->getId());
            $suppliers->setDeletedRestoredAt(new \DateTime());

            //10-01-2017 JN Se agrega respuestas en JSON para confirmar la restauración del registro a travez de AJAX
            $em->flush();
            $successMessage = $this->translator->trans('El registro ha sido restaurado satisfactoriamente.');

            return new Response(
                json_encode(array('mensaje' => $successMessage)),
                200,
                array('Content-Type' => 'application/json')
            );
        } else {
            //dump('paso por update');
            $suppliers->SetUpdatedBy($current_user->getId());
            $suppliers->SetUpdatedAt(new \DateTime());
        }
        //----------------------------------------------------

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $successMessage = $this->translator->trans('El proveedor ha sido modificado.');
            $this->addFlash('mensaje', $successMessage);
            return $this->redirectToRoute('suppliers_index', array('id' => $suppliers->getidProveedor()));
        }
        return $this->render('dashboard/Suppliers/edit.html.twig', array('supplier' => $suppliers, 'form' => $form->createView()));
    }

//ELIMINAR REGISTRO
    #[Route('/delete', name: 'delete', methods: ['GET', 'POST'])]

    public function deleteAction(Request $request, $id): Response
    {
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('DELETE', 'ntty_17')) {
            $message = $this->translator->trans('No tiene permiso para eliminar el registro.');
            return new Response(
                json_encode(array('removed' => 0, 'message' => $message)),
                200,
                array('Content-Type' => 'application/json')
            );
        }
        $em = $this->entityManager;

        $suppliers = $em->getRepository(Tbl17Proveedores::class)->find($id);

        if (!$suppliers) {
            $messageException = $this->translator->trans('Supplier not found.');
            throw $this->createNotFoundException($messageException);
        }

        // Se pasan los valores a la entidad para el delete
        $current_user = $this->getUser();
        //dump($current_user->getId());
        $suppliers->SetRegStatus(false);
        $suppliers->setDeletedRestoredBy($current_user->getId());
        $suppliers->SetDeletedRestoredAt(new \DateTime());
        //----------------------------------------------------

        $form = $this->createCustomForm($suppliers->getidProveedor(), 'DELETE', 'suppliers_delete');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $message = $this->translator->trans('El registro ha sido eliminado.');
            $removed = 1;
            $alert = 'mensaje';

            if ($request->isXMLHttpRequest()) {
                //$res = $this->deleteBrand( $em, $brands);

                return new Response(
                    json_encode(array('removed' => $removed, 'message' => $message)),
                    200,
                    array('Content-Type' => 'application/json')
                );
            }
            $this->addFlash($alert, $message);

            /*
                    $allSuppliers = $em->getRepository(Tbl17Proveedores::class)->findAll();
                    $countSuppliers = count($allSuppliers);

                    // $form = $this->createDeleteForm($user);
                    $form = $this->createCustomForm($suppliers->getidProveedor(), 'DELETE', 'suppliers_delete');
                    $form->handleRequest($request);

                    if($form->isSubmitted() && $form->isValid())
                    {
                        if($request->isXMLHttpRequest())
                        {
                            $res = $this->deleteSupplier( $em, $suppliers);

                            return new Response(
                                json_encode(array('removed' => $res['removed'], 'message' => $res['message'], 'countSuppliers' => $countSuppliers)),
                                200,
                                array('Content-Type' => 'application/json')
                            );
                        }

                        $res = $this->deleteSupplier($em, $suppliers);

                        $this->addFlash($res['alert'], $res['message']);
            */
            return $this->redirectToRoute('suppliers_index');
        }
    }

    private function deleteSupplier($em, $suppliers)
    {
        $em->remove($suppliers);
        $em->flush();

        $message = $this->translator->trans('El proveedor ha sido Eliminado.');
        $removed = 1;
        $alert = 'mensaje';

        return array('removed' => $removed, 'message' => $message, 'alert' => $alert);
    }

    private function createCustomForm($id, $method, $route)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl($route, array('id' => $id)))
            ->setMethod($method)
            ->getForm();
    }

}
