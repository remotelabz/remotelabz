<?php

namespace BackendBundle\Controller;

use AppBundle\Entity\Connexion;
use AppBundle\Entity\Hyperviseur;
use AppBundle\Entity\LAB;
use AppBundle\Entity\Network_Interface;
use AppBundle\Entity\Parameter;
use AppBundle\Entity\POD;
use AppBundle\Entity\Systeme;
use AppBundle\Entity\TP;
use AppBundle\Form\Connexion_select_podType;
use AppBundle\Form\ConnexionType;
use AppBundle\Form\DeviceType;
use AppBundle\Form\HyperviseurType;
use AppBundle\Form\LABType;
use AppBundle\Form\Network_InterfaceType;
use AppBundle\Form\ParameterType;
use AppBundle\Form\PODType;
use AppBundle\Form\SystemeType;
use UserBundle\Entity\User;
use UserBundle\Entity\Classe;
use UserBundle\Form\Type\AddClasseFormType;
use UserBundle\Form\Type\AddUserFormType;
use BackendBundle\Form\Type\SelectClasseFormType;
use BackendBundle\Form\Type\AddinclasseFormType;


use AppBundle\Form\TPType;
use Proxies\__CG__\AppBundle\Entity\ConfigReseau;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\HttpFoundation\Request;

use AppBundle\Entity\Device;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Response;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class DefaultController extends Controller
{

    /**
     * @Route("/admin/add_device", name="add_device")
     */
    public function Add_DeviceAction(Request $request)
    {
        $user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();
		
        $interface = new Network_Interface();
        $interfaceControle = new Network_Interface();
        $hyperviseur = new Hyperviseur();
        $parametre = new Parameter();
        $systeme = new Systeme();
        $device = new Device();

        $InterfaceControleform = $this->get('form.factory')->create(new Network_InterfaceType(), $interfaceControle, array('method' => 'POST'));
        $Interfaceform = $this->get('form.factory')->create(new Network_InterfaceType(), $interface, array('method' => 'POST'));
        $parametreForm = $this->get('form.factory')->create(new ParameterType(), $parametre, array('method' => 'POST'));
        $hyperForm = $this->get('form.factory')->create(new HyperviseurType(), $hyperviseur, array('method' => 'POST'));
        $systemForm = $this->get('form.factory')->create(new SystemeType(), $systeme, array('method' => 'POST'));
        $deviceForm = $this->get('form.factory')->create(new DeviceType(), $device, array('method' => 'POST'));

        $Interfaceform->remove('configreseau');

        if ('POST' === $request->getMethod()) {
            if ($InterfaceControleform->handleRequest($request)->isValid() and ($interfaceControle->getConfigReseau()->getIP() != null)) {
//                    echo 'interface de controle  ';
//                    die();
                $em1 = $this->getDoctrine()->getManager();
                $em1->persist($interfaceControle->getConfigReseau());
                $em1->persist($interfaceControle);
                $em1->flush();
              //  $request->getSession()->getFlashBag()->add('notice', 'Interface de contrôle ' . $interfaceControle->getNomVirtuel() . ' bien enregistrée.');
                return $this->redirect($this->generateUrl('add_device'));
            }
            if ($Interfaceform->handleRequest($request)->isValid()) {
                $em = $this->getDoctrine()->getManager();
				$em->persist($interface);
                $em->flush();
               // $request->getSession()->getFlashBag()->add('notice', 'Interface ' . $interface->getNomVirtuel() . ' bien enregistrée .');
                return $this->redirect($this->generateUrl('add_device'));
            }

            if ($parametreForm->handleRequest($request)->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->persist($parametre);
                $em->flush();
                $request->getSession()->getFlashBag()->add('notice', 'Paramètres enregistrés');
                return $this->redirect($this->generateUrl('add_device'
                ));
            }
            if ($hyperForm->handleRequest($request)->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->persist($hyperviseur);
                $em->flush();
                $request->getSession()->getFlashBag()->add('notice', 'Hyperviseur ajouté avec succès ');
                return $this->redirect($this->generateUrl('add_device'));
            }
            if ($systemForm->handleRequest($request)->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->persist($systeme);
                $em->flush();
                $request->getSession()->getFlashBag()->add('notice', 'Systeme ajouté avec succès ');
                return $this->redirect($this->generateUrl('add_device'));
            }
            if ($deviceForm->handleRequest($request)->isValid()) {
                $em = $this->getDoctrine()->getManager();
                if ($device->getInterfaceControle() != null) {
                    $em->persist($device->getInterfaceControle());
                    $device->setInterfaceControle($device->getInterfaceControle());
					$device->getInterfaceControle()->setDevice($device);
                }
                if ($device->getNetworkInterfaces() != null) {
                    foreach ($device->getNetworkInterfaces() as $net) {
                        $device->addNetworkInterface($net);
                    }
                }
                $em->persist($device);
                $em->flush();
                $request->getSession()->getFlashBag()->add('notice', 'Device ajouté avec succès ');
                return $this->redirect($this->generateUrl('add_device'));
            }
        }

        return $this->render(
            'BackendBundle::add_device.html.twig', array(
            'user' => $user,
			'group' => $group,
            'InterfaceControleform' => $InterfaceControleform->createView(),
            'Interfaceform' => $Interfaceform->createView(),
            'parametreForm' => $parametreForm->createView(),
            'hyperForm' => $hyperForm->createView(),
            'systemForm' => $systemForm->createView(),
            'deviceForm' => $deviceForm->createView(),
        ));
    }

    /**
     * @Route("/admin/add_pod", name="add_pod")
     */
    public function Add_PodAction(Request $request)
    {
        $user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();
		
        $pod = new POD();

        $podForm = $this->get('form.factory')->create(new PODType(), $pod, array('method' => 'POST'));

        if ('POST' === $request->getMethod()) {

            if ($podForm->handleRequest($request)->isValid()) {

                $em = $this->getDoctrine()->getManager();
//                $nomdev = array();
//                foreach ($pod->getDevices() as $dev) {
//                       array_push($nomdev ,$dev->getNom());
//                }


                foreach ($pod->getDevices() as $dev) {
//                    $pod->setNomDevice( $nomdev);
                    $pod->addDevice($dev);
                }

            }
            $em->persist($pod);
            $em->flush();
            $request->getSession()->getFlashBag()->add('notice', 'POD ajouté avec succès');
            return $this->redirect($this->generateUrl('add_pod'));
        }
        return $this->render(
            'BackendBundle::add_pod.html.twig', array(
            'user' => $user,
			'group' => $group,
            'podForm' => $podForm->createView()

        ));


    }

    /**
     * @Route("/admin/add_lab", name="add_lab")
     */
    public function Add_LabAction(Request $request)
    {
        $user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();
        $em = $this->getDoctrine()->getManager();
        $lab = new LAB();
        $labForm = $this->get('form.factory')->create(new LABType($em), $lab, array('method' => 'POST'));

        if ('POST' === $request->getMethod()) {
			$labForm->handleRequest($request);
            if ($labForm->isValid()) {
               
				foreach($lab->getConnexions() as $con) {
					//$lab->addConnexion($con);
					$con->addLab($lab);
				}
                $em->persist($lab);
                $em->flush();
				
                $request->getSession()->getFlashBag()->add('notice', 'Lab ajouté avec succès');
				
				
                //return $this->redirect($this->generateUrl('add_lab'));
            }
        }
        return $this->render(
            'BackendBundle::add_lab.html.twig', array(
            'user' => $user,
			'group' => $group,
            'labForm' => $labForm->createView()

        ));
    }

    /**
     * @Route("/admin/ajax_connexion_call", name="ajax_connexion_call")
     */
    public function ajaxAction(Request $request)
    {

        if ($request->isXmlHttpRequest()) // pour vérifier la présence d'une requete Ajax
        {
            $id = $request->request->get('id');

            if ($id != null) {
                $data = $this->getDoctrine()
                    ->getManager()
                    ->getRepository('AppBundle:Network_Interface')
                    ->Network_Interface($id);
                return new JsonResponse($data);
            }
        }
    }

    /**
     * @Route("/admin/ajax_connexion_pod", name="ajax_connexion_pod")
     */
    public function ajaxActionAddConnexion(Request $request)
    {
        if ($request->isXmlHttpRequest()) // pour vérifier la présence d'une requete Ajax
        {
            $id = $request->request->get('id');
            if ($id != null) {
                $data = $this->getDoctrine()
                    ->getManager()
                    ->getRepository('AppBundle:Connexion')
                    ->getConnexionByPOD($id);
                return new JsonResponse($data);
            }
        }
    }

    /**
     * @Route("/admin/add_connexion", name="add_connexion")
     */
    public function Add_Connexion(Request $request)
    {
        $user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();
		
        $pod = new Connexion();
        $form_pod = $this->get('form.factory')->create(new Connexion_select_podType(), $pod, array('method' => 'POST'));
        if ('POST' === $request->getMethod()) {
            if ($form_pod->handleRequest($request)->isValid() and ($pod->getPod()->getId() != null)) {
                $id_pod = $pod->getPod()->getId();
                return $this->redirect($this->generateUrl('add_connexion_after_getpod', array('pod_id' => $id_pod)));
            }
        }

        return $this->render(
            'BackendBundle::add_connexion_pod.html.twig', array(
            'user' => $user,
			'group'=> $group,
            'form_pod' => $form_pod->createView()
        ));
    }

    /**
     * @Route("/admin/add_connexion_after_getpod{pod_id}", name="add_connexion_after_getpod")
     */
    public function Add_Connexion_after_getpod(Request $request, $pod_id)
    {
        $em = $this->getDoctrine()->getManager();

        $connexion = new Connexion();

        $user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();
		
        $form_connexion = $this->get('form.factory')->create(new ConnexionType($pod_id, $em), $connexion, array('method' => 'POST'));
        $form_connexion->remove('pod');
        $form_connexion->remove('Suivant');
        if ('GET' === $request->getMethod()) {
            return $this->render(
                'BackendBundle::add_connexion.html.twig', array(
                'user' => $user,
				'group' => $group,
                'form_connexion' => $form_connexion->createView()
            ));
        }
        if ('POST' === $request->getMethod()) {
            $em = $this->getDoctrine()->getManager();

            $pod = $em->getRepository('AppBundle:POD')->findOneBy(array('id' => $pod_id));
            if ($form_connexion->handleRequest($request)->isValid()) {
                $connexion->setNomdevice1($connexion->getDevice1()->getNom());
                $connexion->setNomdevice2($connexion->getDevice2()->getNom());
                $connexion->setPod($pod);
                $em->persist($connexion);
                $em->flush();
                $request->getSession()->getFlashBag()->add('notice', 'connexion ajoutée');
                return $this->redirect($this->generateUrl('add_connexion'));
            }
        }

        return $this->redirect($this->generateUrl('add_connexion'));
    }

    /**
     * @Route("/admin/add_tp", name="add_tp")
     */
    public function addTp(Request $request)
    {
        $user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();

        $tp = new TP();
        $form_tp = $this->get('form.factory')->create(new TPType(), $tp, array('method' => 'POST'));
        if ('POST' === $request->getMethod()) {
            if ($form_tp->handleRequest($request)->isValid()) {
                $em = $this->getDoctrine()->getManager();
                
                $em->persist($tp);
                $em->flush();
                $request->getSession()->getFlashBag()->add('notice', 'TP ajouté');
                return $this->redirect($this->generateUrl('add_tp'));


            }

        }
//            if ($form_tp->handleRequest($request)->isValid()) {
//                $labs = $tp->getLabs();
//                foreach ($labs as $lab) {
//                    $tp->addLab($lab);
//                }
//            }

//        }


        return $this->render(
            'BackendBundle::add_tp.html.twig', array(
            'user' => $user,
			'group' => $group,
            'form_tp' => $form_tp->createView()

        ));
    }
	
	/**
     * @Route("/ens/add_classe", name="ens_add_classe")
     */
	public function add_classe(Request $request)
	{
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();

		$classe=new Classe;
		
		$form = $this->get('form.factory')->create(AddClasseFormType::class, $classe,array('method' => 'POST'));
		
		$repository = $this->getDoctrine()->getRepository('UserBundle:Classe');
		$list_classe = $repository->findAll();
				
		if ('POST' === $request->getMethod()) {

			if ($form->handleRequest($request)->isValid()) {
				$em = $this->getDoctrine()->getManager();
				//A FAIRE - Tester si cette classe existe déjà
				$em->persist($classe);
				$em->flush();
				$request->getSession()->getFlashBag()->add('notice', 'Classe ajoutée avec succès');
				return $this->redirect($this->generateUrl('ens_add_classe'));
			}
		}
    	return $this->render(
			'UserBundle:Gestion:add_classe.html.twig',array(
			'user' => $user,
			'group' => $group,
			'list_classe' => $list_classe,
			'form' => $form->createView(),
		));
	}
	
	/**
     * @Route("/admin/delete_classe", name="delete_classe")
     */
	public function delete_classe(Request $request)
	{
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();
		$userManager = $this->container->get('fos_user.user_manager');

        if($request->isXmlHttpRequest()) {
		
		$id='';
		$id=$request->get('data');
		if ($id != '') {

			$repository = $this->getDoctrine()->getRepository('UserBundle:Classe');
			$select_classe = new Classe();
			$classe_id=preg_split("/_/",$id)[1];
			$select_classe = $repository->find($classe_id);
			
			$em = $this->getDoctrine()->getManager();
			$em->remove($select_classe);
			$em->flush();
			$return="Supprimer";
			$result=$classe_id.":".$return;
			return new Response($result);
			}
		return new Response(0);
		}
		else return new Response(0);
	}

	/**
     * @Route("/ens/add_inclasse", name="ens_add_inclasse")
     */
	public function add_inclasse(Request $request)
	{
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();
		$em = $this->getDoctrine()->getManager();
		
		$repository = $this->getDoctrine()->getRepository('UserBundle:Classe');
		$list_classe = $repository->findAll();
		
		$repository = $this->getDoctrine()->getRepository('UserBundle:User');
		
		
		$classe = new Classe();
        $form = $this->get('form.factory')->create(new SelectClasseFormType(), $classe, array('method' => 'POST'));
        if ('POST' === $request->getMethod()) {
			
            if ($form->handleRequest($request)->isValid() and ($classe->getNom()->getId() != null)) {
				$id_classe=$classe->getNom()->getId();
                return $this->redirect($this->generateUrl('ens_add_studentinclasse', array('id_classe' => $id_classe)));
            }
        }
		return $this->render(
			'BackendBundle:User:add_inclasse.html.twig',array(
			'user' => $user,
			'group' => $group,
			'list_classe' => $list_classe,
			'form' => $form->createView(),
		));
	}
	
	/**
     * @Route("/ens/add_studentinclasse{id_classe}", name="ens_add_studentinclasse")
     */
	public function add_studentinclasse(Request $request, $id_classe)
	{
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();
		$em = $this->getDoctrine()->getManager();
		
		$repository = $this->getDoctrine()->getRepository('UserBundle:Classe');
		$classe = $repository->findOneBy(array('id' => $id_classe));
		
			
		$choix_etudiant=array();
        $form = $this->createForm(AddinclasseFormType::class, $choix_etudiant, array('method' => 'POST', 'id_classe' => $id_classe
		));
        if ('POST' === $request->getMethod()) {
			$form->handleRequest($request);

            if ($form->isValid()) {
				
				$repository = $this->getDoctrine()->getRepository('UserBundle:User');
					
				foreach($request->request->get('addinclasse_form')['firstname'] as $user_id) {
					$user=$repository->find($user_id);
					
					if (!in_array($user,$classe->getUsers()->toArray()))
						//$classe->addUser($user);
						$user->addClasse($classe);
				}
				$em->persist($classe);
				$em->flush();
				
                
            }
			return $this->redirect($this->generateUrl('ens_add_studentinclasse',array('id_classe' => $id_classe)));
		}
		
		return $this->render(
			'BackendBundle:User:add_studentinclasse.html.twig',array(
			'user' => $user,
			'group' => $group,
			'id_classe' => $id_classe,
			'list_student' => $classe->getUsers(),
			'form' => $form->createView(),
		));
	}
	
	/**
     * @Route("/ens/delete_student_inclasse", name="delete_student_inclasse")
     */
	public function delete_student_inclasse(Request $request)
	{
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getGroupe();
		$userManager = $this->container->get('fos_user.user_manager');

        if($request->isXmlHttpRequest()) {
		
		$id='';
		$id=$request->get('data');
		if ($id != '') {
			$select_classe = new Classe();
			$classe_id=preg_split("/_/",$id)[2];
			$student_id=preg_split("/_/",$id)[1];
			$select_classe = $this->getDoctrine()->getRepository('UserBundle:Classe')->find($classe_id);
			$select_student = $this->getDoctrine()->getRepository('UserBundle:User')->find($student_id);

			$em = $this->getDoctrine()->getManager();
			//$select_classe->removeUser($select_student);
			$select_student->removeClasse($select_classe);
			$em->flush();
			$return="Supprimer";
			$result=$student_id.":".$return;
			
			return new Response($result);
			}
		return new Response(0);
		}
		else return new Response(0);
	}
}


