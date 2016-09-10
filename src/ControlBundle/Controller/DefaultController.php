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
		'port' => "7220" // Linux
		//'port' => "7224" // Windows 7
		));
    }
	
	/**
     * @Route("/view_vm", name="view_vm")
     */
	 public function view_vmAction() {
		 
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		
			return $this->render('ControlBundle:Default:vm_view.html.twig', array(
		'user' => $user,
		'host' => "194.57.105.124",
		//'port' => "7220"
		'port' => "7227" // Windows 7
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
