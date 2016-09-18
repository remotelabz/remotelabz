<?php

namespace ControlBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;


class DefaultController extends Controller
{
    /**
     * @Route("/control", name="control_vm")
     */
    public function indexAction()
    {
			
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();
		// Si l'utilisateur courant est anonyme, $user vaut « anon. »
		// Sinon, c'est une instance de notre entité User, on peut l'utiliser normalement
		
        return $this->render('ControlBundle:Default:index.html.twig', array(
		'user' => $user,
		'group' => $group,
		'host' => "194.57.105.124",
		'port' => "7220" // Linux
		//'port' => "7224" // Windows 7
		));
    }
	
	/**
     * @Route("/control/view_vm", name="view_vm")
     */
	 public function view_vmAction() {
		 
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();
		
			return $this->render('ControlBundle:Default:vm_view.html.twig', array(
		'user' => $user,
		'group' => $group,
		'host' => "194.57.105.124",
		//'port' => "7220"
		'port' => "7227" // Windows 7
		));
	 }
	 
	/**
     * @Route("/control/choixTP", name="choixTP")
     */
    public function choixTPAction()
    {		
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();
		
		$repository = $this->getDoctrine()->getRepository('AppBundle:TP');
        $list_tp = $repository->findAll();
		
		
        return $this->render('ControlBundle:Default:choixTP.html.twig', array(
		'user' => $user,
		'group' => $group,
		'list_tp' => $list_tp
		));
    }
	
	public function UpdateInterfaceIndex($tp_id,$increment) { // increment permet de définir si il faut augmenter (+1) ou diminuer (-1) l'index des interfaces utilisables
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();
		$em = $this->getDoctrine()->getManager();
				
		$param_system = $this->getDoctrine()->getRepository('AppBundle:Param_System')->findOneBy(array('id' => '1'));
		$start_index=$param_system->getIndexInterface();
		
		$tp = $this->getDoctrine()->getRepository('AppBundle:TP')->find($tp_id);
		$lab=$tp->getLab();
		foreach ($lab->getPod()->getDevices() as $dev) {
			if ($dev->getType() == "virtuel"){
				$intctrl_id=$dev->getInterfaceControle();
				foreach ($dev->getNetworkInterfaces() as $int)
					if ($intctrl_id && ($intctrl_id->getId() == $int->getId()))
					{} else $start_index=$start_index+$increment;
			}
		}
		$min_index=$param_system->getIndexMinInterface();
		if ($start_index < $min_index)
			$param_system->setIndexInterface($min_index);
		else 	
			$param_system->setIndexInterface($start_index+$min_index);	
		$em->persist($param_system);
		$em->flush();
	}
	
	/**
     * @Route("/control/stopLab{tp_id}", name="stopLab")
     */
	public function stopLab($tp_id){
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();
		
		$param_system = $this->getDoctrine()->getRepository('AppBundle:Param_System')->findOneBy(array('id' => '1'));

		$this->UpdateInterfaceIndex($tp_id,-1);
		
		$list_tp = $this->getDoctrine()->getRepository('AppBundle:TP')->findAll();
				
        return $this->render('ControlBundle:Default:choixTP.html.twig', array(
		'user' => $user,
		'group' => $group,
		'list_tp' => $list_tp
		));
	}
	
	/**
     * @Route("/control/startLab{tp_id}", name="startLab")
     */
    public function startLabAction($tp_id)
    {	
	// Ajouter la gestion de l'objet réservation
	// Faire le exec avec le fichier XML stocké par generate_xml
	
	}
	
	/**
     * @Route("/control/generate_xml{tp_id}", name="generate_xml")
     */
    public function generate_xmlAction($tp_id)
    {	
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();

		$param_system = $this->getDoctrine()->getRepository('AppBundle:Param_System')->findOneBy(array('id' => '1'));
		
		$repository = $this->getDoctrine()->getRepository('AppBundle:TP');
        $tp = $repository->find($tp_id);
		$lab=$tp->getLab();
		
		$rootNode = new \SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' standalone='yes'?><lab></lab>" );
        $nodes = $rootNode->addChild('nodes');
		
		$order=1;
		$index=$param_system->getIndexInterface();
		foreach ($lab->getPod()->getDevices() as $dev) {
			$device=$nodes->addChild('device');
			$device->addChild('nom', $dev->getNom());
			$device->addAttribute('type',$dev->getType());
			$device->addAttribute('property',$dev->getPropriete());
			$device->addAttribute('id',$dev->getId());
			$device->addAttribute('script',$dev->getScript());
			$device->addAttribute('image',$dev->getSysteme()->getPathMaster());
			$device->addAttribute('relativ_path',$dev->getSysteme()->getPathRelatif());
			$device->addAttribute('order',$order); //A remplacer par un $dev
			$order++;
			foreach ($dev->getNetworkInterfaces() as $int) {
				if ($dev->getInterfaceControle()) {
					if ( $int->getId() != $dev->getInterfaceControle()->getId() ) {
						$interface=$device->addChild('interface');
						$interface->addAttribute('id',$int->getId());
						$interface->addAttribute('nom_physique',$int->getNomPhysique());
						if ($int->getNomVirtuel() == "tap") {
							$interface->addAttribute('nom_virtuel',"tap".$index);
							$index++;
						}
					}
				}
				else {
					$interface=$device->addChild('interface');
						$interface->addAttribute('id',$int->getId());
						$interface->addAttribute('nom_physique',$int->getNomPhysique());
						$interface->addAttribute('nom_virtuel',$int->getNomVirtuel());
				}
			}
			$int_ctrl=$dev->getInterfaceControle();
			if ($int_ctrl) {
			$int_ctrl_node=$device->addChild('interface_control');
			$int_ctrl_node->addAttribute('id',$int_ctrl->getId());
			$int_ctrl_node->addAttribute('nom_physique',$int_ctrl->getNomPhysique());
			$int_ctrl_node->addAttribute('nom_virtuel',$int_ctrl->getNomVirtuel());
			if ($int_ctrl->getConfigReseau()) {
				$int_ctrl_node->addAttribute('IPv4',$int_ctrl->getConfigReseau()->getIP());
				$int_ctrl_node->addAttribute('Masque',$int_ctrl->getConfigReseau()->getMasque());
				$int_ctrl_node->addAttribute('IPv6',$int_ctrl->getConfigReseau()->getIPv6());
				$int_ctrl_node->addAttribute('Prefix',$int_ctrl->getConfigReseau()->getPrefix());
				$int_ctrl_node->addAttribute('DNSv4',$int_ctrl->getConfigReseau()->getIPDNS());
				$int_ctrl_node->addAttribute('Gatewayv4',$int_ctrl->getConfigReseau()->getIPGateway());
				$int_ctrl_node->addAttribute('Protocole',$int_ctrl->getConfigReseau()->getProtocole());
				$int_ctrl_node->addAttribute('Port',$int_ctrl->getConfigReseau()->getPort());
			}
			}
			if ($dev->getPropriete() == "switch") {
				$networks=$rootNode->addChild('networks');
				$network=$networks->addChild('network');
				$network->addAttribute('type','OVS');
				$network->addAttribute('device_id',$dev->getId());
				foreach ($lab->getConnexions() as $con) {
					$interface=$network->addChild('port');
					$interface->addAttribute('id',$con->getId());
					$interface->addAttribute('interface_id1',$con->getInterface1()->getId());			
					$interface->addAttribute('vlan1',$con->getVlan1());
					$interface->addAttribute('interface_id2',$con->getInterface2()->getId());
					$interface->addAttribute('vlan2',$con->getVlan2());
				}
			}
		}
		$init = $rootNode->addChild('init');
		$serveur = $init->addChild('serveur');	
		$serveur->addChild('IPv4',$param_system->getIpv4());
		$serveur->addChild('IPv6',$param_system->getIpv6());
		$serveur->addChild('index',$param_system->getIndexInterface());
		$this->UpdateInterfaceIndex($tp_id,1);

		$response=new Response($rootNode->asXML());
		$response->headers->set('Content-Type', 'application/xml');
		//$disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT,'foo.xml');
		//$response->headers->set('Content-Disposition', $disposition);
        return $response;

    }
	
}
