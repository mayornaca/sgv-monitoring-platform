<?php

namespace App\Controller\Dashboard;

use App\Controller\BaseController;
#[Route('/default', name: 'default_')]
class DefaultController extends BaseController
{
    
    

    #[Route('', name: 'index', methods: ['GET', 'POST'])]

    
    

    public function indexAction($name): Response
    {
        return $this->render('dashboard/Default/index.html.twig', array('name' => $name));
    }

}
