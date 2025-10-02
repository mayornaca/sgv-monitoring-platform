<?php

namespace App\Controller\Dashboard;

use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use sgv\DashboardBundle\Entity\Tbl07CentroDeCosto;
use sgv\DashboardBundle\Form\Tbl07CentroDeCostoType;

/*   Used by ACL   */

use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use sgv\UserBundle\Controller\SecurityController;

#[Route('/cost_centers', name: 'cost_centers_')]
class CostCentersController extends BaseController
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
        $filterCostCenter = $params['filterCostCenter'] ?? false;
        $regStatus = (string)($params['regStatus'] ?? 'true');

        $page = (int)($params['page'] ?? 1);
        $rowsPerPage = (int)($params['rowsPerPage'] ?? 100);
        $limit = (int)($params['limit'] ?? $rowsPerPage);

        // -------------------------------------------------------------------------
        // 2. SEGURIDAD Y SERVICIOS
        // TODO: Migrate to new permission system using PermissionService
        // Old entity 'ntty_07' should map to MODULE_COST_CENTERS with VIEW permission
        // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_COST_CENTERS, ModuleVoter::VIEW)
        /*
        // -------------------------------------------------------------------------
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('VIEW', 'ntty_07')) {
            throw new AccessDeniedException();
        }
        */

        $em = $this->entityManager;

        // -------------------------------------------------------------------------
        // 3. CONSTRUCCIÓN DE LA CONSULTA PRINCIPAL (DE FORMA SEGURA)
        // -------------------------------------------------------------------------
        $queryBuilder = $em->getRepository("sgv\DashboardBundle\Entity\Tbl07CentroDeCosto")->createQueryBuilder('reg');

        // La base del patrón: empezamos con una condición que siempre es verdadera.
        $queryBuilder->where('1=1');

        // Aplicamos cada filtro de forma segura usando andWhere(), solo si la variable tiene contenido.
        if ($filterCostCenter) {
            $queryBuilder->andWhere('reg.nombreCentroDeCosto LIKE :nombreCentroDeCosto')
                ->setParameter('nombreCentroDeCosto', '%' . $filterCostCenter . '%');
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
                'defaultSortFieldName' => 'reg.nombreCentroDeCosto',
                'defaultSortDirection' => 'asc'
            ]
        );

        $deleteFormAjax = $this->createCustomForm(':REG_ID', 'DELETE', 'sgv_costcenters_delete');

        // Renderizamos la vista pasando el array explícito con nuestras variables limpias.
        return $this->render('dashboard/CostCenters/index.html.twig', array(
            'pagination' => $pagination,
            'delete_form_ajax' => $deleteFormAjax->createView(),
            'rowsPerPage' => $rowsPerPage,
            'regStatus' => $regStatus,
            'filterCostCenter' => $filterCostCenter,
        ));
    }


    //FUNCIÓN PARA CREAR EL REGISTRO EN LA BASE DE DATOS
    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]

    public function createAction(Request $request): Response
    {
        // TODO: Migrate to new permission system using PermissionService
        // Old entity 'ntty_07' should map to MODULE_COST_CENTERS with CREATE permission
        // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_COST_CENTERS, ModuleVoter::CREATE)
        /*
        //INICIO ACL--
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('CREATE', 'ntty_07')) {
            throw new AccessDeniedException();
        }// FIN ACL
        */

        $costcenters = new Tbl07CentroDeCosto();
        $form = $this->createForm(new Tbl07CentroDeCostoType(), $costcenters, array(
            'action' => $this->generateUrl('sgv_costcenters_create'),
            'method' => 'POST'
        ));

        // Handle GET request (show form)
        if ($request->getMethod() === 'GET') {
            return $this->render('dashboard/CostCenters/edit.html.twig', array('form' => $form->createView()));
        }

        // Handle POST request (process form)
        $form->handleRequest($request);

        if ($form->isValid()) {
            // Se pasan los valores a la entidad para auditoria
            $current_user = $this->getUser();
            $costcenters->SetCreatedBy($current_user->getId());
            $costcenters->SetCreatedAt(new \DateTime());
            $costcenters->SetRegStatus(true);

            $em = $this->entityManager;
            $em->persist($costcenters);
            $em->flush();

            $successMessage = $this->translator->trans('The cost center has been created.');
            $this->addFlash('mensaje', $successMessage);

            return $this->redirectToRoute('sgv_costcenters_index');
        }

        return $this->render('dashboard/CostCenters/edit.html.twig', array('form' => $form->createView()));
    }

    //EDITAR REGISTROS
    #[Route('/edit', name: 'edit', methods: ['GET', 'POST'])]

    public function editAction($id): Response
    {
        // TODO: Migrate to new permission system using PermissionService
        // Old entity 'ntty_07' should map to MODULE_COST_CENTERS with VIEW permission
        // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_COST_CENTERS, ModuleVoter::VIEW)
        /*
        //INICIO ACL--
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('VIEW', 'ntty_07')) {
            throw new AccessDeniedException();
        }// FIN ACL
        */
        $em = $this->entityManager;
        $costcenters = $em->getRepository('sgv\DashboardBundle\Entity\Tbl07CentroDeCosto')->find($id);

        if (!$costcenters) {
            $messageException = $this->translator->trans('Cost center not found.');
            throw $this->createNotFoundException($messageException);
        }

        $form = $this->createEditForm($costcenters);

        return $this->render('dashboard/CostCenters/edit.html.twig', array('costcenter' => $costcenters, 'form' => $form->createView()));

    }

    //CREA FORMULARIO DE EDICIÓN
    private function createEditForm(Tbl07CentroDeCosto $entity)
    {
        $form = $this->createForm(new Tbl07CentroDeCostoType(), $entity, array('action' => $this->generateUrl('sgv_costcenters_update', array('id' => $entity->getidCentroDeCosto())), 'method' => 'PUT'));

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
            if (false === $securityController->isGrantedCheck('UNDELETE', 'ntty_07')) {
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
            // Old entity 'ntty_07' should map to MODULE_COST_CENTERS with EDIT permission
            // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_COST_CENTERS, ModuleVoter::EDIT)
            /*
            //INICIO ACL--
            $securityController = $this->get('sgv_user.security_controller');
            if (false === $securityController->isGrantedCheck('EDIT', 'ntty_07')) {
                throw new AccessDeniedException();
            }
            */
            // FIN ACL
        }

        $current_user = $this->getUser();
        //-- End For Restore--- -   -   -   -   -   -   -   -   -

        $em = $this->entityManager;

        $costcenters = $em->getRepository('sgv\DashboardBundle\Entity\Tbl07CentroDeCosto')->find($id);

        if (!$costcenters) {
            $messageException = $this->translator->trans('Cost center not found.');
            throw $this->createNotFoundException($messageException);
        }

        $form = $this->createEditForm($costcenters);
        $form->handleRequest($request);

        // Se pasan los valores a la entidad para el update
        //----------------------------------------------------
        //10-01-2017 JN Se agrega respuestas en JSON para confirmar la restauración del registro a travez de AJAX
        if ($restore) {
            $costcenters->SetRegStatus(true);
            $costcenters->setDeletedRestoredBy($current_user->getId());
            $costcenters->setDeletedRestoredAt(new \DateTime());

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
            $costcenters->SetUpdatedBy($current_user->getId());
            $costcenters->SetUpdatedAt(new \DateTime());
        }
        //----------------------------------------------------

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $successMessage = $this->translator->trans('The cost center has been modified.');
            $this->addFlash('mensaje', $successMessage);
            return $this->redirectToRoute('sgv_costcenters_index', array('id' => $costcenters->getidCentroDeCosto()));
        }
        return $this->render('dashboard/CostCenters/edit.html.twig', array('costcenter' => $costcenters, 'form' => $form->createView()));
    }

//ELIMINAR REGISTRO
    #[Route('/delete', name: 'delete', methods: ['GET', 'POST'])]

    public function deleteAction(Request $request, $id): Response
    {
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('DELETE', 'ntty_07')) {
            $message = $this->translator->trans('No tiene permiso para eliminar el registro.');
            return new Response(
                json_encode(array('removed' => 0, 'message' => $message)),
                200,
                array('Content-Type' => 'application/json')
            );
        }
        $em = $this->entityManager;

        $costcenters = $em->getRepository('sgv\DashboardBundle\Entity\Tbl07CentroDeCosto')->find($id);

        if (!$costcenters) {
            $messageException = $this->translator->trans('Cost center not found.');
            throw $this->createNotFoundException($messageException);
        }

        // Se pasan los valores a la entidad para el delete
        $current_user = $this->getUser();
        //dump($current_user->getId());
        $costcenters->SetRegStatus(false);
        $costcenters->setDeletedRestoredBy($current_user->getId());
        $costcenters->SetDeletedRestoredAt(new \DateTime());
        //----------------------------------------------------

        $form = $this->createCustomForm($costcenters->getidCentroDeCosto(), 'DELETE', 'sgv_costcenters_delete');
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
                    $allCostCenters = $em->getRepository('sgv\DashboardBundle\Entity\Tbl07CentroDeCosto')->findAll();
                    $countCostCenters = count($allCostCenters);

                    // $form = $this->createDeleteForm($user);
                    $form = $this->createCustomForm($costcenters->getidCentroDeCosto(), 'DELETE', 'sgv_costcenters_delete');
                    $form->handleRequest($request);

                    if($form->isSubmitted() && $form->isValid())
                    {
                        if($request->isXMLHttpRequest())
                        {
                            $res = $this->deleteCostCenter( $em, $costcenters);

                            return new Response(
                                json_encode(array('removed' => $res['removed'], 'message' => $res['message'], 'countCostCenters' => $countCostCenters)),
                                200,
                                array('Content-Type' => 'application/json')
                            );
                        }

                        $res = $this->deleteCostCenter($em, $costcenters);

                        $this->addFlash($res['alert'], $res['message']);
            */
            return $this->redirectToRoute('sgv_costcenters_index');
        }
    }

    private function deleteCostCenter($em, $costcenters)
    {
        $em->remove($costcenters);
        $em->flush();

        $message = $this->translator->trans('The cost center has been deleted.');
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
