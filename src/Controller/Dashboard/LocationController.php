<?php

namespace App\Controller\Dashboard;

use App\Entity\Tbl02Ubicacion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/location', name: 'location_')]
class LocationController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Crear Ubicación via AJAX - Para Permisos de Trabajo
     */
    #[Route('/create-ajax', name: 'create_ajax', methods: ['POST'])]
    #[IsGranted('ROLE_VIEW_WORK_PERMITS')]
    public function createAjaxAction(Request $request): Response
    {
        $params = $request->request->all();
        $em = $this->entityManager;
        $current_user = $this->getUser();

        // Validación: nombre y descripcion requeridos
        if (!empty($params['nombre']) && !empty($params['descripcion'])) {
            $location = new Tbl02Ubicacion();

            // Campos básicos
            $location->setNombre($params['nombre']);
            $location->setDescripcion($params['descripcion']);

            // Concesionaria hardcoded (como en legacy, linea 33 de legacy frm_new_location.html.twig)
            $location->setConcesionaria(22);

            // Auditoría
            $location->setRegStatus(true);
            $location->setCreatedBy($current_user->getId());
            $location->setCreatedAt(new \DateTime());

            $em->persist($location);
            $em->flush();

            return new Response(
                json_encode(array(
                    'mensaje_success' => 'Se ha creado la ubicación correctamente',
                    'idLocation' => $location->getId()
                )),
                200,
                array('Content-Type' => 'application/json')
            );
        } else {
            return new Response(
                json_encode(array('mensaje_error' => 'Error: nombre y descripción son obligatorios')),
                200,
                array('Content-Type' => 'application/json')
            );
        }
    }
}
