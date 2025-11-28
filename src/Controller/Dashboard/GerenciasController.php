<?php

namespace App\Controller\Dashboard;

use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use sgv\DashboardBundle\Entity\Tbl28Gerencias;
use sgv\DashboardBundle\Form\Tbl28GerenciasType;

/*   Used by ACL   */

use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use sgv\UserBundle\Controller\SecurityController;

#[Route('/gerencias', name: 'gerencias_')]
class GerenciasController extends BaseController
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
        $filterGerencia = $params['filterGerencia'] ?? false;
        $regStatus = (string)($params['regStatus'] ?? 'true');

        $page = (int)($params['page'] ?? 1);
        $rowsPerPage = (int)($params['rowsPerPage'] ?? 100);
        $limit = (int)($params['limit'] ?? $rowsPerPage);

        // -------------------------------------------------------------------------
        // 2. SEGURIDAD Y SERVICIOS
        // TODO: Migrate to new permission system using PermissionService
        // Old entity 'ntty_28' should map to MODULE_MANAGEMENTS with VIEW permission
        // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_MANAGEMENTS, ModuleVoter::VIEW)
        /*
        // -------------------------------------------------------------------------
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('VIEW', 'ntty_28')) {
            throw new AccessDeniedException();
        }
        */

        $em = $this->entityManager;

        // -------------------------------------------------------------------------
        // 3. CONSTRUCCIÓN DE LA CONSULTA PRINCIPAL (DE FORMA SEGURA)
        // -------------------------------------------------------------------------
        $queryBuilder = $em->getRepository("sgvDashboardBundle:Tbl28Gerencias")->createQueryBuilder('reg');

        // La base del patrón: empezamos con una condición que siempre es verdadera.
        $queryBuilder->where('1=1');

        // Aplicamos cada filtro de forma segura usando andWhere(), solo si la variable tiene contenido.
        if ($filterGerencia) {
            $queryBuilder->andWhere('reg.nombreGerencia LIKE :nombreGerencia')
                ->setParameter('nombreGerencia', '%' . $filterGerencia . '%');
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
                'defaultSortFieldName' => 'reg.nombreGerencia',
                'defaultSortDirection' => 'asc'
            ]
        );

        $deleteFormAjax = $this->createCustomForm(':REG_ID', 'DELETE', 'sgv_gerencias_delete');

        // Renderizamos la vista pasando el array explícito con nuestras variables limpias.
        return $this->render('dashboard/Gerencias/index.html.twig', array(
            'pagination' => $pagination,
            'delete_form_ajax' => $deleteFormAjax->createView(),
            'rowsPerPage' => $rowsPerPage,
            'regStatus' => $regStatus,
            'filterGerencia' => $filterGerencia,
        ));
    }

    //CREA EL FORMULARIO QUE SE UTILIZARÁ PARA CREAR UN NUEVO REGISTRO
    #[Route('/add', name: 'add', methods: ['GET', 'POST'])]

    public function addAction(): Response
    {
        // TODO: Migrate to new permission system using PermissionService
        // Old entity 'ntty_28' should map to MODULE_MANAGEMENTS with CREATE permission
        // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_MANAGEMENTS, ModuleVoter::CREATE)
        /*
        //INICIO ACL--
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('CREATE', 'ntty_28')) {
            throw new AccessDeniedException();
        }// FIN ACL
        */
        $gerencias = new Tbl28Gerencias();
        $form = $this->createCreateForm($gerencias);

        return $this->render('dashboard/Gerencias/add.html.twig', array('form' => $form->createView()));
    }

    //FUNCIÓN PARA CREAR EL FORMULARIO
    private function createCreateForm(Tbl28Gerencias $entity)
    {
        $form = $this->createForm(new Tbl28GerenciasType(), $entity, array(
            'action' => $this->generateUrl('sgv_gerencias_create'),
            'method' => 'POST'
        ));
        return $form;
    }

    //FUNCIÓN PARA CREAR EL REGISTRO EN LA BASE DE DATOS
    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]

    public function createAction(Request $request): Response
    {
        $gerencias = new Tbl28Gerencias();

        // Se pasan los valores a la entidad para auditoria
        $current_user = $this->getUser();
        //dump($current_user->getId());
        $gerencias->SetRegStatus(true);
        $gerencias->SetCreatedBy($current_user->getId());
        $gerencias->SetCreatedAt(new \DateTime());
        //----------------------------------------------------

        $form = $this->createCreateForm($gerencias);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->entityManager;
            $em->persist($gerencias);
            $em->flush();

            $successMessage = $this->translator->trans('La gerencia ha sido creada.');
            $this->addFlash('mensaje', $successMessage);

            return $this->redirectToRoute('sgv_gerencias_index');
        }

        return $this->render('dashboard/Gerencias/add.html.twig', array('form' => $form->createView()));
    }

    //EDITAR REGISTROS
    #[Route('/edit', name: 'edit', methods: ['GET', 'POST'])]

    public function editAction($id): Response
    {
        // TODO: Migrate to new permission system using PermissionService
        // Old entity 'ntty_28' should map to MODULE_MANAGEMENTS with VIEW permission
        // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_MANAGEMENTS, ModuleVoter::VIEW)
        /*
        //INICIO ACL--
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('VIEW', 'ntty_28')) {
            throw new AccessDeniedException();
        }// FIN ACL
        */
        $em = $this->entityManager;
        $gerencias = $em->getRepository('sgvDashboardBundle:Tbl28Gerencias')->find($id);

        if (!$gerencias) {
            $messageException = $this->translator->trans('No se encontró la gerencia.');
            throw $this->createNotFoundException($messageException);
        }

        $form = $this->createEditForm($gerencias);

        return $this->render('dashboard/Gerencias/edit.html.twig', array('gerencia' => $gerencias, 'form' => $form->createView()));

    }

    //CREA FORMULARIO DE EDICIÓN
    private function createEditForm(Tbl28Gerencias $entity)
    {
        $form = $this->createForm(new Tbl28GerenciasType(), $entity, array('action' => $this->generateUrl('sgv_gerencias_update', array('id' => $entity->getidGerencia())), 'method' => 'PUT'));

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
            if (false === $securityController->isGrantedCheck('UNDELETE', 'ntty_08')) {
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
            // Old entity 'ntty_28' should map to MODULE_MANAGEMENTS with EDIT permission
            // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_MANAGEMENTS, ModuleVoter::EDIT)
            /*
            //INICIO ACL--
            $securityController = $this->get('sgv_user.security_controller');
            if (false === $securityController->isGrantedCheck('EDIT', 'ntty_28')) {
                throw new AccessDeniedException();
            }
            */
            // FIN ACL
        }

        $current_user = $this->getUser();
        //-- End For Restore--- -   -   -   -   -   -   -   -   -

        $em = $this->entityManager;

        $gerencias = $em->getRepository('sgvDashboardBundle:Tbl28Gerencias')->find($id);

        if (!$gerencias) {
            $messageException = $this->translator->trans('No se encontró la gerencia');
            throw $this->createNotFoundException($messageException);
        }

        $form = $this->createEditForm($gerencias);
        $form->handleRequest($request);

        // Se pasan los valores a la entidad para el update
        //----------------------------------------------------
        //10-01-2017 JN Se agrega respuestas en JSON para confirmar la restauración del registro a travez de AJAX
        if ($restore) {
            $gerencias->SetRegStatus(true);
            $gerencias->setDeletedRestoredBy($current_user->getId());
            $gerencias->setDeletedRestoredAt(new \DateTime());

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
            $gerencias->SetUpdatedBy($current_user->getId());
            $gerencias->SetUpdatedAt(new \DateTime());
        }
        //----------------------------------------------------

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $successMessage = $this->translator->trans('La gerencia ha sido modificada.');
            $this->addFlash('mensaje', $successMessage);
            return $this->redirectToRoute('sgv_gerencias_index', array('id' => $gerencias->getidGerencia()));
        }
        return $this->render('dashboard/Gerencias/edit.html.twig', array('gerencia' => $gerencias, 'form' => $form->createView()));
    }

//ELIMINAR REGISTRO
    #[Route('/delete', name: 'delete', methods: ['GET', 'POST'])]

    public function deleteAction(Request $request, $id): Response
    {
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('DELETE', 'ntty_28')) {
            $message = $this->translator->trans('No tiene permiso para eliminar el registro.');
            return new Response(
                json_encode(array('removed' => 0, 'message' => $message)),
                200,
                array('Content-Type' => 'application/json')
            );
        }
        $em = $this->entityManager;

        $gerencias = $em->getRepository('sgvDashboardBundle:Tbl28Gerencias')->find($id);

        if (!$gerencias) {
            $messageException = $this->translator->trans('No se encontró la gerencia.');
            throw $this->createNotFoundException($messageException);
        }

        // Se pasan los valores a la entidad para el delete
        $current_user = $this->getUser();
        //dump($current_user->getId());
        $gerencias->SetRegStatus(false);
        $gerencias->setDeletedRestoredBy($current_user->getId());
        $gerencias->SetDeletedRestoredAt(new \DateTime());
        //----------------------------------------------------

        $form = $this->createCustomForm($gerencias->getidGerencia(), 'DELETE', 'sgv_gerencias_delete');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $message = $this->translator->trans('El registro ha sido eliminado.');
            $removed = 1;
            $alert = 'mensaje';

            if ($request->isXMLHttpRequest()) {

                return new Response(
                    json_encode(array('removed' => $removed, 'message' => $message)),
                    200,
                    array('Content-Type' => 'application/json')
                );
            }

            $this->addFlash($alert, $message);
            return $this->redirectToRoute('sgv_gerencias_index');
        }
    }

    private function deleteGerencia($em, $gerencias)
    {
        $em->remove($gerencias);
        $em->flush();

        $message = $this->translator->trans('La gerencia ha sido eliminada.');
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
