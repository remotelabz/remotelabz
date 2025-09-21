<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IconController extends AbstractController
{
    #[IsGranted("ROLE_TEACHER_EDITOR", message: "Access denied.")]
    #[Route('/admin/icons', name: 'app_icons_gallery')]
    public function gallery(): Response
    {
        return $this->render('icons/gallery.html.twig');
    }
}