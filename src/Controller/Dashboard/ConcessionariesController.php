<?php

namespace App\Controller\Dashboard;

use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use sgv\DashboardBundle\Entity\Tbl06Concesionaria;
use sgv\DashboardBundle\Form\Tbl06ConcesionariaType;

/*   Used by ACL   */

use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use sgv\UserBundle\Controller\SecurityController;

#[Route('/concessionaires', name: 'concessionaires_')]
class ConcessionariesController extends BaseController
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
        $filterConcessionarie = $params['filterConcessionarie'] ?? false;
        $filterRUT = $params['filterRUT'] ?? false;
        $filterAddress = $params['filterAddress'] ?? false;
        $regStatus = (string)($params['regStatus'] ?? 'true');

        $page = (int)($params['page'] ?? 1);
        $rowsPerPage = (int)($params['rowsPerPage'] ?? 100);
        $limit = (int)($params['limit'] ?? $rowsPerPage);

        // -------------------------------------------------------------------------
        // 2. SEGURIDAD Y SERVICIOS
        // TODO: Migrate to new permission system using PermissionService
        // Old entity 'ntty_06' should map to MODULE_CONCESSIONARIES with CREATE permission
        // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_CONCESSIONARIES, ModuleVoter::CREATE)
        /*
        // -------------------------------------------------------------------------
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('CREATE', 'ntty_06')) {
            throw new AccessDeniedException();
        }
        */

        $em = $this->entityManager;

        // -------------------------------------------------------------------------
        // 3. CONSTRUCCIÓN DE LA CONSULTA PRINCIPAL (DE FORMA SEGURA)
        // -------------------------------------------------------------------------
        $queryBuilder = $em->getRepository("sgv\DashboardBundle\Entity\Tbl06Concesionaria")->createQueryBuilder('reg');

        // La base del patrón: empezamos con una condición que siempre es verdadera.
        $queryBuilder->where('1=1');

        // Aplicamos cada filtro de forma segura usando andWhere(), solo si la variable tiene contenido.
        if ($filterConcessionarie) {
            $queryBuilder->andWhere('reg.nombre LIKE :nombre')
                ->setParameter('nombre', '%' . $filterConcessionarie . '%');
        }

        if ($filterRUT) {
            $queryBuilder->andWhere('reg.rutConcesionaria LIKE :rutConcesionaria')
                ->setParameter('rutConcesionaria', '%' . $filterRUT . '%');
        }

        if ($filterAddress) {
            $queryBuilder->andWhere('reg.direccionConcesionaria LIKE :direccionConcesionaria')
                ->setParameter('direccionConcesionaria', '%' . $filterAddress . '%');
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
                'defaultSortFieldName' => 'reg.nombre',
                'defaultSortDirection' => 'asc'
            ]
        );

        $deleteFormAjax = $this->createCustomForm(':REG_ID', 'DELETE', 'sgv_concessionaries_delete');

        // Renderizamos la vista pasando el array explícito con nuestras variables limpias.
        return $this->render('dashboard/Concessionaries/index.html.twig', array(
            'pagination' => $pagination,
            'delete_form_ajax' => $deleteFormAjax->createView(),
            'rowsPerPage' => $rowsPerPage,
            'regStatus' => $regStatus,
            'filterConcessionarie' => $filterConcessionarie,
            'filterRUT' => $filterRUT,
            'filterAddress' => $filterAddress
        ));
    }


    //FUNCIÓN PARA CREAR EL REGISTRO EN LA BASE DE DATOS
    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]

    public function createAction(Request $request): Response
    {
        // TODO: Migrate to new permission system using PermissionService
        // Old entity 'ntty_06' should map to MODULE_CONCESSIONARIES with CREATE permission
        // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_CONCESSIONARIES, ModuleVoter::CREATE)
        /*
        //INICIO ACL--
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('CREATE', 'ntty_06')) {
            throw new AccessDeniedException();
        }// FIN ACL
        */

        $concessionaries = new Tbl06Concesionaria();
        $form = $this->createForm(new Tbl06ConcesionariaType(), $concessionaries, array(
            'action' => $this->generateUrl('sgv_concessionaries_create'),
            'method' => 'POST'
        ));

        // Handle GET request (show form)
        if ($request->getMethod() === 'GET') {
            return $this->render('dashboard/Concessionaries/add.html.twig', array('form' => $form->createView()));
        }

        // Handle POST request (process form)
        $form->handleRequest($request);

        if ($form->isValid()) {
            // Se pasan los valores a la entidad para auditoria
            $current_user = $this->getUser();
            $concessionaries->SetRegStatus(true);
            $concessionaries->SetCreatedBy($current_user->getId());
            $concessionaries->SetCreatedAt(new \DateTime());

            // $file stores the uploaded PDF file
            /** @var Symfony\Component\HttpFoundation\File\UploadedFile $file */
            if ($concessionaries->getLogo()) {
                $file = $concessionaries->getLogo();
                $extension = $file->guessExtension();
                if (!$extension) {
                    $extension = 'png';
                }
                // Generate a unique name for the file before saving it
                $fileName = $this->get('nzo_url_encryptor')->encrypt($concessionaries->getIdConcesionaria()) . '.' . $extension;
                // Move the file to the directory where brochures are stored
                $file->move(
                    $this->getParameter('concessionaries_directory'),
                    $fileName
                );

                // Update the 'brochure' property to store the PDF file name
                // instead of its contents
                $concessionaries->setLogo($fileName);
            }

            $em = $this->entityManager;
            $em->persist($concessionaries);
            $em->flush();

            $successMessage = $this->translator->trans('The concessionarie has been created.');
            $this->addFlash('mensaje', $successMessage);

            return $this->redirectToRoute('sgv_concessionaries_index');
        }

        return $this->render('dashboard/Concessionaries/add.html.twig', array('form' => $form->createView()));
    }

    //EDITAR REGISTROS
    #[Route('/edit', name: 'edit', methods: ['GET', 'POST'])]

    public function editAction($id): Response
    {
        // TODO: Migrate to new permission system using PermissionService
        // Old entity 'ntty_06' should map to MODULE_CONCESSIONARIES with VIEW permission
        // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_CONCESSIONARIES, ModuleVoter::VIEW)
        /*
        //INICIO ACL--
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('VIEW', 'ntty_06')) {
            throw new AccessDeniedException();
        }// FIN ACL
        */
        $em = $this->entityManager;
        $concessionaries = $em->getRepository('sgv\DashboardBundle\Entity\Tbl06Concesionaria')->find($id);

        if (!$concessionaries) {
            $messageException = $this->translator->trans('Concessionarie not found.');
            throw $this->createNotFoundException($messageException);
        }
        /** @var Symfony\Component\HttpFoundation\File\UploadedFile $file */
        //dump($concessionaries->getLogo());
        $form = $this->createEditForm($concessionaries);

        return $this->render('dashboard/Concessionaries/edit.html.twig', array('concessionarie' => $concessionaries, 'form' => $form->createView()));

    }

    //CREA FORMULARIO DE EDICIÓN
    private function createEditForm(Tbl06Concesionaria $entity)
    {
        $form = $this->createForm(new Tbl06ConcesionariaType(),
            $entity,
            array(
                'action' => $this->generateUrl('sgv_concessionaries_update', array('id' => $entity->getidConcesionaria())),
                'method' => 'PUT'
            )
        );

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
            if (false === $securityController->isGrantedCheck('UNDELETE', 'ntty_06')) {
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
            // Old entity 'ntty_06' should map to MODULE_CONCESSIONARIES with EDIT permission
            // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_CONCESSIONARIES, ModuleVoter::EDIT)
            /*
            //INICIO ACL--
            $securityController = $this->get('sgv_user.security_controller');
            if (false === $securityController->isGrantedCheck('EDIT', 'ntty_06')) {
                throw new AccessDeniedException();
            }
            */
            // FIN ACL
        }

        $current_user = $this->getUser();
        //-- End For Restore--- -   -   -   -   -   -   -   -   -

        $em = $this->entityManager;

        $concessionaries = $em->getRepository('sgv\DashboardBundle\Entity\Tbl06Concesionaria')->find($id);

        if (!$concessionaries) {
            $messageException = $this->translator->trans('Concessionarie not found.');
            throw $this->createNotFoundException($messageException);
        }
        $logo = $concessionaries->getLogo();
        //dump($concessionaries->getLogo());
        $form = $this->createEditForm($concessionaries);
        $form->handleRequest($request);
        //dump($form);
        // Se pasan los valores a la entidad para el update
        //----------------------------------------------------
        //10-01-2017 JN Se agrega respuestas en JSON para confirmar la restauración del registro a travez de AJAX
        if ($restore) {
            $concessionaries->SetRegStatus(true);
            $concessionaries->setDeletedRestoredBy($current_user->getId());
            $concessionaries->setDeletedRestoredAt(new \DateTime());

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
            $concessionaries->SetUpdatedBy($current_user->getId());
            $concessionaries->SetUpdatedAt(new \DateTime());
        }
        //----------------------------------------------------

        if ($form->isSubmitted() && $form->isValid()) {
            //dump($concessionaries->getLogo());
            if ($concessionaries->getLogo()) {
                // $file stores the uploaded PDF file
                /** @var Symfony\Component\HttpFoundation\File\UploadedFile $file */
                if ($concessionaries->getLogo()) {
                    $file = $concessionaries->getLogo();
                    dump($file, $file->guessExtension(), $file->getClientOriginalExtension(), $file->getClientOriginalName());
                    $extension = $file->guessExtension();
                    if (!$extension) {
                        $extension = 'png';
                    }
                    // Generate a unique name for the file before saving it
                    $fileName = $this->get('nzo_url_encryptor')->encrypt($concessionaries->getIdConcesionaria()) . '.' . $extension;
                    dump($fileName);
                    // Move the file to the directory where brochures are stored
                    $file->move(
                        $this->getParameter('concessionaries_directory'),
                        $fileName
                    );

                    // Update the 'brochure' property to store the PDF file name
                    // instead of its contents
                    $concessionaries->setLogo($fileName);
                }
            } else {
                $concessionaries->setLogo($logo);
            }

            $em->flush();

            $successMessage = $this->translator->trans('The concessionarie has been modified.');
            $this->addFlash('mensaje', $successMessage);
            return $this->redirectToRoute('sgv_concessionaries_index', array('id' => $concessionaries->getidConcesionaria()));
        }
        return $this->render('dashboard/Concessionaries/edit.html.twig', array('concessionarie' => $concessionaries, 'form' => $form->createView()));
    }

//ELIMINAR REGISTRO
    #[Route('/delete', name: 'delete', methods: ['GET', 'POST'])]

    public function deleteAction(Request $request, $id): Response
    {
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('DELETE', 'ntty_06')) {
            $message = $this->translator->trans('No tiene permiso para eliminar el registro.');
            return new Response(
                json_encode(array('removed' => 0, 'message' => $message)),
                200,
                array('Content-Type' => 'application/json')
            );
        }
        $em = $this->entityManager;

        $concessionaries = $em->getRepository('sgv\DashboardBundle\Entity\Tbl06Concesionaria')->find($id);

        if (!$concessionaries) {
            $messageException = $this->translator->trans('Concessionarie not found.');
            throw $this->createNotFoundException($messageException);
        }

        //$allConcessionaries = $em->getRepository('sgv\DashboardBundle\Entity\Tbl06Concesionaria')->findAll();
        //$countConcessionaries = count($allConcessionaries);

        // Se pasan los valores a la entidad para el delete
        $current_user = $this->getUser();
        //dump($current_user->getId());
        $concessionaries->SetRegStatus(false);
        $concessionaries->setDeletedRestoredBy($current_user->getId());
        $concessionaries->SetDeletedRestoredAt(new \DateTime());
        //----------------------------------------------------

        // $form = $this->createDeleteForm($concessionarie);
        $form = $this->createCustomForm($concessionaries->getidConcesionaria(), 'DELETE', 'sgv_concessionaries_delete');
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
                        if($request->isXMLHttpRequest())
                        {
                            $res = $this->deleteConcessionarie( $em, $concessionaries);

                            return new Response(
                                json_encode(array('removed' => $res['removed'], 'message' => $res['message'], 'countConcessionaries' => $countConcessionaries)),
                                200,
                                array('Content-Type' => 'application/json')
                            );
                        }

                        $res = $this->deleteConcessionarie($em, $concessionaries);
            */
            $this->addFlash($alert, $message);
            return $this->redirectToRoute('sgv_concessionaries_index');
        }
    }

    private function deleteConcessionarie($em, $concessionaries)
    {
        $em->remove($concessionaries);
        $em->flush();

        $message = $this->translator->trans('The concessionarie has been deleted.');
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
