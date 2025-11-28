<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PrivacyController extends AbstractController
{
    #[Route('/privacidad', name: 'app_privacy')]
    public function privacy(): Response
    {
        return $this->render('privacy/index.html.twig');
    }
}
