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
use AppBundle\Entity\NetworkUsed;
use AppBundle\Entity\Affected_IP;


class DefaultController extends Controller
{
	
	/**
     * @Route("/control/view_vm/{device_id}/{protocol}/{port}", name="view_vm")
     */
	 public function view_vmAction($device_id,$protocol,$port) {
		 
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();
		
		$repository = $this->getDoctrine()->getRepository('AppBundle:Device');
        $device = $repository->find($device_id);
		
		//if ($device->getInterfaceControle()->getConfigReseau()->getProtocole()=='websocket')
		if ($protocol=='websocket')
			{
			 return $this->render('ControlBundle:Default:view_websocket.html.twig', array(
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
		
		if ($protocol=='vnc') {
			return $this->render('ControlBundle:Default:view_vnc.html.twig', array(
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
     * @Route("/control/view_vm_access/{device_id}", name="view_vm_access")
     */
	 public function view_vm_accessAction($device_id) {
		 
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();
		
		$repository = $this->getDoctrine()->getRepository('AppBundle:Affected_IP');
        
		$repository_device = $this->getDoctrine()->getRepository('AppBundle:Device');
        $device = $repository_device->findOneById($device_id);
		
		$affected_ip_device = $repository->findByDevice($device_id);
		
		return $this->render('ControlBundle:Default:view_config.html.twig', array(
		'user' => $user,
		'group' => $group,
		'title' => $device->getNom(),
		//Probleme sur ce calcul à mon avis
		'ip_calc' => $this->calculateIP($affected_ip_device[0]->getIdNetwork()->getIpAddress(),$affected_ip_device[0]->getIndexIp()),
		'netmask' => $affected_ip_device[0]->getIdNetwork()->getNetmask(),
		'network_ip' => $affected_ip_device[0]->getIdNetwork()->getIpAddress()
		));
	}
	
	/**
     * @Route("/control/get_vpn_client_config", name="get_vpn_client_config")
     */
	 public function get_vpn_client_configAction() {
		 
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();
		
		$repository = $this->getDoctrine()->getRepository('AppBundle:Affected_IP');
        
		$repository_device = $this->getDoctrine()->getRepository('AppBundle:Device');
        
		
		return $this->render('ControlBundle:Default:get_vpn_config.html.twig', array(
		'user' => $user,
		'group' => $group
		));
	}
	
	/**
     * @Route("/control/choixTP", name="choixTP")
     */
    public function choixTPAction()
    {		
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$classes = $user->getClasses();
		$role=$user->getRole();
		
		$repository = $this->getDoctrine()->getRepository('AppBundle:Run');
        
		//Looking for executed TP by connected user
		$run = $repository->findBy(array('user'=> $user));
		
		//Check if user is in group
		$groups=$this->getDoctrine()->getRepository('UserBundle:UserGroup')->GroupsOfUser($user);
		//For debug only
		/*		foreach ($groups as $group)
					echo $group;
				echo count($groups);
		*/
		//
		foreach ($groups as $group) {
			//echo "groupe :".$group->getNom();
				foreach($repository->findBy(array('usergroup'=> $group)) as $new_run)
					array_push($run,$new_run);
		}		

		foreach ($classes as $class) {
			//echo "classe :".$class->getNom();
			foreach ($class->getUsers() as $oneuser) { // If one teacher from one of my class has started a TP
				/*echo "Utilisateur :".$oneuser->getLastname();
				echo $oneuser->getRole()->getNom();
				echo "<br/>";*/
				if ($oneuser != $user && $oneuser->getRole()->getNom() == 'Enseignant')
					foreach($repository->findBy(array('user'=> $oneuser)) as $new_run) 
						array_push($run,$new_run);
			}
		}
		
		//exit();
		
		//$run = $repository->findOneBy(array('user'=> $user));$user_group
		
		if (count($run) != null) {//If a TP is executed by the user
			//foreach ($run as $onerun) echo $onerun->getTpProcessName();
			if (count($run)>1 ) { // Many TP are executed by this user
				$list_tp=array();
				foreach($classes as $class) {
					foreach($class->getTps()->toArray() as $tp)
						array_push($list_tp,$tp);
				}
				return $this->render('ControlBundle:Default:choixTP.html.twig', array(
					'user' => $user,
					'group' => $role,
					'list_tp' => $list_tp,
					'run' => $run
					));
			}
		
			else {// Only one TP is executed by this user
			
				$executed_tp=$run[0];
				$tp_array=$this->read_xml($executed_tp->getDirTpUser(),$executed_tp->getTpProcessName());
			
				if ($executed_tp->getTp()->getManaged()==0) { // this TP is not managed
					//Analyse du XML
					return $this->render('ControlBundle:TP:'.$executed_tp->getTp()->getNom().'.html.twig', array(
							'user' => $user,
							'group' => $role,
							'tp_array' => $tp_array
							));
				}
				else {//Only 1 executed TP and it's managed
					return $this->render('ControlBundle:TP:'.$executed_tp->getTp()->getNom().'.html.twig', array(
							'user' => $user,
							'group' => $role,
							'tp_array' => $tp_array
							));
				}
			}
		}
		else {//User n'a pas de TP lancé
			$repository = $this->getDoctrine()->getRepository('AppBundle:TP');
			$list_tp=array();
			foreach($classes as $class) {
				foreach($class->getTps()->toArray() as $tp)
					array_push($list_tp,$tp);
			}
		
			//$list_tp = $repository->findAll();
				
			return $this->render('ControlBundle:Default:choixTP.html.twig', array(
				'user' => $user,
				'group' => $role,
				'list_tp' => $list_tp
				));
		}
    }
	
	public function UpdateInterfaceControleIndex($tp_id,$increment) { // increment permet de définir s'il faut augmenter (+1) ou diminuer (-1) l'index des interfaces utilisables
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();
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
			$param_system->setIndexInterface($start_index-1+$min_index);	
		$em->persist($param_system);
		$em->flush();
	}
	
	/**
     * @Route("/control/stopLab{tp_id}", name="stopLab")
     */
	public function stopLabAction($tp_id){
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();
		$em = $this->getDoctrine()->getManager();
		$vpn_delete_user_shell_file="script_delete_vpn_user.sh";		
		
		
		$param_system = $this->getDoctrine()->getRepository('AppBundle:Param_System')->findOneBy(array('id' => '1'));

		//$this->UpdateInterfaceControleIndex($tp_id,-1);
		//Inutile car cela peut entrainer des chevauchements.
		
		/*$repository = $this->getDoctrine()->getRepository('AppBundle:TP');
		$list_tp=array();
		$classes = $user->getClasses();
		foreach($classes as $class) {
			foreach($class->getTps()->toArray() as $tp)
				array_push($list_tp,$tp);
		}*/
			
		
		$logger = $this->get('logger');
		$script_name=$this->getParameter('script_stop_lab');
		
		$run=$em->getRepository('AppBundle:Run')->findOneBy(array('user'=>$user,'tp'=>$tp_id));
					
		$cmd="/usr/bin/python $script_name "." ".$run->getDirTpUser()." ".$run->getTpProcessName();

		//Rename xml file of TP to avoid to forget history
		$filename=$run->getDirTpUser()."/".$run->getTpProcessName().".xml";
		$filename_old=$filename.".".date('YmjsiH');
		
		rename($filename,$filename_old);
		
		$tp = $this->getDoctrine()->getRepository('AppBundle:TP')->find($tp_id);
		
		if ( $tp->getAccess()==="vpn") {
		// VPN access to the laboratory. We need to reserve IP Network for the user and for the devices
		
		$param_system = $this->getDoctrine()->getRepository('AppBundle:Param_System')->findOneBy(array('id' => '1'));
		$indexNetwork=$param_system->getIndexNetwork();
		$param_system->setIndexNetwork($indexNetwork-1);
		$em->persist($param_system);
		
		//Remove Affected_IP for devices
		$repository_affected_ip=$this->getDoctrine()->getRepository('AppBundle:Affected_IP');
		$affected_ips=$repository_affected_ip->findByidNetwork($run->getNetworkUsed());
	
		foreach($affected_ips as $affected_ip) {
			$em->remove($affected_ip);
		}
		//Remove Affected_IP for users
		$affected_ips=$repository_affected_ip->findByidNetwork($run->getNetworkUsedUser());
	
		foreach($affected_ips as $affected_ip) {
			$em->remove($affected_ip);
		}
		
		//For device network
		$network=$em->getRepository('AppBundle:NetworkUsed')->findOneById($run->getNetworkUsed());
		$em->remove($network);
		$network=$em->getRepository('AppBundle:NetworkUsed')->findOneById($run->getNetworkUsedUser());
		$em->remove($network);
		}		
		
		$em->remove($run);
		$em->flush();
		$output = array();
		exec($cmd,$output);
		$logger->info($cmd);
		$output = implode("",$output);
		$logger->info($output);
		
		$script_name=$this->getParameter('script_delnet');
					
		$cmd="/usr/bin/python $script_name "." ".$run->getDirTpUser();
	
		$logger->info($cmd);
		$logger->info($output);
		//echo $output;
		//$output = implode("",$output);
		$logger->info($output);
		
		if ( $tp->getAccess()==="vpn") {
		$script_name=$this->getParameter('script_delvpn');			
		$cmd="/usr/bin/python $script_name "." ".$run->getDirTpUser();
		//echo $cmd;
		$output = array();
		exec($cmd,$output);
		$logger->info($cmd);
		$output = implode("",$output);
		//echo $output."<br/>";
		$logger->info($output);
		
		$script_vpn_delete_user=$this->getParameter('script_delete_vpn_user');
		$cmd="/usr/bin/python ".$script_vpn_delete_user." ".$run->getDirTpUser()." ".$vpn_delete_user_shell_file;
		//echo $cmd;
		$output = array();
		exec($cmd,$output);
		$logger->info($cmd);
		$output = implode("",$output);
		//echo $output;
		$logger->info($output);
		}
		
		$filename=$run->getDirTpUser()."/script_addnet.sh";
		if (file_exists($filename)) unlink($filename);
		$filename=$run->getDirTpUser()."/script_delnet.sh";
		if (file_exists($filename)) unlink($filename);

		$filename=$run->getDirTpUser()."/script_ovs.sh";
		if (file_exists($filename)) unlink($filename);
		$filename=$run->getDirTpUser()."/script_vm.sh";
		if (file_exists($filename)) unlink($filename);
		$filename=$run->getDirTpUser()."/script_del.sh";
		if (file_exists($filename)) unlink($filename);
		$filename=$run->getDirTpUser()."/script_hosts";
		if (file_exists($filename)) unlink($filename);
		$filename=$run->getDirTpUser()."/script_user.sh";
		if (file_exists($filename)) unlink($filename);
		$filename=$run->getDirTpUser()."/script_reboot.sh";
		if (file_exists($filename)) unlink($filename);
		$filename=$run->getDirTpUser()."/script_addvpn_servvm.sh";
		if (file_exists($filename)) unlink($filename);
		$filename=$run->getDirTpUser()."/script_addvpn_frontend.sh";
		if (file_exists($filename)) unlink($filename);
		$filename=$run->getDirTpUser()."/script_addvpn_servvpn.sh";
		if (file_exists($filename)) unlink($filename);
		$filename=$run->getDirTpUser()."/script_delvpn_servvm.sh";
		if (file_exists($filename)) unlink($filename);
		$filename=$run->getDirTpUser()."/script_delvpn_frontend.sh";
		if (file_exists($filename)) unlink($filename);
		$filename=$run->getDirTpUser()."/script_delvpn_servvpn.sh";
		if (file_exists($filename)) unlink($filename);
		$filename=$run->getDirTpUser()."/script_create_vpn_user.sh";
		if (file_exists($filename)) unlink($filename);
		$filename=$run->getDirTpUser()."/".$vpn_delete_user_shell_file;
		if (file_exists($filename)) unlink($filename);
	
		
        return $this->forward('ControlBundle:Default:choixTP', array(
		'user' => $user,
		'group' => $group
		));
	}
	
	/**
     * @Route("/control/startLab/{tp_id}", name="startLab")
     */
    public function startLabAction($tp_id)
    {	
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$em = $this->getDoctrine()->getManager();
		$vpn_create_user_shell_file="script_create_vpn_user.sh";
		$vpn_delete_user_shell_file="script_delete_vpn_user.sh";
		
		$group=$user->getRole();
		$logger = $this->get('logger');
	
	// Ajouter la gestion de l'objet réservation
	// Faire le exec avec le fichier XML stocké par generate_xml
	$for_run=$this->generate_xml($tp_id);
	$script_name=$this->getParameter('script_start_lab');
	//$cmd="".escapeshellarg($script_name." ".$tp_array['lab_name_avec_id_absolutepath']);
	//$output=exec(sprintf('/usr/bin/python $script_name %s',escapeshellcmd($tp_array['lab_name_avec_id_absolutepath'])));
	//$output=exec(sprintf('/usr/bin/python $script_name %s',"/home/RLv2_fnolot/3VM_1ASA_883.xml"));
	
	//Je stocke dans un run les informations du TP executé :
	$run=new Run();
	$repository = $this->getDoctrine()->getRepository('AppBundle:TP');
    $tp = $repository->find($tp_id);
	$run->setTp($tp);
	$run->setTpProcessName($for_run['lab_name_instance']);
	$run->setUser($user);
	$run->setDirTpUser($for_run['dir']);

	if ( $tp->getAccess()==="vpn") {
		// VPN access to the laboratory. We need to reserve IP Network for the user and for the devices
				
		$run->setNetworkUsed($for_run['network_used']);
		
		$network_user=$for_run['network_user'];
		$run->setNetworkUsedUser($network_user);
	
		/*$repository_device=$this->getDoctrine()->getRepository('AppBundle:Device');
		foreach($for_run['devices'] as $device) {
			$ip=new Affected_IP();
			$ip->setIndexIp($i);
			$ip->setTpProcessName($for_run['lab_name_instance']);
			$ip->setIdNetwork($network_device);
			$ip->setUser(NULL);
			$ip->setDevice($repository_device->findOneById($device['id']));
			$em->persist($ip);
			$i++;
		}*/
	
		//For user network with the VPN

		//Affected_IP for the users of the class or for the group
		//Create vpn configuration file for each user
		$i=1;
		$classes = $user->getClasses();
		//Create a file with the list of all users
		file_put_contents($for_run['dir']."/".$vpn_create_user_shell_file,"#!/bin/bash\ncd /home/adminVM/easy-rsa/\nsource vars\n");
		file_put_contents($for_run['dir']."/".$vpn_delete_user_shell_file,"#!/bin/bash\ncd /home/adminVM/easy-rsa/\nsource vars\n");
		
		foreach ($classes as $class) {
			foreach ($class->getUsers() as $oneuser) {
			$ip=new Affected_IP();
			$ip->setIndexIp($i);
			$ip->setTpProcessName($for_run['lab_name_instance']);
			$ip->setIdNetwork($network_user);
			$ip->setUser($oneuser);
			$ip->setDevice(NULL);
			$em->persist($ip);
			$i++;
			file_put_contents($for_run['dir']."/".$vpn_create_user_shell_file,"/home/adminVM/easy-rsa/pkitool ".$oneuser->getLastname()."\n",FILE_APPEND);
			//file_put_contents($for_run['dir']."/".$vpn_create_user_shell_file,"/home/adminVM/easy-rsa/pkitool --sign ".$oneuser->getLastname()."\n",FILE_APPEND);
			file_put_contents($for_run['dir']."/".$vpn_create_user_shell_file,"/home/adminVM/client-config/make_config.sh ".$oneuser->getLastname()."\n",FILE_APPEND);
			file_put_contents($for_run['dir']."/".$vpn_delete_user_shell_file,"/home/adminVM/easy-rsa/revoke-full ".$oneuser->getLastname()."\n",FILE_APPEND);
			file_put_contents($for_run['dir']."/".$vpn_delete_user_shell_file,"/etc/init.d/openvpn restart\n",FILE_APPEND);
			}
		}
	}

	$em->persist($run);
    $em->flush();

	$cmd="/usr/bin/python \"$script_name\" \"".$for_run['lab_name_avec_id_absolutepath']."\" \"".$for_run['IPv4_Serv']."\" \"".$for_run['dir']."\" ".$this->getParameter('ansible_user')." ".$this->getParameter('ansible_pass');
	
	//echo $cmd;
	$output = array();
	exec($cmd,$output);
	$logger->info($cmd);
	$output = implode("",$output);
	//echo $output;
	$logger->info($output);
	$msg="";
	$msg_warning="";
	if (strstr($output,"SUCCESS")) {
			$msg="Laboratoire démarré avec succès";
			$this->get('session')->getFlashBag()->add('notice', $msg);
	}
	else {
			$msg_warning="Echec de démarrage";
			$this->get('session')->getFlashBag()->add('danger', $msg_warning);
	}

	if ( $tp->getAccess()==="vpn") {
		$script_name_addvpn=$this->getParameter('script_addvpn');
		$cmd="/usr/bin/python \"$script_name_addvpn\" "." ".$run->getDirTpUser();
		//echo $cmd;	
		$output = array();
		exec($cmd,$output);
		$logger->info($cmd);
		$output = implode("",$output);
		$logger->info($output);
		//echo $output."<br/>";
		
		if (strstr($output,"SUCCESS")) {
			$msg="Connexion VPN démarée avec succès";
			$this->get('session')->getFlashBag()->add('notice', $msg);
		}
		else {
			$msg_warning="Echec de mise en place de la connexion VPN";
			$this->get('session')->getFlashBag()->add('danger', $msg_warning);
		}
	
		$script_vpn_create_user=$this->getParameter('script_create_vpn_user');
		$cmd="/usr/bin/python ".$script_vpn_create_user." ".$run->getDirTpUser()." ".$vpn_create_user_shell_file;
		//echo $cmd;
		$output = array();
		exec($cmd,$output);
		$logger->info($cmd);
		$output = implode("",$output);
		//echo $output;
		$logger->info($output);
		if (strstr($output,"SUCCESS")) {
			$msg="Utilisateurs VPN créés avec succès";
			$this->get('session')->getFlashBag()->add('notice', $msg);
		}
		else {
			$msg_warning="Echec dans la création des utilisateurs du VPN";
			$this->get('session')->getFlashBag()->add('danger', $msg_warning);
		}
	}	
	
	
	
	
	$file_name='ControlBundle:TP:'.$tp->getNom().'.html.twig';
	
	// Ajouter paramètre avec les param pour chaque fenetre
	// indiquer telnet ou vnc et l'id du device à chaque fois
	// appel ainsi à un controller unique avec pour param l'id du device

	return $this->render($file_name, array(
		'user' => $user,
		'group' => $group,
		'tp_array' => $for_run,
		'output' => $cmd,
		'msg' => $msg
		));
		
	
	}
	
		/**
     * @Route("/control/viewLab/{tp_id}", name="viewLab")
     */
    public function viewLabAction($tp_id)
    {	// A FINIR D'ECRIRE - POUR L'INSTANT IL NE MARCHE PAS
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$em = $this->getDoctrine()->getManager();

		$group=$user->getRole();
		$logger = $this->get('logger');
	
	// Ajouter la gestion de l'objet réservation
	// Faire le exec avec le fichier XML stocké par generate_xml
	$for_run=$this->generate_xml($tp_id);
	$script_name=$this->getParameter('script_start_lab');
	//$cmd="".escapeshellarg($script_name." ".$tp_array['lab_name_avec_id_absolutepath']);
	//$output=exec(sprintf('/usr/bin/python $script_name %s',escapeshellcmd($tp_array['lab_name_avec_id_absolutepath'])));
	//$output=exec(sprintf('/usr/bin/python $script_name %s',"/home/RLv2_fnolot/3VM_1ASA_883.xml"));
	
	
	
	//$run=new Run();
	$repository = $this->getDoctrine()->getRepository('AppBundle:TP');
    $tp = $repository->find($tp_id);
	$run->setTp($tp);
	$run->setTpProcessName($for_run['lab_name_instance']);
	$run->setUser($user);
	$run->setDirTpUser($for_run['dir']);

	
		
	/*if ( $tp->getAccess()==="vpn") {
		// VPN access to the laboratory. We need to reserve IP Network for the user and for the devices
		
		$param_system = $this->getDoctrine()->getRepository('AppBundle:Param_System')->findOneBy(array('id' => '1'));
		$indexNetwork=$param_system->getIndexNetwork();
		$param_system->setIndexNetwork($indexNetwork+1);
		$em->persist($param_system);
		//For device network
		$networkcidr=$this->getParameter('network_lab'); // Network in CIDR (/X) notation
		$network_device_size=$this->getParameter('network_lab_max_size');
		list($network,$mask)=explode('/',$networkcidr);
		$network_size=(32-log($network_device_size,2));
		$network_device=new NetworkUsed();
		$network_device->setIpAddress(long2ip(ip2long($network)+$indexNetwork*$network_device_size));
		$network_device->setNetmask($this->createNetmaskAddr($network_size));
		$em->persist($network_device);
		$run->setNetworkUsed($network_device);
		
		//For user network with the VPN
		$networkcidr=$this->getParameter('network_user'); // Network in CIDR (/X) notation
		$network_device_size=$this->getParameter('network_user_max_size');
		list($network,$mask)=explode('/',$networkcidr);
		$network_size=(32-log($network_device_size,2));
		$network_device=new NetworkUsed();
		$network_device->setIpAddress(long2ip(ip2long($network)+$indexNetwork*$network_device_size));
		$network_device->setNetmask($this->createNetmaskAddr($network_size));
		$em->persist($network_device);
		$run->setNetworkUsedUser($network_device);
		//network_user=new NetworkUsed();*/
	//} 
	
	$em->persist($run);
	
    $em->flush();

	$cmd="/usr/bin/python \"$script_name\" \"".$for_run['lab_name_avec_id_absolutepath']."\" \"".$for_run['IPv4_Serv']."\" \"".$for_run['dir']."\" ".$this->getParameter('ansible_user')." ".$this->getParameter('ansible_pass');
	
	//echo $cmd;
	$output = array();
	exec($cmd,$output);
	$logger->info($cmd);
	$output = implode("",$output);
	$logger->info($output);

	
	
	$file_name='ControlBundle:TP:'.$tp->getNom().'.html.twig';
	
	// Ajouter paramètre avec les param pour chaque fenetre
	// indiquer telnet ou vnc et l'id du device à chaque fois
	// appel ainsi à un controller unique avec pour param l'id du device

	return $this->render($file_name, array(
		'user' => $user,
		'group' => $group,
		'tp_array' => $for_run,
		'output' => $cmd
		));
	}

	/**
     * @Route("/control/rebootVM/{tp_id}/{name}", name="rebootVM")
     */
	public function rebootVM($tp_id,$name){
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();
		$em = $this->getDoctrine()->getManager();

		$param_system = $this->getDoctrine()->getRepository('AppBundle:Param_System')->findOneBy(array('id' => '1'));

		//$this->UpdateInterfaceControleIndex($tp_id,-1);
		//Inutile car cela peut entrainer des chevauchements.

		
		$repository = $this->getDoctrine()->getRepository('AppBundle:TP');
		$tp = $repository->find($tp_id);
	
		$repository = $this->getDoctrine()->getRepository('AppBundle:Run');
        $run = $repository->findOneBy(array('user'=> $user));
				
		$tp_array=$this->read_xml($run->getDirTpUser(),$run->getTpProcessName());
		
		$logger = $this->get('logger');
		$script_name=$this->getParameter('script_reboot_vm');

		$run=$em->getRepository('AppBundle:Run')->findOneBy(array('user'=>$user,'tp'=>$tp_id));
					
		$cmd="/usr/bin/python $script_name "." ".$run->getDirTpUser()." \"".$name."\"";
		
		$output = array();
		exec($cmd,$output);
		
		$logger->info($cmd);
		$output = implode("",$output);
		//print_r($cmd." ".$output);
		$logger->info($output);
		if (strstr($output,"success")) {
			$msg=$name."  rebooté avec succès";
			$this->get('session')->getFlashBag()->add('notice', $msg);
		}
		else {
			$msg=$name."  non rebooté. Une erreur a eu lieu";
			$this->get('session')->getFlashBag()->add('danger', $msg);
		}
		$file_name='ControlBundle:TP:'.$tp->getNom().'.html.twig';

		return $this->render($file_name, array(
			'user' => $user,
			'group' => $group,
			'tp_array' => $tp_array,
			'output' => $cmd
		));
	}
	
	/**
     * @Route("/control/addnet/{tp_id}", name="addnet")
     */
	public function addnet($tp_id){
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();
		$em = $this->getDoctrine()->getManager();

		$param_system = $this->getDoctrine()->getRepository('AppBundle:Param_System')->findOneBy(array('id' => '1'));

		//$this->UpdateInterfaceControleIndex($tp_id,-1);
		//Inutile car cela peut entrainer des chevauchements.

		
		$repository = $this->getDoctrine()->getRepository('AppBundle:TP');
		$tp = $repository->find($tp_id);
	
		$repository = $this->getDoctrine()->getRepository('AppBundle:Run');
        $run = $repository->findOneBy(array('user'=> $user));
				
		$tp_array=$this->read_xml($run->getDirTpUser(),$run->getTpProcessName());
		
		$logger = $this->get('logger');
		$script_name=$this->getParameter('script_addnet');

		$run=$em->getRepository('AppBundle:Run')->findOneBy(array('user'=>$user,'tp'=>$tp_id));
					
		$cmd="/usr/bin/python $script_name "." ".$run->getDirTpUser();
		
		$output = array();
		exec($cmd,$output);
		
		$logger->info($cmd);
		$output = implode("",$output);
	
		$logger->info($output);
		
		if (strstr($output,"SUCCESS")) {
			$msg="Laboratoire connecté avec succès à Internet";
			$this->get('session')->getFlashBag()->add('notice', $msg);
		}
		else {
			$msg="Echec de connexion";
			$this->get('session')->getFlashBag()->add('danger', $msg);
		}
		$file_name='ControlBundle:TP:'.$tp->getNom().'.html.twig';

		return $this->render($file_name, array(
			'user' => $user,
			'group' => $group,
			'tp_array' => $tp_array,
			'output' => $cmd
		));
	}
	
	/**
     * @Route("/control/delnet/{tp_id}", name="delnet")
     */
	public function delnet($tp_id){
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();
		$em = $this->getDoctrine()->getManager();

		$param_system = $this->getDoctrine()->getRepository('AppBundle:Param_System')->findOneBy(array('id' => '1'));

		//$this->UpdateInterfaceControleIndex($tp_id,-1);
		//Inutile car cela peut entrainer des chevauchements.

		
		$repository = $this->getDoctrine()->getRepository('AppBundle:TP');
		$tp = $repository->find($tp_id);
	
		$repository = $this->getDoctrine()->getRepository('AppBundle:Run');
        $run = $repository->findOneBy(array('user'=> $user));
				
		$tp_array=$this->read_xml($run->getDirTpUser(),$run->getTpProcessName());
		
		$logger = $this->get('logger');
		$script_name=$this->getParameter('script_delnet');

		$run=$em->getRepository('AppBundle:Run')->findOneBy(array('user'=>$user,'tp'=>$tp_id));
					
		$cmd="/usr/bin/python $script_name "." ".$run->getDirTpUser();
		
		$output = array();
		exec($cmd,$output);
		
		$logger->info($cmd);
		$output = implode("",$output);
		
		$logger->info($output);
		
		if (strstr($output,"SUCCESS")) {
			$msg="Laboratoire déconnecté d'Internet avec succès";
			$this->get('session')->getFlashBag()->add('notice', $msg);
		}
		else {
			$msg="Erreur de déconnexion".$output;
			$this->get('session')->getFlashBag()->add('danger', $msg);
		}
		$file_name='ControlBundle:TP:'.$tp->getNom().'.html.twig';

		return $this->render($file_name, array(
			'user' => $user,
			'group' => $group,
			'tp_array' => $tp_array,
			'output' => $cmd
		));
	}
	
	
    public function generate_xml($tp_id) {
		$em = $this->getDoctrine()->getManager();

		$dir_prefix=$this->getParameter('homedir');
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();

		$param_system = $this->getDoctrine()->getRepository('AppBundle:Param_System')->findOneBy(array('id' => '1'));
		
		$repository = $this->getDoctrine()->getRepository('AppBundle:TP');
        $tp = $repository->find($tp_id);
		$lab=$tp->getLab();
		
		$rootNode = new \SimpleXMLElement("<?xml version='1.0' encoding='UTF-8' standalone='yes'?><lab></lab>" );
        $user_node=$rootNode->addChild('user');
		$index=$param_system->getIndexInterface();
		$indexControl=$param_system->getIndexInterfaceControle();
		$lab_name_tp=$lab->getNomlab();
		$lab_name=$lab_name_tp."_".$param_system->getIndexInterfaceControle();
		$Structure_tp['lab_name']=$lab_name_tp;
		$Structure_tp['lab_name_instance']=$lab_name;
		
		
		$nomlab_node=$rootNode->addChild('lab_name',$lab_name);
		$rootNode->addChild('tp_id',$tp_id);
		$rootNode->addChild('tp_managed',$tp->getManaged());
		$rootNode->addChild('tp_type',$tp->getType());
		$rootNode->addChild('tp_access',$tp->getAccess());
		$Structure_tp['tp_managed']=$tp->getManaged();
		$Structure_tp['tp_type']=$tp->getType();
		$Structure_tp['tp_access']=$tp->getAccess();
		
		$user_node->addAttribute('login',$user->getUsername());
		$nodes = $rootNode->addChild('nodes');
		$order=1;
		$devices=array();
		$repository_affected_ip_device = $this->getDoctrine()->getRepository('AppBundle:Affected_IP');
		$init = $rootNode->addChild('init');
		if ($Structure_tp['tp_access']==="vpn") {
			$i=1;
			$param_system = $this->getDoctrine()->getRepository('AppBundle:Param_System')->findOneBy(array('id' => '1'));
			$indexNetwork=$param_system->getIndexNetwork();
			$param_system->setIndexNetwork($indexNetwork+1);
			$em->persist($param_system);
			//For lab network
			$networkcidr=$this->getParameter('network_lab'); // Network in CIDR (/X) notation
			$network_device_size=$this->getParameter('network_lab_max_size');
			
			list($network,$mask)=explode('/',$networkcidr);
			$network_size=(32-log($network_device_size,2));
			$network_device=new NetworkUsed();
			$ip_address=long2ip(ip2long($network)+$indexNetwork*$network_device_size);
			$network_device->setIpAddress($ip_address);
			$network_device->setNetmask($network_size);
			$em->persist($network_device);
			
			$Structure_tp['network_used']=$network_device;
			$init->addChild('network_lab',$ip_address."/".$network_size);
			
			$networkcidr=$this->getParameter('network_user'); // Network in CIDR (/X) notation
			$network_device_size=$this->getParameter('network_user_max_size');
			list($network,$mask)=explode('/',$networkcidr);
			$network_user_size=(32-log($network_device_size,2));
			$network_user=new NetworkUsed();
			$ip_user_network=long2ip(ip2long($network)+$indexNetwork*$network_device_size);
			$network_user->setIpAddress($ip_user_network);
			$network_user->setNetmask($network_user_size);
			$em->persist($network_user);
			
			$init->addChild('network_user',$ip_user_network."/".$network_user_size);
			$Structure_tp['network_user']=$network_user;
			
		}
		foreach ($lab->getPod()->getDevices() as $dev) {
			$device=$nodes->addChild('device');
			if ($Structure_tp['tp_access']==="vpn") {
					$ip=new Affected_IP();
					$ip->setIndexIp($i);
					$ip->setTpProcessName($lab_name);
					$ip->setIdNetwork($network_device);
					$ip->setUser(NULL);
					$ip->setDevice($dev);
					$em->persist($ip);
					
					
				$direct_access=$device->addChild('direct_access');
				$direct_access->addChild('IPv4',
					$this->calculateIP($ip_address,$i)."/".$network_size);
				$direct_access->addChild('IPv6',"");
				$i++;
				}
			
			$property=$dev->getPropriete();	
			$device->addAttribute('property',$property);
			if ($property =='Switch') {
				$device->addChild('nom', $dev->getNom().$index);
				$device->addChild('nom_brute', $dev->getNom().$index);
			}
			else
			{
				$device->addChild('nom', $dev->getNom()."_".$tp_id);
				$device->addChild('nom_brute', $dev->getNom()."_".$tp_id);
			}
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
				if ($dev->getInterfaceControle()) { //Une interface de controle exist
					if ( $int->getId() != $dev->getInterfaceControle()->getId() ) { // Si l'interface actuelle n'est pas l'int. de controle
						$interface=$device->addChild('interface');
						$interface->addAttribute('id',$int->getId());
						$interface->addAttribute('physical_name',$int->getNomPhysique());
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
						$interface->addAttribute('physical_name',$int->getNomPhysique());
						$interface->addAttribute('virtual_name',$int->getNomVirtuel());
				}
			}
			$int_ctrl=$dev->getInterfaceControle();
			if ($int_ctrl) {
				$int_ctrl_node=$device->addChild('interface_control');
				$int_ctrl_node->addAttribute('id',$int_ctrl->getId());
				$int_ctrl_node->addAttribute('physical_name',$int_ctrl->getNomPhysique());
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
						$port=$this->getParameter('port_start_websocket')+$indexControl;
					if ($proto=="telnet")
						$port=$this->getParameter('port_start_telnet')+$indexControl;
					if ($proto=="vnc")
						$port=$this->getParameter('port_start_vnc')+$indexControl;
					if ($proto=="ssh")
						$port=$this->getParameter('port_start_ssh')+$indexControl;
					//$int_ctrl->getConfigReseau()->getPort()
					$indexControl++;
					$int_ctrl_node->addAttribute('port',$port);
					array_push($devices,array('id'=>$dev->getId(),
						'nom'=>$dev->getNom()."_".$tp_id,
						'nom_brute'=>$dev->getNom(),
						'type'=>$property,
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
				array_push($devices,array('id'=>$dev->getId(),
						'nom'=>$dev->getNom()."_".$tp_id,
						'nom_brute'=>$dev->getNom(),
						'type'=>$property,
						'protocol'=>"",
						'port'=>"",
						'IPv4'=>"",
						'IPv6'=>""
					));
			}
		}
		$Structure_tp['devices']=$devices;
		
		$serveur = $init->addChild('serveur');	
		$serveur->addChild('IPv4',$param_system->getIpv4());
		$Structure_tp['IPv4_Serv']=$param_system->getIpv4();
		$Structure_tp['IPv6_Serv']=$param_system->getIpv6();
		$serveur->addChild('IPv6',$param_system->getIpv6());
		$serveur->addChild('index_interface',$param_system->getIndexInterface());
		$serveur->addChild('index_interface_control',$param_system->getIndexInterface());
		$param_system->setIndexInterface($index);
		$param_system->setIndexInterfaceControle($indexControl);
		//$this->UpdateInterfaceControleIndex($tp_id,1);

		$response=new Response($rootNode->asXML());
		$response->headers->set('Content-Type', 'application/xml');
		#$disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT,'foo.xml');
		#$response->headers->set('Content-Disposition', $disposition);
        $dir=$dir_prefix.$user->getUsername();
		$filename=$dir.'/'.$lab_name.'.xml';
		$Structure_tp['lab_name_avec_id_absolutepath']=$filename;
		$Structure_tp['dir']=$dir;
		$Structure_tp['tp_id']=$tp_id;
			if (!is_dir($dir))
				mkdir($dir,0770);
		
		$fp = fopen($filename,'w');
	
		fwrite($fp,$rootNode->asXML());
		fclose($fp);

		return $Structure_tp;
    }
	
	public function xml_attribute($object, $attribute) {
		if(isset($object[$attribute]))
			return (string) $object[$attribute];
	}

	public function read_xml($tp_dir,$tp_instance_name) {
		$dir_prefix=$this->getParameter('homedir');
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();
		
		$repository = $this->getDoctrine()->getRepository('AppBundle:TP');
        
		$devices=array();
			
		$xml_file = simplexml_load_file($tp_dir."/".$tp_instance_name.".xml");
		$xml_devices = $xml_file->xpath('/lab/nodes/device');
		$tp_id = $xml_file->xpath('/lab/tp_id');
		$tp_managed = $xml_file->xpath('/lab/tp_managed');
		$tp_access = implode($xml_file->xpath('/lab/tp_access'));
		$tp_type = $xml_file->xpath('/lab/tp_type');
		
        $tp = $repository->find($tp_id[0]);
		$lab_name=$tp->getLab()->getNomlab();
		
		
		$protocol="";
		$port="";
		$IPv4="";
		$IPv6="";
		$nom_brute="";
		foreach ($xml_devices as $node) {
			$property=$this->xml_attribute($node,'property');
			if ( $property != "switch") {
			  $id=$this->xml_attribute($node,'id');		  
			  foreach ($node->children() as $element) {	  
					$nom_element=$element->getName();
				if ($nom_element=='nom') {
					$nom=(string) $element;
				}
				if ($nom_element=='nom_brute') {
					$nom_brute=(string) $element;
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
					'type'=>$property,
					'nom'=>$nom,
					'nom_brute'=>$nom_brute,
					'protocol'=>$protocol,
					'port'=>$port,
					'IPv4'=>$IPv4,
					'IPv6'=>$IPv6
				));	  
		  
		  //$protocol=$this->xml_attribute($protocole,'protocol');
			  
			}
		}
	$Structure_tp['devices']=$devices;
	$Structure_tp['tp_id']=(string) $tp_id[0];
	$Structure_tp['lab_name']=$lab_name;
	$Structure_tp['tp_managed']=$tp_managed;
	$Structure_tp['tp_type']=$tp_type;
	$Structure_tp['tp_access']=$tp_access;
		return $Structure_tp;

	}	

	public function MacEnd($nb) {
		return dechex(floor($nb/256)).":".dechex($nb%256);
	}

	
	function cidr2NetmaskAddr($cidr) { // return 255.255.255.240 from cidr2NetmaskAddr('194.234.213.0/28');
    $ta = substr($cidr, strpos($cidr, '/') + 1) * 1;
    $netmask = str_split(str_pad(str_pad('', $ta, '1'), 32, '0'), 8);
    foreach ($netmask as &$element) $element = bindec($element);
    return join('.', $netmask);
    }

	function NetmaskAddr2cidr($cidr) { // return 194.234.213.0/28 from NetmaskAddr2cidr('194.234.213.0','255.255.255.240');
    $ta = substr($cidr, strpos($cidr, '/') + 1) * 1;
    $netmask = str_split(str_pad(str_pad('', $ta, '1'), 32, '0'), 8);
    foreach ($netmask as &$element) $element = bindec($element);
    return join('.', $netmask);
    }

	function calculateIP($cidr,$indexip) { // return  194.234.213.5 from cidr2NetmaskAddr('194.234.213.0',5); 
	return long2ip(ip2long($cidr)+$indexip);
    }

	function createNetmaskAddr($bitcount) { //return 255.255.255.240 from createNetmaskAddr(28);
    $netmask = str_split(str_pad(str_pad('', $bitcount, '1'), 32, '0'), 8);
    foreach ($netmask as &$element) $element = bindec($element);
    return join('.', $netmask);
    }

	function cidr2NetAddr($cidr) { // return 194.234.213.0 from cidr2NetAddr('194.234.213.0/28');
    list($network,$mask)=explode('/',$cidr);
	return $network;
    }
}
