<?php

namespace App\Controller\Dashboard;

use App\Entity\Tbl17Proveedores;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/proveedor', name: 'proveedor_')]
class ProveedorController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Crear Proveedor/Empresa Solicitante via AJAX - Para Permisos de Trabajo
     */
    #[Route('/create-ajax', name: 'create_ajax', methods: ['POST'])]
    #[IsGranted('ROLE_VIEW_WORK_PERMITS')]
    public function createAjaxAction(Request $request): Response
    {
        $params = $request->request->all();
        $em = $this->entityManager;
        $current_user = $this->getUser();

        // Validación: razon_social y rut_proveedor requeridos
        if (!empty($params['razon_social']) && !empty($params['rut_proveedor'])) {
            // Verificar duplicado por RUT
            $proveedor_exist = $em->getRepository(Tbl17Proveedores::class)->findBy(['rutProveedor' => $params['rut_proveedor']]);

            if (!$proveedor_exist) {
                $proveedor = new Tbl17Proveedores();

                // Campos básicos
                $proveedor->setRazonSocial($params['razon_social']);
                $proveedor->setRutProveedor($params['rut_proveedor']);

                // Auditoría
                $proveedor->setRegStatus(true);
                $proveedor->setCreatedBy($current_user->getId());
                $proveedor->setCreatedAt(new \DateTime());

                $em->persist($proveedor);
                $em->flush();

                return new Response(
                    json_encode(array(
                        'mensaje_success' => 'Se ha creado el proveedor correctamente',
                        'idProveedor' => $proveedor->getIdProveedor()
                    )),
                    200,
                    array('Content-Type' => 'application/json')
                );
            } else {
                // Ya existe
                return new Response(
                    json_encode(array(
                        'mensaje_error' => 'El proveedor con este RUT ya existe',
                        'idProveedor' => $proveedor_exist[0]->getIdProveedor()
                    )),
                    200,
                    array('Content-Type' => 'application/json')
                );
            }
        } else {
            return new Response(
                json_encode(array('mensaje_error' => 'Error: razón social y RUT son obligatorios')),
                200,
                array('Content-Type' => 'application/json')
            );
        }
    }
}
