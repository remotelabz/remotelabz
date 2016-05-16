<?php

namespace VerifyBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class DefaultController extends Controller
{
    /**
     * @Route("/verify", name="verify")
     */
    public function indexAction()
    {
        return $this->render('VerifyBundle:Default:index.html.twig');
    }
}
