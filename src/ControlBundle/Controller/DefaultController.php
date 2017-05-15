<?php

namespace ControlBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use AppBundle\Entity\Run;


class DefaultController extends Controller
{
	
	/**
     * @Route("/control/view_vm{device_id}/{protocol}/{port}", name="view_vm")
     */
	 public function view_vmAction($device_id,$protocol,$port) {
		 
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();
		
		$repository = $this->getDoctrine()->getRepository('AppBundle:Device');
        $device = $repository->find($device_id);
		
		//if ($device->getInterfaceControle()->getConfigReseau()->getProtocole()=='websocket')
		if ($protocol=='websocket')
			{
			 return $this->render('ControlBundle:Default:view_vnc.html.twig', array(
		'user' => $user,
		'group' => $group,
		'host' => $device->getInterfaceControle()->getConfigReseau()->getIP(),
		//'port' => $device->getInterfaceControle()->getConfigReseau()->getPort(),
		'port' => $port,
		'title' => $device->getNom()
		));
		/*	return $this->redirect('http://194.57.105.124/vnc_auto.html?host='.$device->getInterfaceControle()->getConfigReseau()->getIP(). '&port='.$port); */
		
			}
		//if ($device->getInterfaceControle()->getConfigReseau()->getProtocole()=='telnet')
			if ($protocol=='telnet')
		{
				 return $this->render('ControlBundle:Default:wstelnet.html.twig', array(
		'user' => $user,
		'group' => $group,
		'host' => $device->getInterfaceControle()->getConfigReseau()->getIP(),
		//'port' => $device->getInterfaceControle()->getConfigReseau()->getPort(),
		'port' => $port,
		'title' => $device->getNom()
		));
		}
	 }
	 
	 
	/**
     * @Route("/control/choixTP", name="choixTP")
     */
    public function choixTPAction()
    {		
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();
		
		$repository = $this->getDoctrine()->getRepository('AppBundle:Run');
        $run = $repository->findOneBy(array('user'=> $user));
		
		if ($run != null) {
		//Analyse du XML
		$tp_array=$this->read_xml($run->getDirTpUser(),$run->getTpProcessName());
	
		
		return $this->render('ControlBundle:Default:'.$run->getTp()->getNom().'.html.twig', array(
		'user' => $user,
		'group' => $group,
		'tp_array' => $tp_array
		));
			
		}
		else {//User n'a pas de TP lancé
		$repository = $this->getDoctrine()->getRepository('AppBundle:TP');
        $list_tp = $repository->findAll();
				
        return $this->render('ControlBundle:Default:choixTP.html.twig', array(
		'user' => $user,
		'group' => $group,
		'list_tp' => $list_tp
		));
		}
    }
	
	public function UpdateInterfaceIndex($tp_id,$increment) { // increment permet de définir s'il faut augmenter (+1) ou diminuer (-1) l'index des interfaces utilisables
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
	public function stopLabAction($tp_id){
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();
						$em = $this->getDoctrine()->getManager();

		$param_system = $this->getDoctrine()->getRepository('AppBundle:Param_System')->findOneBy(array('id' => '1'));

		$this->UpdateInterfaceIndex($tp_id,-1);
		
		$list_tp = $this->getDoctrine()->getRepository('AppBundle:TP')->findAll();
		
		$logger = $this->get('logger');
		$script_name=$this->getParameter('script_stop_lab');
		
		$run=$em->getRepository('AppBundle:Run')->findOneBy(array('user'=>$user));
					
		$cmd="/usr/bin/python $script_name "." ".$run->getDirTpUser()." ".$run->getTpProcessName();
		
		$em->remove($run);
		$em->flush();
		$output=passthru($cmd);
		$logger->info($cmd);
		$logger->info($output);
	
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
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$em = $this->getDoctrine()->getManager();

		$group=$user->getGroupe();
	$logger = $this->get('logger');
	
	// Ajouter la gestion de l'objet réservation
	// Faire le exec avec le fichier XML stocké par generate_xml
	$tp_array=$this->generate_xml($tp_id);
	
	$script_name=$this->getParameter('script_start_lab');
	//$cmd="".escapeshellarg($script_name." ".$tp_array['lab_name_avec_id_absolutepath']);
	//$output=exec(sprintf('/usr/bin/python $script_name %s',escapeshellcmd($tp_array['lab_name_avec_id_absolutepath'])));
	//$output=exec(sprintf('/usr/bin/python $script_name %s',"/home/RLv2_fnolot/3VM_1ASA_883.xml"));
	
	//Je stocke dans un run les informations du TP executé :
	$run=new Run();
	$repository = $this->getDoctrine()->getRepository('AppBundle:TP');
    $tp = $repository->find($tp_id);
	$run->setTp($tp);
	$run->setTpProcessName($tp_array['lab_name_instance']);
	$run->setUser($user);
	$run->setDirTpUser($tp_array['dir']);
	$em->persist($run);
    $em->flush();
	
	$cmd="/usr/bin/python $script_name ".$tp_array['lab_name_avec_id_absolutepath']." ".$tp_array['IPv4_Serv']." ".$tp_array['dir'];
	$output=passthru($cmd);
	$logger->info($cmd);
	$logger->info($output);
   
	$file_name='ControlBundle:Default:'.$tp_array['lab_name'].'.html.twig';
	
	// Ajouter paramètre avec les param pour chaque fenetre
	// indiquer telnet ou vnc et l'id du device à chaque fois
	// appel ainsi à un controller unique avec pour param l'id du device
	
	return $this->render($file_name, array(
		'user' => $user,
		'group' => $group,
		'tp_array' => $tp_array,
		'output' => $cmd
		));
	}
	
	
    public function generate_xml($tp_id)
    {	
		$dir_prefix='/home/RLv2_';
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();

		$param_system = $this->getDoctrine()->getRepository('AppBundle:Param_System')->findOneBy(array('id' => '1'));
		
		
		$repository = $this->getDoctrine()->getRepository('AppBundle:TP');
        $tp = $repository->find($tp_id);
		$lab=$tp->getLab();
		
		$rootNode = new \SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' standalone='yes'?><lab></lab>" );
        $user_node=$rootNode->addChild('user');
		$index=$param_system->getIndexInterface();
		$lab_name_tp=$lab->getNomlab();
		$lab_name=$lab_name_tp."_".$param_system->getIndexInterface();
		$Structure_tp['lab_name']=$lab_name_tp;
		$Structure_tp['lab_name_instance']=$lab_name;
		
		
		$nomlab_node=$rootNode->addChild('lab_name',$lab_name);
		$user_node->addAttribute('login',$user->getUsername());
		$nodes = $rootNode->addChild('nodes');
		$order=1;
		$devices=array();
		foreach ($lab->getPod()->getDevices() as $dev) {
			$device=$nodes->addChild('device');
			$property=$dev->getPropriete();	
			$device->addAttribute('property',$property);
			if ($property =='switch')
				$device->addChild('nom', $dev->getNom().$index);
			else
				$device->addChild('nom', $dev->getNom());
			$device->addAttribute('type',$dev->getType());
			
			$device->addAttribute('id',$dev->getId());
			$device->addAttribute('script',$dev->getScript());
			$device->addAttribute('image',$dev->getSysteme()->getPathMaster());
			$device->addAttribute('relativ_path',$dev->getSysteme()->getPathRelatif());
			$device->addAttribute('order',$order); //A remplacer par un $dev
			$device->addAttribute('hypervisor',$dev->getSysteme()->getHyperviseur()->getNom());
			$system=$dev->getSysteme();
			$system_node=$device->addChild('system', $system->getNom());
			$system_node->addAttribute('memory',$system->getParametres()->getSizeMemoire());
			$system_node->addAttribute('disk',$system->getParametres()->getSizeDisque());
			$order++;
			foreach ($dev->getNetworkInterfaces() as $int) {
				if ($dev->getInterfaceControle()) {
					if ( $int->getId() != $dev->getInterfaceControle()->getId() ) {
						$interface=$device->addChild('interface');
						$interface->addAttribute('id',$int->getId());
						$interface->addAttribute('physique_name',$int->getNomPhysique());
						if ($int->getNomVirtuel() == "tap") {
							$interface->addAttribute('logical_name',"tap".$index);
							$interface->addAttribute('mac_address',"00:02:03:04:".$this->MacEnd($index));
							$index++;
						}
					}
				}
				else {
					$interface=$device->addChild('interface');
						$interface->addAttribute('id',$int->getId());
						$interface->addAttribute('nom_physique',$int->getNomPhysique());
						$interface->addAttribute('virtual_name',$int->getNomVirtuel());
				}
			}
			$int_ctrl=$dev->getInterfaceControle();
			if ($int_ctrl) {
			$int_ctrl_node=$device->addChild('interface_control');
			$int_ctrl_node->addAttribute('id',$int_ctrl->getId());
			$int_ctrl_node->addAttribute('physique_name',$int_ctrl->getNomPhysique());
			$int_ctrl_node->addAttribute('logical_name',$int_ctrl->getNomVirtuel());
			if ($int_ctrl->getConfigReseau()) {
				$int_ctrl_node->addAttribute('IPv4',$int_ctrl->getConfigReseau()->getIP());
				$int_ctrl_node->addAttribute('mask',$int_ctrl->getConfigReseau()->getMasque());
				$int_ctrl_node->addAttribute('IPv6',$int_ctrl->getConfigReseau()->getIPv6());
				$int_ctrl_node->addAttribute('prefix',$int_ctrl->getConfigReseau()->getPrefix());
				$int_ctrl_node->addAttribute('DNSv4',$int_ctrl->getConfigReseau()->getIPDNS());
				$int_ctrl_node->addAttribute('gatewayv4',$int_ctrl->getConfigReseau()->getIPGateway());
				$proto=$int_ctrl->getConfigReseau()->getProtocole();
				$int_ctrl_node->addAttribute('protocol',$proto);
				
				if ($proto=="websocket")
					$port=$this->getParameter('port_start_websocket')+$int_ctrl->getId()+$index;
				if ($proto=="telnet")
					$port=$this->getParameter('port_start_telnet')+$int_ctrl->getId()+$index;
				//$int_ctrl->getConfigReseau()->getPort()
				
				$int_ctrl_node->addAttribute('port',$port);
				array_push($devices,array('id'=>$dev->getId()
				,
					'nom'=>$dev->getNom(),
					'protocol'=>$proto,
					'port'=>$port,
					'IPv4'=>$int_ctrl->getConfigReseau()->getIP(),
					'IPv6'=>$int_ctrl->getConfigReseau()->getIPv6()
				));
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
		$Structure_tp['devices']=$devices;
		$init = $rootNode->addChild('init');
		$serveur = $init->addChild('serveur');	
		$serveur->addChild('IPv4',$param_system->getIpv4());
		$Structure_tp['IPv4_Serv']=$param_system->getIpv4();
		$Structure_tp['IPv6_Serv']=$param_system->getIpv6();
		$serveur->addChild('IPv6',$param_system->getIpv6());
		$serveur->addChild('index',$param_system->getIndexInterface());
		$this->UpdateInterfaceIndex($tp_id,1);

		$response=new Response($rootNode->asXML());
		$response->headers->set('Content-Type', 'application/xml');
		#$disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT,'foo.xml');
		#$response->headers->set('Content-Disposition', $disposition);
        $dir=$dir_prefix.$user->getUsername();
		$filename=$dir.'/'.$lab_name.'.xml';
		$Structure_tp['lab_name_avec_id_absolutepath']=$filename;
		$Structure_tp['dir']=$dir;
			if (!is_dir($dir))
				mkdir($dir,0770);
		
		$fp = fopen($filename,'x');
		fwrite($fp,$rootNode->asXML());
		fclose($fp);

		return $Structure_tp;
    }
	public function xml_attribute($object, $attribute)
	{
		if(isset($object[$attribute]))
			return (string) $object[$attribute];
	}

	public function read_xml($tp_dir,$tp_instance_name) {
		$dir_prefix=$this->getParameter('homedir');
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();
		
		$repository = $this->getDoctrine()->getRepository('AppBundle:TP');
        
		$devices=array();
			
		$xml_file = simplexml_load_file($tp_dir."/".$tp_instance_name.".xml");
		$xml_devices = $xml_file->xpath('/lab/nodes/device');
		$protocol="";
		$port="";
		$IPv4="";
		$IPv6="";
		foreach ($xml_devices as $node) {
		if ($this->xml_attribute($node,'property') != "switch") {
		  $id=$this->xml_attribute($node,'id');		  
		  foreach ($node->children() as $element) {	  
				$nom_element=$element->getName();
			if ($nom_element=='nom') {
				$nom=(string) $element;
			}
			if ($nom_element=='interface_control') {
				foreach($element->attributes() as $attribute) {
					switch($attribute->getName()) {
						case 'protocol':
							$protocol=(string) $attribute;
							break;
						case 'port':
							$port=(string) $attribute;
							break;
						case 'IPv4':
							$IPv4=(string) $attribute;
							break;
						case 'IPv6':
							$IPv6=(string) $attribute;
				}
			}
			}

		  }
		  array_push($devices,array(
					'id'=>$id,
					'nom'=>$nom,
					'protocol'=>$protocol,
					'port'=>$port,
					'IPv4'=>$IPv4,
					'IPv6'=>$IPv6
				));	  
		  
		  //$protocol=$this->xml_attribute($protocole,'protocol');
			  
		}
		}
	$Structure_tp['devices']=$devices;


		return $Structure_tp;
		
	}	
	
	public function MacEnd($nb) {
		return dechex(floor($nb/256)).":".dechex($nb%256);
	}
}
