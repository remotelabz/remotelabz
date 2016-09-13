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
     * @Route("/choixTP", name="choixTP")
     */
    public function choixTPAction()
    {		
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		
		$repository = $this->getDoctrine()->getRepository('AppBundle:TP');
        $list_tp = $repository->findAll();
		
        return $this->render('ControlBundle:Default:choixTP.html.twig', array(
		'user' => $user,
		'list_tp' => $list_tp
		));
    }
	
	/**
     * @Route("/generate_xml{tp_id}", name="generate_xml")
     */
    public function generate_xmlAction($tp_id)
    {		
		

		$repository = $this->getDoctrine()->getRepository('AppBundle:TP');
        $tp = $repository->find($tp_id);
		$lab=$tp->getLab();
		
		$rootNode = new \SimpleXMLElement( "<?xml version='1.0' encoding='UTF-8' standalone='yes'?><lab></lab>" );
        $nodes = $rootNode->addChild('nodes');
		
		foreach ($lab->getPod()->getDevices() as $dev) {
			$device=$nodes->addChild('device', $dev->getNom());
			$device->addAttribute('id',$dev->getId());
			$device->addAttribute('type',$dev->getType());
			$device->addAttribute('script',$dev->getScript());
		}
		$interface=$device->addChild('interface');
		$interface->addAttribute('id','2');
		$device=$nodes->addChild( 'device', $tp->getLab()->getNomLab());
        return new Response($rootNode->asXML());
		

    }
	
}
