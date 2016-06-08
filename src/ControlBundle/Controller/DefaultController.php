<?php

namespace ControlBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    /**
     * @Route("/control", name="control_vm")
     */
    public function indexAction()
    {
			
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		
		
		// Si l'utilisateur courant est anonyme, $user vaut « anon. »
		
		// Sinon, c'est une instance de notre entité User, on peut l'utiliser normalement
		
		
        return $this->render('ControlBundle:Default:index.html.twig', array(
		'user' => $user,
		'host' => "194.57.105.124",
		'port' => "7220"
		));
    }
	
	 /**
     * @Route("/listeTP", name="liste_TP")
     */
    public function listeTPAction()
    {		
        return $this->render('ControlBundle:Default:listeTP.html.twig');
    }
}
