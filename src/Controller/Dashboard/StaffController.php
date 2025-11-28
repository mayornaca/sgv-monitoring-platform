<?php

namespace App\Controller\Dashboard;

use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Tbl06Concesionaria;
use App\Entity\Tbl07CentroDeCosto;
use App\Entity\Tbl08Areas;
use App\Entity\Tbl13LicenciasDeConducir;
use App\Entity\Tbl14Personal;
use App\Form\Tbl14PersonalType;
use App\Entity\Tbl24ConductoresAsignados;
// TODO STEP 6: Uncomment after migrating forms
// use App\Form\Tbl24ConductoresAsignadosType;
use Symfony\Component\Validator\Constraints\DateTime;
// TODO: Check if FuelLoadsController exists in App\Controller\Dashboard
// use App\Controller\Dashboard\FuelLoadsController;
use Doctrine\Common\Collections\ArrayCollection;

/*   Used by ACL   */

use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use sgv\UserBundle\Controller\SecurityController;

#[Route('/staff', name: 'staff_')]
// Security: Métodos individuales definen sus permisos con #[IsGranted()]
class StaffController extends BaseController
{
    
    

    #[Route('/ajax-busca-duplicado', name: 'ajax_busca_duplicado', methods: ['GET', 'POST'])]

    
    

    public function ajaxBuscaDuplicadoAction(Request $request): Response
    {
        $params = $request->request->all();

        if (isset($params['rut'])) {
            $rut = $params['rut'];
            //Tbl14Personal
            $em = $this->entityManager; //conexión con los servicios de datos
            $qbpersonal = $em->createQueryBuilder();
            $qbpersonal->select('reg')
                ->from(Tbl14Personal::class, 'reg')
                ->where('reg.rut = :rut');
            $qbpersonal->setParameter('rut', $rut);

            $personal = $qbpersonal->getQuery()->getArrayResult();

            //$duplicado =
        } else {
            $mensaje_error = 'Error: No se proporcionó un parámetro de búsqueda';
            $personal = false;
        }

        // dump($licencia_valida);

        if ($request->isXMLHttpRequest()) {
            return new Response(
                json_encode(array('staff' => $personal)),
                200,
                array('Content-Type' => 'application/json')
            );
        } else {
            return array('staff' => $personal);
        }
    }

    #[Route('/ajax-get-licencia-de-conducir', name: 'ajax_get_licencia_de_conducir', methods: ['GET', 'POST'])]


    public function ajaxGetLicenciaDeConducirAction(Request $request): Response
    {
        $params = $request->request->all(); //PROBADA OTRA FORMA DE OBTENER LOS PARAMETROS DEL request (POST) (//$request->request->get('name');) o (//$request->request->all();)
        $licencia_valida = false;
        $licencia_vigente = false;
        $vehiculo_sin_licencia = false;
        $conductor_sin_licencia = false;

        // Validamos que los parametros recibidos sean provenientes de el form personal o vehiculos respectivamente
        if ((isset($params['licencias_del_conductor']) and isset($params['id_vehiculo']) and isset($params['fecha_vencimiento_licencias_del_conductor'])) or (isset($params['id_personal']) and isset($params['id_licencia_requerida']))) {
            $em = $this->entityManager; //conexión con los servicios de datos

            // Seteamos parámetros obtenidos desde el formulario de personal
            if (isset($params['licencias_del_conductor']) and isset($params['id_vehiculo']) and isset($params['fecha_vencimiento_licencias_del_conductor'])) {
                //Set Licencias del conductor
                $licencias_del_conductor = $params['licencias_del_conductor'];
                $fecha_vencimiento_licencias_del_conductor = $params['fecha_vencimiento_licencias_del_conductor'];
                // Date Objetc Create JN 18-06-2017
                $year = substr($fecha_vencimiento_licencias_del_conductor, 6, 4);
                $month = substr($fecha_vencimiento_licencias_del_conductor, 3, 2);
                $day = substr($fecha_vencimiento_licencias_del_conductor, 0, 2);

                $fecha_vencimiento_licencias_del_conductor = date_create(date("Y-m-d", mktime(23, 59, 59, $month, $day, $year)));

                $id_vehiculo = $params['id_vehiculo']; // Se obtendrá la licencia requerida al recibir este parámetro

                // Get Tbl10Vehiculos
                if (isset($params['id_vehiculo'])) {
                    $qbvehiculo = $em->createQueryBuilder();
                    $qbvehiculo->select('reg')
                        ->from('sgv\DashboardBundle\Entity\Tbl10Vehiculos', 'reg')
                        ->join('reg.idLicenciaRequerida', 'lic')->addSelect('lic')
                        ->where('reg.idVehiculo = :id_vehiculo');
                    $qbvehiculo->setParameter('id_vehiculo', $id_vehiculo);
                    $vehiculo = $qbvehiculo->getQuery()->getArrayResult();  //dump($vehiculo[0]['idLicenciaRequerida']['idLicencia']);//dump($vehiculo[0]['idLicenciaRequerida']['aliasLicencia']);
                } else {
                    $vehiculo = false;
                }
                // Set Licencia Requerida
                $id_licencia_requerida = $vehiculo[0]['idLicenciaRequerida']['idLicencia'];
            }

            if (isset($params['id_personal']) and isset($params['id_licencia_requerida'])) {
                $id_personal = $params['id_personal'];
                // Get Tbl14Personal
                if (isset($params['id_personal'])) {
                    $qbpersonal = $em->createQueryBuilder();
                    $qbpersonal->select('reg')
                        ->from(Tbl14Personal::class, 'reg')
                        //->join('reg.idLicenciaConducir','lic')->addSelect('lic')    Este campo ya no se utiliza 18-01-2017 JN
                        ->where('reg.idPersonal = :id_personal');
                    $qbpersonal->setParameter('id_personal', $id_personal);
                    $personal = $qbpersonal->getQuery()->getArrayResult();

                } else {
                    $personal = false;
                }
                //Set Licencias del conductor
                $licencias_del_conductor = $personal[0]['licenciasConducir'];
                $fecha_vencimiento_licencias_del_conductor = $personal[0]['fechaVencimientoLicencia'];
                // Set Licencia Requerida
                $id_licencia_requerida = $params['id_licencia_requerida'];
            }

            // Validación de Licencia Vigente
            //dump($fecha_vencimiento_licencias_del_conductor);
            //dump(date_create(date("Y-m-d")));
            //dump(date("Y-m-d"));

            if ($fecha_vencimiento_licencias_del_conductor > date_create(date("Y-m-d"))) {
                $licencia_vigente = true;
            }
            //Todo Establecer diferentes errores para la validación de la licencia de conducir

            // Comprobamos que el conductor posea una licencia válida para ello necesitamos un array con las licencias del conductor y la licencia requerida
            if ($licencias_del_conductor and $id_licencia_requerida) {
                $licencias_conductor = explode(',', $licencias_del_conductor);
                //dump($id_licencia_requerida);
                //dump($licencias_conductor);
                //dump(array_search($id_licencia_requerida, $licencias_conductor));
                if (is_numeric(array_search($id_licencia_requerida, $licencias_conductor)))              //(array_search($id_licencia_requerida, $licencias_conductor) != false or array_search($id_licencia_requerida, $licencias_conductor) >= 0)
                {
                    //dump('es valida simple');
                    $licencia_valida = true;
                } else {
                    foreach ($licencias_conductor as $licencia_conducir) {
                        $detalle_de_licencia = $em->getRepository(Tbl13LicenciasDeConducir::class)->find($licencia_conducir);
                        $compendio_detalle_de_licencia = explode(',', $detalle_de_licencia->getCompendio());
                        //dump($compendio_detalle_de_licencia);
                        //dump(array_search($id_licencia_requerida, $compendio_detalle_de_licencia));
                        if (is_numeric(array_search($id_licencia_requerida, $compendio_detalle_de_licencia)))                    //(array_search($id_licencia_requerida, $compendio_detalle_de_licencia) != false or array_search($id_licencia_requerida, $compendio_detalle_de_licencia) >= 0)
                        {
                            //dump('es valida compleja');
                            $licencia_valida = true;
                        }
                    }
                }
            }//Se añade el manejo de cuando un conductor no tiene una licencia o un vehículo no posee una licencia requerida
            elseif (!$licencias_del_conductor) {
                $conductor_sin_licencia = true;
            } elseif (!$id_licencia_requerida) {
                $vehiculo_sin_licencia = true;
            }

        } else {
            $licencia_valida = 'error';
            $licencia_vigente = 'error';

            //$personal = 'error';
            //$vehiculo = 'error';
        }

        // dump($licencia_valida);
        // Se quita la devolución de las siguientes variables no necesarias
        /* 'personal' => $personal, 'vehiculo' => $vehiculo,*/
        if ($request->isXMLHttpRequest()) {
            return new Response(
                json_encode(array('licencia_valida' => $licencia_valida, 'licencia_vigente' => $licencia_vigente, 'conductor_sin_licencia' => $conductor_sin_licencia, 'vehiculo_sin_licencia' => $vehiculo_sin_licencia)),
                200,
                array('Content-Type' => 'application/json')
            );
        } else {
            // Se quita la devolución de las siguientes variables no necesarias
            /* 'personal' => $personal, 'vehiculo' => $vehiculo,*/
            return array('licencia_valida' => $licencia_valida, 'licencia_vigente' => $licencia_vigente, 'conductor_sin_licencia' => $conductor_sin_licencia, 'vehiculo_sin_licencia' => $vehiculo_sin_licencia);
        }
    }

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
        $filterRUT = $params['filterRUT'] ?? false;
        $filterNombres = $params['filterNombres'] ?? false;
        $filterApellidos = $params['filterApellidos'] ?? false;
        $filterState = $params['filterState'] ?? 'all';
        $regStatus = (string)($params['regStatus'] ?? 'true');

        // Filtros de tipo array
        $costcenters = $params['costcenters'] ?? [];
        $areas = $params['areas'] ?? [];

        // Paginación
        $page = (int)($params['page'] ?? 1);
        $rowsPerPage = (int)($params['rowsPerPage'] ?? 100);
        $limit = (int)($params['limit'] ?? $rowsPerPage);

        // -------------------------------------------------------------------------
        // 2. SEGURIDAD Y CARGA DE DATOS PARA LA VISTA
        // TODO: Migrate to new permission system using PermissionService
        // Old entity 'ntty_14' should map to MODULE_STAFF with VIEW permission
        // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_STAFF, ModuleVoter::VIEW)
        /*
        // -------------------------------------------------------------------------
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('VIEW', 'ntty_14')) {
            throw new AccessDeniedException();
        }
        */

        $em = $this->entityManager;
        $current_user = $this->getUser();

        // Simplificamos la lógica de concesiones
        $defaultConcessions = !empty($current_user->getConcessions()) ? explode(',', $current_user->getConcessions()) : [];
        $concessions = $params['concessions'] ?? $defaultConcessions;

        // Carga de datos para los menús desplegables (dropdowns)
        $all_concessionaries = $em->getRepository(Tbl06Concesionaria::class)->findBy([], ['nombre' => 'ASC']);
        $all_costcenters = $em->getRepository(Tbl07CentroDeCosto::class)->findBy([], ['nombreCentroDeCosto' => 'ASC']);
        $all_areas = $em->getRepository(Tbl08Areas::class)->findBy([], ['nombreArea' => 'ASC']);

        // Lógica para obtener y procesar las licencias
        $licencias = $em->getRepository(Tbl13LicenciasDeConducir::class)->findBy([], ['tipoLicencia' => 'ASC', 'aliasLicencia' => 'ASC']);
        $arr_licencias = [];
        foreach ($licencias as $licencia) {
            // Asumiendo que $licencia es un objeto Entidad
            $arr_licencias[$licencia->getIdLicencia()] = $licencia->getAliasLicencia();
        }

        // -------------------------------------------------------------------------
        // 3. CONSTRUCCIÓN DE LA CONSULTA PRINCIPAL (DE FORMA SEGURA)
        // -------------------------------------------------------------------------
        $queryBuilder = $em->getRepository(Tbl14Personal::class)->createQueryBuilder('reg');

        // La base del patrón: empezamos con una condición que siempre es verdadera.
        $queryBuilder->where('1=1');

        // Aplicamos cada filtro de forma segura usando andWhere(), solo si la variable tiene contenido.
        if (!empty($concessions) && !(count($concessions) == 1 && $concessions[0] == '')) {
            $queryBuilder->andWhere('reg.idConcesionaria IN (:concessions)')->setParameter('concessions', array_map('intval', $concessions));
        }
        if (!empty($costcenters)) {
            $queryBuilder->andWhere('reg.idCentroDeCosto IN (:costcenters)')->setParameter('costcenters', $costcenters);
        }
        if (!empty($areas)) {
            $queryBuilder->andWhere('reg.idArea IN (:areas)')->setParameter('areas', $areas);
        }
        if ($filterRUT) {
            $queryBuilder->andWhere('reg.rut LIKE :rut')->setParameter('rut', '%' . $filterRUT . '%');
        }
        if ($filterNombres) {
            $queryBuilder->andWhere('reg.nombres LIKE :nombres')->setParameter('nombres', '%' . $filterNombres . '%');
        }
        if ($filterApellidos) {
            $queryBuilder->andWhere('reg.apellidos LIKE :apellidos')->setParameter('apellidos', '%' . $filterApellidos . '%');
        }

        // Lógica de estado (corregida)
        if ($filterState === 'activos') {
            // Asumo que el campo se llama 'estado' y es booleano. Ajustar si el nombre es diferente.
            $queryBuilder->andWhere('reg.estado = :statusState')->setParameter('statusState', true);
        } elseif ($filterState === 'inactivos') {
            $queryBuilder->andWhere('reg.estado = :statusState')->setParameter('statusState', false);
        }

        if ($regStatus === 'true') {
            $queryBuilder->andWhere('reg.regStatus = :regStatus')->setParameter('regStatus', true);
        } elseif ($regStatus === 'false') {
            $queryBuilder->andWhere('reg.regStatus = :regStatus')->setParameter('regStatus', false);
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
                'defaultSortFieldName' => 'reg.nombres',
                'defaultSortDirection' => 'asc'
            ]
        );

        $deleteFormAjax = $this->createCustomForm(':REG_ID', 'DELETE', 'staff_delete');

        // Renderizamos la vista pasando el array explícito con nuestras variables limpias.
        return $this->render('dashboard/Staff/index.html.twig', array(
            'pagination' => $pagination,
            'delete_form_ajax' => $deleteFormAjax->createView(),
            'filterRUT' => $filterRUT,
            'filterNombres' => $filterNombres,
            'filterApellidos' => $filterApellidos,
            'rowsPerPage' => $rowsPerPage,
            'filterState' => $filterState,
            'licencias' => $arr_licencias,
            'all_concessionaries' => $all_concessionaries,
            'all_costcenters' => $all_costcenters,
            'all_areas' => $all_areas,
            'concessions' => $concessions,
            'costcenters' => $costcenters,
            'areas' => $areas,
            'regStatus' => $regStatus
        ));
    }

    //INDEX ENLISTA LOS REGISTROS DESDE LA BASE DE DATOS
    /* #[Route('', name: 'index', methods: ['GET', 'POST'])]
 public function indexAction(Request $request): Response
     {
         //return $this->render('dashboard/Staff/index.html.twig', array('name' => "HOLO"));
         $em = $this->entityManager; //conexión con los servicios de datos
         $dql = "SELECT reg FROM sgvDashboardBundle:Tbl14Personal reg ORDER BY reg.idPersonal ASC";
         $staff = $em->createQuery($dql);
         $paginator = $this->get('knp_paginator');
         $pagination = $paginator->paginate(
             $staff,
             $request->query->getInt('page', 1),
             25
         );
         //return $this->render('dashboard/Staff/index.html.twig', array('pagination' => $pagination));

         $deleteFormAjax = $this->createCustomForm(':REG_ID', 'DELETE', 'staff_delete');

         return $this->render('dashboard/Staff/index.html.twig', array('pagination' => $pagination, 'delete_form_ajax' => $deleteFormAjax->createView()));
     }*/

    //CREA EL FORMULARIO QUE SE UTILIZARÁ PARA CREAR UN NUEVO REGISTRO
    // DUPLICATE ROUTE REMOVED - use 'create' route instead

    //FUNCIÓN PARA CREAR EL REGISTRO EN LA BASE DE DATOS
    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]

    public function createAction(Request $request): Response
    {
        $em = $this->entityManager;
        $query_builder_licencias = $em->createQueryBuilder();
        $query_builder_licencias->select('reg')
            ->from(Tbl13LicenciasDeConducir::class, 'reg')
            //->groupBy('reg.tipoLicencia')
            ->orderBy('reg.tipoLicencia', 'ASC')
            ->orderBy('reg.aliasLicencia', 'ASC');
        $licencias = $query_builder_licencias->getQuery()->getResult();
        $staff = new Tbl14Personal();

        // Se pasan los valores a la entidad para auditoria
        $current_user = $this->getUser();
        //dump($current_user->getId());
        $staff->SetRegStatus(true);
        $staff->SetCreatedBy($current_user->getId());
        $staff->SetCreatedAt(new \DateTime());
        //----------------------------------------------------

        $form = $this->createCreateForm($staff);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // $file stores the uploaded file
            /** @var Symfony\Component\HttpFoundation\File\UploadedFile $file */
            if ($staff->getPhoto()) {
                $file = $staff->getPhoto();

                // Generate a unique name for the file before saving it
                $fileName = md5(uniqid('', true)) . '.' . $file->guessExtension();
                $file->move(
                    $this->getParameter('staff_directory'),
                    $fileName
                );

                // Update the 'brochure' property to store the PDF file name
                // instead of its contents
                $staff->setPhoto($fileName);
            }

            $em = $this->entityManager;
            $em->persist($staff);
            $em->flush();

            $successMessage = $this->translator->trans('El colaborador ha sido creado correctamente, ahora puede asignar vehículos.');
            $this->addFlash('mensaje', $successMessage);

            return $this->redirect($this->generateUrl('staff_edit', array('id' => $staff->getIdPersonal())));
            //return $this->redirectToRoute('staff_index');
        }

        return $this->render('dashboard/Staff/add.html.twig', array(
            'form' => $form->createView(),
            'licencias' => $licencias
        ));
    }

    #[Route('/create-ajax', name: 'create_ajax', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_VIEW_WORK_PERMITS')]
    public function createAjaxAction(Request $request): Response
    {
        // TODO: Migrate to new permission system using PermissionService
        // Old entity 'ntty_14' should map to MODULE_STAFF with CREATE permission
        // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_STAFF, ModuleVoter::CREATE)
        /*
        //INICIO ACL--
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('CREATE', 'ntty_14')) {
            throw new AccessDeniedException();
        }// FIN ACL
        */
        $params = $request->request->all(); //SE OBTIENEN LOS PARAMETROS DEL FORM SOLO PARA EL METODO (POST)
        $em = $this->entityManager;
        $current_user = $this->getUser();

        // Validación mínima: solo nombres y apellidos requeridos
        if ($params['nombres'] and $params['apellidos']) {
            // Verificar duplicado solo si se proporciona RUT
            $staff_exist = null;
            if (!empty($params['rut'])) {
                $staff_exist = $em->getRepository(Tbl14Personal::class)->findByRut($params['rut']);
            }

            if (!$staff_exist) {
                $staff = new Tbl14Personal();

                // Campos básicos
                $staff->setNombres($params['nombres']);
                $staff->setApellidos($params['apellidos']);

                // RUT ahora es opcional
                if (!empty($params['rut'])) {
                    $staff->setRut($params['rut']);
                }

                // Campos opcionales
                if (isset($params['fono'])) {
                    $staff->setFono($params['fono']);
                }
                if (isset($params['correo_electronico'])) {
                    $staff->setCorreoElectronico($params['correo_electronico']);
                }
                if (isset($params['anexo'])) {
                    $staff->setAnexo($params['anexo']);
                }
                if (isset($params['autoriza_ot'])) {
                    $staff->setAutorizaOt((bool)$params['autoriza_ot']);
                }

                // Relaciones - usar valores del formulario o valores por defecto
                $concesionariaId = isset($params['id_concesionaria']) && $params['id_concesionaria'] != 'c-0'
                    ? (int)str_replace('c-', '', $params['id_concesionaria'])
                    : 0;
                $staff->setIdConcesionaria($em->getRepository(Tbl06Concesionaria::class)->find($concesionariaId));

                $centroCostoId = isset($params['id_centro_costo']) ? (int)$params['id_centro_costo'] : 0;
                $staff->setIdCentroDeCosto($em->getRepository(Tbl07CentroDeCosto::class)->find($centroCostoId));

                $areaId = isset($params['id_area']) ? (int)$params['id_area'] : 0;
                $staff->setIdArea($em->getRepository(Tbl08Areas::class)->find($areaId));

                // Campos de licencia por defecto
                $staff->setEstadoLicenciaConducir(false);
                $staff->setFechaEmisionLicencia(null);
                $staff->setFechaVencimientoLicencia(null);

                // Auditoría
                $staff->SetRegStatus(true);
                $staff->SetCreatedBy($current_user->getId());
                $staff->SetCreatedAt(new \DateTime());

                $em->persist($staff);
                $em->flush();

                return new Response(
                    json_encode(array(
                        'mensaje_success' => 'Se ha creado el personal interno correctamente',
                        'idStaff' => $staff->getIdPersonal()
                    )),
                    200,
                    array('Content-Type' => 'application/json')
                );

                /*
                 $form = $this->createCreateForm($staff);
                $form->handleRequest($request);

                if($form->isValid()) {
                    //$em->persist($staff);
                    //$em->flush();

                    return new Response(
                        json_encode(array( 'mensaje_success' => 'Se han creado el conductor correctamente')),
                        200,
                        array('Content-Type' => 'application/json')
                    );
                }
                else{
                    return new Response(
                        json_encode(array( 'mensaje_error' => 'Error los datos no son válidos')),
                        200,
                        array('Content-Type' => 'application/json')
                    );
                }*/

            } else {
                //YA EXISTE
                return new Response(
                    json_encode(array('idStaff' => $staff_exist[0]->getIdPersonal())),
                    200,
                    array('Content-Type' => 'application/json')
                );
            }
        } else {
            return new Response(
                json_encode(array('mensaje_error' => 'Error: nombres y apellidos son obligatorios')),
                200,
                array('Content-Type' => 'application/json')
            );
        }
    }

    /**
     * Crear Personal Externo via AJAX - Para Permisos de Trabajo
     * Personal Externo se guarda en tabla separada: tbl_14_1_personal_externo
     */
    #[Route('/create-external-ajax', name: 'create_external_ajax', methods: ['POST'])]
    #[IsGranted('ROLE_VIEW_WORK_PERMITS')]
    public function createExternalAjaxAction(Request $request): Response
    {
        $params = $request->request->all();
        $em = $this->entityManager;
        $current_user = $this->getUser();

        // Validación mínima: solo nombres, apellidos y empresa requeridos
        if (!empty($params['nombres']) && !empty($params['apellidos']) && !empty($params['id_proveedor'])) {
            // Verificar duplicado solo si se proporciona RUT
            $staff_exist = null;
            if (!empty($params['rut'])) {
                $staff_exist = $em->getRepository(\App\Entity\Tbl141PersonalExterno::class)->findBy(['rut' => $params['rut']]);
            }

            if (!$staff_exist) {
                $staff = new \App\Entity\Tbl141PersonalExterno();

                // Campos básicos
                $staff->setNombres($params['nombres']);
                $staff->setApellidos($params['apellidos']);

                // RUT es opcional
                if (!empty($params['rut'])) {
                    $staff->setRut($params['rut']);
                }

                // Campos opcionales
                if (isset($params['fono']) && !empty($params['fono'])) {
                    $staff->setFono($params['fono']);
                }
                if (isset($params['correo_electronico']) && !empty($params['correo_electronico'])) {
                    $staff->setCorreoElectronico($params['correo_electronico']);
                }

                // ID Proveedor (empresa solicitante)
                $staff->setIdProveedor((int)$params['id_proveedor']);

                // Auditoría
                $staff->setRegStatus(true);
                $staff->setCreatedBy($current_user->getId());
                $staff->setCreatedAt(new \DateTime());

                $em->persist($staff);
                $em->flush();

                return new Response(
                    json_encode(array(
                        'mensaje_success' => 'Se ha creado el personal externo correctamente',
                        'idStaff' => $staff->getIdPersonal()
                    )),
                    200,
                    array('Content-Type' => 'application/json')
                );

            } else {
                // Ya existe
                return new Response(
                    json_encode(array('idStaff' => $staff_exist[0]->getIdPersonal())),
                    200,
                    array('Content-Type' => 'application/json')
                );
            }
        } else {
            return new Response(
                json_encode(array('mensaje_error' => 'Error: nombres, apellidos y empresa son obligatorios')),
                200,
                array('Content-Type' => 'application/json')
            );
        }
    }

    //EDITAR REGISTROS
    #[Route('/edit', name: 'edit', methods: ['GET', 'POST'])]

    public function editAction($id): Response
    {
        // TODO: Migrate to new permission system using PermissionService
        // Old entity 'ntty_14' should map to MODULE_STAFF with VIEW permission
        // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_STAFF, ModuleVoter::VIEW)
        /*
        //INICIO ACL--
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('VIEW', 'ntty_14')) {
            throw new AccessDeniedException();
        }// FIN ACL
        */
        $em = $this->entityManager;
        $staff = $em->getRepository(Tbl14Personal::class)->find($id);

        //Tbl13LicenciasDeConducir
        $query_builder_licencias = $em->createQueryBuilder();
        $query_builder_licencias->select('reg')
            ->from(Tbl13LicenciasDeConducir::class, 'reg')
            //->groupBy('reg.tipoLicencia')
            ->orderBy('reg.tipoLicencia', 'ASC')
            ->orderBy('reg.aliasLicencia', 'ASC');

        $licencias = $query_builder_licencias->getQuery()->getResult();

        //dump($licencias);

        //$licencias = $em->getRepository(Tbl13LicenciasDeConducir::class)->findAll();
        if (!$staff) {
            $messageException = $this->translator->trans('El colaborador no se encuentra registrado en el sistema.');
            throw $this->createNotFoundException($messageException);
        }
        $form = $this->createEditForm($staff);
        //dump($staff);
        return $this->render('dashboard/Staff/edit.html.twig', array(
            'staff' => $staff,
            'form' => $form->createView(),
            'licencias' => $licencias

        ));

    }

    //FUNCIÓN PARA CREAR EL FORMULARIO
    private function createCreateForm(Tbl14Personal $entity)
    {
        $form = $this->createForm(Tbl14PersonalType::class, $entity, array(
            'action' => $this->generateUrl('staff_create'),
            'method' => 'POST'
        ));
        return $form;
    }

    //CREA FORMULARIO DE EDICIÓN
    private function createEditForm(Tbl14Personal $entity)
    {
        $form = $this->createForm(Tbl14PersonalType::class, $entity, array(
            'action' => $this->generateUrl('staff_update', array('id' => $entity->getidPersonal())),
            'method' => 'PUT'
        ));

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
            if (false === $securityController->isGrantedCheck('UNDELETE', 'ntty_14')) {
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
            // Old entity 'ntty_14' should map to MODULE_STAFF with EDIT permission
            // Example: $this->permissionService->hasModulePermission(ModuleVoter::MODULE_STAFF, ModuleVoter::EDIT)
            /*
            //INICIO ACL--
            $securityController = $this->get('sgv_user.security_controller');
            if (false === $securityController->isGrantedCheck('EDIT', 'ntty_14')) {
                throw new AccessDeniedException();
            }
            */
            // FIN ACL
        }

        $current_user = $this->getUser();
        //-- End For Restore--- -   -   -   -   -   -   -   -   -

        $em = $this->entityManager;

        $staff = $em->getRepository(Tbl14Personal::class)->find($id);

        $old_fechaDeEmisionLicencia = $staff->getFechaEmisionLicencia();
        $old_fechaDeVencimientoLicencia = $staff->getFechaVencimientoLicencia();
        //dump($old_fechaDeEmisionLicencia);
        //dump($old_fechaDeVencimientoLicencia);

        $old_estadoLicenciaConducir = $staff->getEstadoLicenciaConducir(); //Suspención
        $old_fechaInicioSuspencionLicenciaConducir = $staff->getFechaInicioEstadoLicenciaConducir();
        $old_licenciasConducir = $staff->getLicenciasConducir();

        if (!$staff) {
            $messageException = $this->translator->trans('El colaborador no se encuentra registrado en el sistema.');
            throw $this->createNotFoundException($messageException);
        }

        $form = $this->createEditForm($staff);

//---------------------------------------------------------------------------------------------------------------------
        /*
* Uso replace(): $all = $request->request->all(); $all['tactill_customerbundle_customertype'] = $newValue; $request->request->replace($all);Esto permite modificar los parámetros de matriz
*/
        $all = $request->request->all();

        if (isset($all['frm_staff']['tbl24ConductoresAsignados'])) {
            //dump($all['frm_staff']);
            $frm_vehicles_tbl24ConductoresAsignados = $all['frm_staff']['tbl24ConductoresAsignados'];
            $rebuild_frm_vehicles_tbl24ConductoresAsignados = array();
            $auxiliar_keys = array();
            foreach ($frm_vehicles_tbl24ConductoresAsignados as $item) {
                //Valido la que el objeto contenga los array's obligatorios antes de añadir más propiedades
                if (isset($item['idPersonal']) and isset($item['idVehiculo']) and array_search($item['idPersonal'] . $item['idVehiculo'], $auxiliar_keys) === false) {
                    $item['regStatus'] = true;
                    $created_at = new \DateTime();
                    $created_at = $created_at->format('Y-m-d H:i:s');
                    $item['createdAt'] = $created_at;
                    $item['createdBy'] = $current_user->getId();
                    $item['updatedAt'] = $created_at;
                    $item['updatedBy'] = $current_user->getId();
                    //Valido la integridad del objeto vehículo, si es válido inserto el array al contenedor
                    if ($item['idVehiculo']) {
                        array_push($rebuild_frm_vehicles_tbl24ConductoresAsignados, $item);
                        array_push($auxiliar_keys, $item['idPersonal'] . $item['idVehiculo']);
                    }
                }
            }
            //dump($rebuild_frm_vehicles_tbl24ConductoresAsignados);
            $all['frm_staff']['tbl24ConductoresAsignados'] = $rebuild_frm_vehicles_tbl24ConductoresAsignados;
            //dump($all);
            $request->request->replace($all);
        }
        $originalConductoresAsignados = new ArrayCollection();
        //dump($vehicles->getTbl24ConductoresAsignados());
        foreach ($staff->getTbl24ConductoresAsignados() as $detalleConductoreAsignado) {
            $found = 0;
            foreach ($originalConductoresAsignados as $originalConductoresAsignado) {
                if ($originalConductoresAsignado->getIdPersonal()->getIdPersonal() == $detalleConductoreAsignado->getIdPersonal()->getIdPersonal()) {
                    $found++;
                }
                //dump(($originalConductoresAsignado->getIdPersonal()->getIdPersonal() == $detalleConductoreAsignado->getIdPersonal()->getIdPersonal()));
            }

            if ($found === 0) {
                $originalConductoresAsignados->add($detalleConductoreAsignado);
            }/**/
            $originalConductoresAsignados->add($detalleConductoreAsignado);
            //$originalConductoresAsignados->add($detalleConductoreAsignado);
        }
//---------------------------------------------------------------------------------------------------------------------
        $form->handleRequest($request);

        // Se pasan los valores a la entidad para el update
        //----------------------------------------------------
        //10-01-2017 JN Se agrega respuestas en JSON para confirmar la restauración del registro a travez de AJAX
        if ($restore) {
            $staff->SetRegStatus(true);
            $staff->setDeletedRestoredBy($current_user->getId());
            $staff->setDeletedRestoredAt(new \DateTime());

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
            $staff->SetUpdatedBy($current_user->getId());
            $staff->SetUpdatedAt(new \DateTime());
        }
        //----------------------------------------------------

        //Control de modificación de campos que desencadenan la reinspección de los registros de cargas de combustibles
        $new_fechaDeEmisionLicencia = $staff->getFechaEmisionLicencia();
        $new_fechaDeVencimientoLicencia = $staff->getFechaVencimientoLicencia();
        $new_estadoLicenciaConducir = $staff->getEstadoLicenciaConducir();
        $new_fechaInicioSuspencionLicenciaConducir = $staff->getFechaInicioEstadoLicenciaConducir();

        //dump($new_fechaDeEmisionLicencia);
        //dump($new_fechaDeVencimientoLicencia);

        $new_licenciasConducir = $staff->getLicenciasConducir();

        if ($old_estadoLicenciaConducir != $new_estadoLicenciaConducir or $old_licenciasConducir != $new_licenciasConducir or $old_fechaDeEmisionLicencia != $new_fechaDeEmisionLicencia or $old_fechaInicioSuspencionLicenciaConducir != $new_fechaInicioSuspencionLicenciaConducir or $old_fechaDeVencimientoLicencia != $new_fechaDeVencimientoLicencia) // ToDo  or $old_licenciasConducir != $new_licenciasConducir
        {
            //ToDo Desencadenar función de reprogramación de pendientes de inspección en cargas de combustibles
            //dump('Desencadenar función de reprogramación de pendientes de inspección en cargas de combustibles');
            //dump($new_estadoLicenciaConducir);
            //dump($new_fechaInicioSuspencionLicenciaConducir);
            //dump(new \DateTime());
            if ($new_estadoLicenciaConducir and $new_fechaInicioSuspencionLicenciaConducir) {
                FuelLoadsController::reprogramarInspeccionRegCargasDeCombustiblesModConductorAction($staff->getIdPersonal(), $new_fechaInicioSuspencionLicenciaConducir);
            }

            if ($old_licenciasConducir != $new_licenciasConducir) {
                FuelLoadsController::reprogramarInspeccionRegCargasDeCombustiblesModConductorAction($staff->getIdPersonal(), new \DateTime());
            }

            if ($old_fechaDeEmisionLicencia != $new_fechaDeEmisionLicencia) {
                FuelLoadsController::reprogramarInspeccionRegCargasDeCombustiblesModConductorAction($staff->getIdPersonal(), new \DateTime());
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($originalConductoresAsignados as $detalleConductoreAsignado) {
                if (false === $staff->getTbl24ConductoresAsignados()->contains($detalleConductoreAsignado)) {
                    $staff->getTbl24ConductoresAsignados()->removeElement($detalleConductoreAsignado);
                    $em->remove($detalleConductoreAsignado);
                }
            }

            // $file stores the uploaded file
            /** @var Symfony\Component\HttpFoundation\File\UploadedFile $file */
            if ($staff->getPhoto()) {
                $file = $staff->getPhoto();

                // Generate a unique name for the file before saving it
                $fileName = $this->get('nzo_url_encryptor')->encrypt($staff->getIdPersonal());  //$this->get('nzo_url_encryptor')->encrypt($concessionaries->getIdConcesionaria().'.'.$file->guessExtension());
                //$fileName = md5(uniqid('', true)).'.'.$file->guessExtension();
                //dump($fileName);
                // Move the file to the directory where brochures are stored
                $file->move(
                    $this->getParameter('staff_directory'),
                    $fileName
                );

                // Update the 'brochure' property to store the PDF file name
                // instead of its contents
                $staff->setPhoto($fileName);
            }

            $em->flush();

            $successMessage = $this->translator->trans('El colaborador ha sido modificado.');
            $this->addFlash('mensaje', $successMessage);
            return $this->redirectToRoute('staff_index', array('id' => $staff->getidPersonal()));
        }

        // Get licencias for the form
        $query_builder_licencias = $em->createQueryBuilder();
        $query_builder_licencias->select('reg')
            ->from(Tbl13LicenciasDeConducir::class, 'reg')
            ->orderBy('reg.tipoLicencia', 'ASC')
            ->orderBy('reg.aliasLicencia', 'ASC');
        $licencias = $query_builder_licencias->getQuery()->getResult();

        return $this->render('dashboard/Staff/edit.html.twig', array('staff' => $staff, 'form' => $form->createView(), 'licencias' => $licencias));
    }

//ELIMINAR REGISTRO
    #[Route('/delete', name: 'delete', methods: ['GET', 'POST'])]

    public function deleteAction(Request $request, $id): Response
    {
        $securityController = $this->get('sgv_user.security_controller');
        if (false === $securityController->isGrantedCheck('DELETE', 'ntty_14')) {
            $message = $this->translator->trans('No tiene permiso para eliminar el registro.');
            return new Response(
                json_encode(array('removed' => 0, 'message' => $message)),
                200,
                array('Content-Type' => 'application/json')
            );
        }
        $em = $this->entityManager;

        $staff = $em->getRepository(Tbl14Personal::class)->find($id);

        if (!$staff) {
            $messageException = $this->translator->trans('El colaborador no se encuentra registrado en el sistema.');
            throw $this->createNotFoundException($messageException);
        }

        // Se pasan los valores a la entidad para el delete
        $current_user = $this->getUser();
        //dump($current_user->getId());
        $staff->SetRegStatus(false);
        $staff->setDeletedRestoredBy($current_user->getId());
        $staff->SetDeletedRestoredAt(new \DateTime());
        //----------------------------------------------------

        $form = $this->createCustomForm($staff->getidPersonal(), 'DELETE', 'staff_delete');
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
                    $allStaff = $em->getRepository(Tbl14Personal::class)->findAll();
                    $countStaff = count($allStaff);

                    // $form = $this->createDeleteForm($user);
                    $form = $this->createCustomForm($staff->getidPersonal(), 'DELETE', 'staff_delete');
                    $form->handleRequest($request);

                    if($form->isSubmitted() && $form->isValid())
                    {
                        if($request->isXMLHttpRequest())
                        {
                            $res = $this->deleteBrand( $em, $staff);

                            return new Response(
                                json_encode(array('removed' => $res['removed'], 'message' => $res['message'], 'countStaff' => $countStaff)),
                                200,
                                array('Content-Type' => 'application/json')
                            );
                        }

                        $res = $this->deleteBrand($em, $staff);

                        $this->addFlash($res['alert'], $res['message']);
            */
            return $this->redirectToRoute('staff_index');
        }
    }

    private function deleteBrand($em, $staff)
    {
        $em->remove($staff);
        $em->flush();

        $message = $this->translator->trans('The staff has been deleted.');
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

    #[Route('/get-staff', name: 'get_staff', methods: ['GET', 'POST'])]


    public function getStaffAction(Request $request): Response
    {
        if ($request->request->get('user')['idStaff']) {
            $idPersonal = $request->request->get('user')['idStaff'];
        } else {
            $idPersonal = 0;
        }

        if ($idPersonal) {
            $em = $this->entityManager; //conexión con los servicios de datos
            $qbStaff = $em->createQueryBuilder();
            $qbStaff->select('reg')
                ->from(Tbl14Personal::class, 'reg')
                ->where('reg.idPersonal = :idPersonal')
                ->setParameters(array('idPersonal' => $idPersonal));
        }

        if (isset($qbStaff)) {
            if ($request->isXMLHttpRequest()) {
                return new Response(
                    json_encode($qbStaff->getQuery()->getArrayResult()),
                    200,
                    array('Content-Type' => 'application/json')
                );
            } else {
                return $qbStaff->getQuery()->getResult(); // PARA OBTENER UN OBJECT ARRAY ES: ->getQuery()->getResult();
            }
        } else {
            return false;
        }

    }

    private function getPuntosRut($rut)
    {
        if (strlen($rut) > 8) {
            $rut = trim($rut);
            $rut = str_replace(" ", "", $rut);
            $rut = str_replace(".", "", $rut);
            $rutTmp = explode("-", $rut);
            return number_format($rutTmp[0], 0, "", ".") . '-' . $rutTmp[1];
        } else {
            return false;
        }

    }

}
