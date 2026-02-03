<?php

namespace App\Controller\Dashboard;

use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use sgv\DashboardBundle\Entity\Tbl18DispositivosGps;
use sgv\DashboardBundle\Form\Tbl18DispositivosGpsType;

#[Route('/gps_devices', name: 'gps_devices_')]
class GPSDevicesController extends BaseController
{
    
    

    //INDEX ENLISTA LOS REGISTROS DESDE LA BASE DE DATOS
    #[Route('', name: 'index', methods: ['GET', 'POST'])]

    public function indexAction(Request $request): Response
    {
        //return $this->render('dashboard/GPSDevices/index.html.twig', array('name' => "HOLO"));
        $em = $this->entityManager; //conexión con los servicios de datos
        $dql = "SELECT reg FROM sgvDashboardBundle:Tbl18DispositivosGps reg ORDER BY reg.idDispositivoGps ASC";
        $gpsdevices = $em->createQuery($dql);
        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $gpsdevices,
            $request->query->getInt('page', 1),
            25
        );
        //return $this->render('dashboard/GPSDevices/index.html.twig', array('pagination' => $pagination));

        $deleteFormAjax = $this->createCustomForm(':REG_ID', 'DELETE', 'sgv_gpsdevices_delete');

        return $this->render('dashboard/GPSDevices/index.html.twig', array('pagination' => $pagination, 'delete_form_ajax' => $deleteFormAjax->createView()));
    }

    //CREA EL FORMULARIO QUE SE UTILIZARÁ PARA CREAR UN NUEVO REGISTRO
    #[Route('/add', name: 'add', methods: ['GET', 'POST'])]

    public function addAction(): Response
    {
        $gpsdevices = new Tbl18DispositivosGps();
        $form = $this->createCreateForm($gpsdevices);

        return $this->render('dashboard/GPSDevices/add.html.twig',array('form' => $form->createView()));
    }
                //FUNCIÓN PARA CREAR EL FORMULARIO
                private function createCreateForm(Tbl18DispositivosGps $entity)
                {
                    $form = $this->createForm(new Tbl18DispositivosGpsType(), $entity, array(
                        'action' => $this->generateUrl('sgv_gpsdevices_create'),
                        'method' => 'POST'
                    ));
                    return $form;
                }

                //FUNCIÓN PARA CREAR EL REGISTRO EN LA BASE DE DATOS
                #[Route('/create', name: 'create', methods: ['GET', 'POST'])]

                public function createAction(Request $request): Response
                {
                    $gpsdevices = new Tbl18DispositivosGps();

                    // Se pasan los valores a la entidad para auditoria
                    $current_user = $this->getUser();
                    //dump($current_user->getId());
                    $gpsdevices->SetRegStatus(true);
                    $gpsdevices->SetCreatedBy($current_user->getId());
                    $gpsdevices->SetCreatedAt(new \DateTime());
                    //----------------------------------------------------

                    $form = $this->createCreateForm($gpsdevices);
                    $form->handleRequest($request);

                    if($form->isValid())
                    {
                            $em = $this->entityManager;
                            $em->persist($gpsdevices);
                            $em->flush();

                            $successMessage = $this->get('translator')->trans('The gps device has been created.');
                            $this->addFlash('mensaje', $successMessage);

                            return $this->redirectToRoute('sgv_gpsdevices_index');
                    }

                    return $this->render('dashboard/GPSDevices/add.html.twig', array('form' => $form->createView()));
                }
    //EDITAR REGISTROS
    #[Route('/edit', name: 'edit', methods: ['GET', 'POST'])]

    public function editAction($id): Response
    {
        $em = $this->entityManager;
        $gpsdevices = $em->getRepository('sgv\DashboardBundle\Entity\Tbl18DispositivosGps')->find($id);

        if(!$gpsdevices)
        {
            $messageException = $this->get('translator')->trans('User not found.');
            throw $this->createNotFoundException($messageException);
        }

        $form = $this->createEditForm($gpsdevices);

        return $this->render('dashboard/GPSDevices/edit.html.twig', array('gpsdevice' => $gpsdevices, 'form' => $form->createView()));

    }
            //CREA FORMULARIO DE EDICIÓN
            private function createEditForm(Tbl18DispositivosGps $entity)
            {
                $form = $this->createForm(new Tbl18DispositivosGpsType(), $entity, array('action' => $this->generateUrl('sgv_gpsdevices_update', array('id' => $entity->getidDispositivoGps())), 'method' => 'PUT'));

                return $form;
            }
            //SE ACTUALIZAN LOS VALORES DEL FORMULARIO EN LA BASE DE DATOS
            #[Route('/update', name: 'update', methods: ['GET', 'POST'])]

            public function updateAction($id, Request $request): Response
            {
                //-- Begin For Restore--- -   -   -   -   -   -   -   -   -
                if($request->request->get('restore') == "true")
                {
                    $restore = true;
                }
                else
                {
                    $restore = false;
                }

                $current_user = $this->getUser();
                //-- End For Restore--- -   -   -   -   -   -   -   -   -

                $em = $this->entityManager;

                $gpsdevices = $em->getRepository('sgv\DashboardBundle\Entity\Tbl18DispositivosGps')->find($id);

                if(!$gpsdevices)
                {
                    $messageException = $this->get('translator')->trans('GPS Device not found.');
                    throw $this->createNotFoundException($messageException);
                }

                $form = $this->createEditForm($gpsdevices);
                $form->handleRequest($request);

                // Se pasan los valores a la entidad para el update
                //----------------------------------------------------
                //10-01-2017 JN Se agrega respuestas en JSON para confirmar la restauración del registro a travez de AJAX
                if($restore)
                {
                    $gpsdevices->SetRegStatus(true);
                    $gpsdevices->setDeletedRestoredBy($current_user->getId());
                    $gpsdevices->setDeletedRestoredAt(new \DateTime());

                    //10-01-2017 JN Se agrega respuestas en JSON para confirmar la restauración del registro a travez de AJAX
                    $em->flush();
                    $successMessage = $this->get('translator')->trans('El registro ha sido restaurado satisfactoriamente.');

                    return new Response(
                        json_encode(array( 'mensaje' => $successMessage)),
                        200,
                        array('Content-Type' => 'application/json')
                    );
                }
                else
                {
                    //dump('paso por update');
                    $gpsdevices->SetUpdatedBy($current_user->getId());
                    $gpsdevices->SetUpdatedAt(new \DateTime());
                }
                //----------------------------------------------------

                if($form->isSubmitted() && $form->isValid())
                {
                    $em->flush();

                    $successMessage = $this->get('translator')->trans('The gps device has been modified.');
                    $this->addFlash('mensaje', $successMessage);
                    return $this->redirectToRoute('sgv_gpsdevices_index', array('id' => $gpsdevices->getidDispositivoGps()));
                }
                return $this->render('dashboard/GPSDevices/edit.html.twig', array('gpsdevice' => $gpsdevices, 'form' => $form->createView()));
            }
//ELIMINAR REGISTRO
    #[Route('/delete', name: 'delete', methods: ['GET', 'POST'])]

    public function deleteAction(Request $request, $id): Response
    {
        $em = $this->entityManager;

        $gpsdevices = $em->getRepository('sgv\DashboardBundle\Entity\Tbl18DispositivosGps')->find($id);

        if(!$gpsdevices)
        {
            $messageException = $this->get('translator')->trans('GPS Device not found.');
            throw $this->createNotFoundException($messageException);
        }

        // Se pasan los valores a la entidad para el delete
        $current_user = $this->getUser();
        //dump($current_user->getId());
        $gpsdevices->SetRegStatus(false);
        $gpsdevices->setDeletedRestoredBy($current_user->getId());
        $gpsdevices->SetDeletedRestoredAt(new \DateTime());
        //----------------------------------------------------

        $form = $this->createCustomForm($gpsdevices->getidDispositivoGps(), 'DELETE', 'sgv_gpsdevices_delete');
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid())
        {
            $em->flush();

            $message = $this->get('translator')->trans('El registro ha sido eliminado.');
            $removed = 1;
            $alert = 'mensaje';

            if($request->isXMLHttpRequest())
            {
                //$res = $this->deleteBrand( $em, $brands);

                return new Response(
                    json_encode(array('removed' => $removed, 'message' => $message)),
                    200,
                    array('Content-Type' => 'application/json')
                );
            }
            $this->addFlash($alert, $message);

/*
        $allGPSDevices = $em->getRepository('sgv\DashboardBundle\Entity\Tbl18DispositivosGps')->findAll();
        $countGPSDevices = count($allGPSDevices);

        // $form = $this->createDeleteForm($user);
        $form = $this->createCustomForm($gpsdevices->getidDispositivoGps(), 'DELETE', 'sgv_gpsdevices_delete');
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid())
        {
            if($request->isXMLHttpRequest())
            {
                $res = $this->deleteGPSDevice( $em, $gpsdevices);

                return new Response(
                    json_encode(array('removed' => $res['removed'], 'message' => $res['message'], 'countGPSDevices' => $countGPSDevices)),
                    200,
                    array('Content-Type' => 'application/json')
                );
            }

            $res = $this->deleteGPSDevice($em, $gpsdevices);

            $this->addFlash($res['alert'], $res['message']);
*/
            return $this->redirectToRoute('sgv_gpsdevices_index');
        }
    }

    private function deleteGPSDevice($em, $gpsdevices)
    {
        $em->remove($gpsdevices);
        $em->flush();

        $message = $this->get('translator')->trans('The gps device has been deleted.');
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
