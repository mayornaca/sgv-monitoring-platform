<?php

namespace App\Controller\Dashboard;

use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use sgv\DashboardBundle\Entity\Tbl08Areas;
use sgv\DashboardBundle\Form\Tbl08AreasType;

/*   Uso por ACL   */

use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use sgv\UserBundle\Controller\SecurityController;

#[Route('/areas', name: 'areas_')]
class AreasController extends BaseController
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
        $filterArea = $params['filterArea'] ?? false;
        $regStatus = (string)($params['regStatus'] ?? 'true');

        $page = (int)($params['page'] ?? 1);
        $rowsPerPage = (int)($params['rowsPerPage'] ?? 100);
        $limit = (int)($params['limit'] ?? $rowsPerPage);

        // -------------------------------------------------------------------------
        // 2. SEGURIDAD Y SERVICIOS
        // TODO: Migrate to new permission system using PermissionService
        // Old entity 'ntty_08' should map to MODULE_AREAS with VIEW permission
        // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_AREAS, ModuleVoter::VIEW)
        /*
        // -------------------------------------------------------------------------
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('VIEW', 'ntty_08')) {
            throw new AccessDeniedException();
        }
        */

        $em = $this->entityManager;

        // -------------------------------------------------------------------------
        // 3. CONSTRUCCIÓN DE LA CONSULTA PRINCIPAL (DE FORMA SEGURA)
        // -------------------------------------------------------------------------
        $queryBuilder = $em->getRepository("sgvDashboardBundle:Tbl08Areas")->createQueryBuilder('reg');

        // La base del patrón: empezamos con una condición que siempre es verdadera.
        $queryBuilder->where('1=1');

        // Aplicamos cada filtro de forma segura usando andWhere(), solo si la variable tiene contenido.
        if ($filterArea) {
            $queryBuilder->andWhere('reg.nombreArea LIKE :nombreArea')
                ->setParameter('nombreArea', '%' . $filterArea . '%');
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
                'defaultSortFieldName' => 'reg.nombreArea',
                'defaultSortDirection' => 'asc'
            ]
        );

        $deleteFormAjax = $this->createCustomForm(':REG_ID', 'DELETE', 'sgv_areas_delete');

        // Renderizamos la vista pasando el array explícito con nuestras variables limpias.
        return $this->render('dashboard/Areas/index.html.twig', array(
            'pagination' => $pagination,
            'delete_form_ajax' => $deleteFormAjax->createView(),
            'rowsPerPage' => $rowsPerPage,
            'regStatus' => $regStatus,
            'filterArea' => $filterArea,
        ));
    }


    //FUNCIÓN PARA CREAR EL REGISTRO EN LA BASE DE DATOS
    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]

    public function createAction(Request $request): Response
    {
        // TODO: Migrate to new permission system using PermissionService
        // Old entity 'ntty_08' should map to MODULE_AREAS with CREATE permission
        // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_AREAS, ModuleVoter::CREATE)
        /*
        //INICIO ACL--
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('CREATE', 'ntty_08')) {
            throw new AccessDeniedException();
        }// FIN ACL
        */

        $areas = new Tbl08Areas();
        $form = $this->createForm(new Tbl08AreasType(), $areas, array(
            'action' => $this->generateUrl('sgv_areas_create'),
            'method' => 'POST'
        ));

        // Handle GET request (show form)
        if ($request->getMethod() === 'GET') {
            return $this->render('dashboard/Areas/edit.html.twig', array('form' => $form->createView()));
        }

        // Handle POST request (process form)
        $form->handleRequest($request);

        if ($form->isValid()) {
            // Se pasan los valores a la entidad para auditoria
            $current_user = $this->getUser();
            $areas->SetRegStatus(true);
            $areas->SetCreatedBy($current_user->getId());
            $areas->SetCreatedAt(new \DateTime());

            $em = $this->entityManager;
            $em->persist($areas);
            $em->flush();

            $successMessage = $this->translator->trans('The area has been created.');
            $this->addFlash('mensaje', $successMessage);

            return $this->redirectToRoute('sgv_areas_index');
        }

        return $this->render('dashboard/Areas/edit.html.twig', array('form' => $form->createView()));
    }

    //EDITAR REGISTROS
    #[Route('/edit', name: 'edit', methods: ['GET', 'POST'])]

    public function editAction($id): Response
    {
        // TODO: Migrate to new permission system using PermissionService
        // Old entity 'ntty_08' should map to MODULE_AREAS with VIEW permission
        // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_AREAS, ModuleVoter::VIEW)
        /*
        //INICIO ACL--
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('VIEW', 'ntty_08')) {
            throw new AccessDeniedException();
        }
        */
        // FIN ACL
        $em = $this->entityManager;
        $areas = $em->getRepository('sgvDashboardBundle:Tbl08Areas')->find($id);

        if (!$areas) {
            $messageException = $this->translator->trans('Area not found.');
            throw $this->createNotFoundException($messageException);
        }

        $form = $this->createEditForm($areas);

        return $this->render('dashboard/Areas/edit.html.twig', array('area' => $areas, 'form' => $form->createView()));

    }

    //CREA FORMULARIO DE EDICIÓN
    private function createEditForm(Tbl08Areas $entity)
    {
        $form = $this->createForm(new Tbl08AreasType(), $entity, array('action' => $this->generateUrl('sgv_areas_update', array('id' => $entity->getidArea())), 'method' => 'PUT'));

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
            // Old entity 'ntty_08' should map to MODULE_AREAS with EDIT permission
            // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_AREAS, ModuleVoter::EDIT)
            /*
            //INICIO ACL--
            $securityController = $this->get('sgv_user.security_controller');
            if (false === $securityController->isGrantedCheck('EDIT', 'ntty_08')) {
                throw new AccessDeniedException();
            }
            */
            // FIN ACL
        }

        $current_user = $this->getUser();
        //-- End For Restore--- -   -   -   -   -   -   -   -   -

        $em = $this->entityManager;

        $areas = $em->getRepository('sgvDashboardBundle:Tbl08Areas')->find($id);

        if (!$areas) {
            $messageException = $this->translator->trans('Area not found.');
            throw $this->createNotFoundException($messageException);
        }

        $form = $this->createEditForm($areas);
        $form->handleRequest($request);

        // Se pasan los valores a la entidad para el update
        //----------------------------------------------------
        //10-01-2017 JN Se agrega respuestas en JSON para confirmar la restauración del registro a travez de AJAX
        if ($restore) {
            $areas->SetRegStatus(true);
            $areas->setDeletedRestoredBy($current_user->getId());
            $areas->setDeletedRestoredAt(new \DateTime());

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
            $areas->SetUpdatedBy($current_user->getId());
            $areas->SetUpdatedAt(new \DateTime());
        }
        //----------------------------------------------------

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $successMessage = $this->translator->trans('The area has been modified.');
            $this->addFlash('mensaje', $successMessage);
            return $this->redirectToRoute('sgv_areas_index', array('id' => $areas->getidArea()));
        }
        return $this->render('dashboard/Areas/edit.html.twig', array('area' => $areas, 'form' => $form->createView()));
    }

//ELIMINAR REGISTRO
    #[Route('/delete', name: 'delete', methods: ['GET', 'POST'])]

    public function deleteAction(Request $request, $id): Response
    {
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('DELETE', 'ntty_08')) {
            $message = $this->translator->trans('No tiene permiso para eliminar el registro.');
            return new Response(
                json_encode(array('removed' => 0, 'message' => $message)),
                200,
                array('Content-Type' => 'application/json')
            );
        }
        $em = $this->entityManager;

        $areas = $em->getRepository('sgvDashboardBundle:Tbl08Areas')->find($id);

        if (!$areas) {
            $messageException = $this->translator->trans('Area not found.');
            throw $this->createNotFoundException($messageException);
        }

        // Se pasan los valores a la entidad para el delete
        $current_user = $this->getUser();
        //dump($current_user->getId());
        $areas->SetRegStatus(false);
        $areas->setDeletedRestoredBy($current_user->getId());
        $areas->SetDeletedRestoredAt(new \DateTime());
        //----------------------------------------------------

        $form = $this->createCustomForm($areas->getidArea(), 'DELETE', 'sgv_areas_delete');
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
            /*
                    $allAreas = $em->getRepository('sgvDashboardBundle:Tbl08Areas')->findAll();
                    $countAreas = count($allAreas);

                    // $form = $this->createDeleteForm($user);
                    $form = $this->createCustomForm($areas->getidArea(), 'DELETE', 'sgv_areas_delete');
                    $form->handleRequest($request);

                    if($form->isSubmitted() && $form->isValid())
                    {
                        if($request->isXMLHttpRequest())
                        {
                            $res = $this->deleteArea( $em, $areas);

                            return new Response(
                                json_encode(array('removed' => $res['removed'], 'message' => $res['message'], 'countAreas' => $countAreas)),
                                200,
                                array('Content-Type' => 'application/json')
                            );
                        }

                        $res = $this->deleteArea($em, $areas);

                        $this->addFlash($res['alert'], $res['message']);
            */
            $this->addFlash($alert, $message);
            return $this->redirectToRoute('sgv_areas_index');
        }
    }

    private function deleteArea($em, $areas)
    {
        $em->remove($areas);
        $em->flush();

        $message = $this->translator->trans('The area has been deleted.');
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
