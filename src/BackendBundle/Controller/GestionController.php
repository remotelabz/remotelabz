<?php

namespace BackendBundle\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GestionController extends Controller
{
	/**
     * @Route("/admin/list_system", name="list_system")
     */
    public function list_system(){
        $user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();
		
        $repository = $this->getDoctrine()->getRepository('AppBundle:Systeme');

        $list_system = $repository->findAll();


        return $this->render(
            'BackendBundle:Gestion:list_system.html.twig',array(
            'user' => $user,
			'group' => $group,
            'list_system' => $list_system
        ));

    }
	
    /**
     * @Route("/admin/list_device", name="list_Device")
     */
    public function list_device(){
        $user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();
		
        $repository = $this->getDoctrine()->getRepository('AppBundle:Device');

        $list_device = $repository->findAll();


        return $this->render(
            'BackendBundle:Gestion:list_device.html.twig',array(
            'user' => $user,
			'group' => $group,
            'list_device' => $list_device
        ));

    }
    /**
     * @Route("/admin/list_interface", name="list_Network_Interface")
     */
    public function list_interface(){
        $user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();
        $repository = $this->getDoctrine()->getRepository('AppBundle:Network_Interface');
		$repo = $this->getDoctrine()->getRepository('AppBundle:Device');

        $list_interface = $repository->getInterfaceForList();
		$list_interfaceControle = $repo->getControleInterfaceForList();


        return $this->render(
            'BackendBundle:Gestion:list_interface.html.twig',array(
            'user' 						=> $user,
			'group'	=>$group,
			'list_interfaceControle'	=> $list_interfaceControle,
            'list_interface'			 => $list_interface
        ));

    }
	 /**
     * @Route("/admin/list_ConfigReseau", name="list_ConfigReseau")
     */
    public function list_ConfigReseau(){
        $user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();
		$repo = $this->getDoctrine()->getRepository('AppBundle:Network_Interface');
		$list_interfaceControle = $repo->getInterfaceControleForList();


        return $this->render(
            'BackendBundle:Gestion:list_configReseau.html.twig',array(
            'user' 						=> $user,
			'group'=> $group,
			'list_interfaceControle'	=> $list_interfaceControle,
        ));

    }
    /**
     * @Route("/admin/list_pod", name="list_POD")
     */
    public function list_pod(){
        $user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();
        $repository = $this->getDoctrine()->getRepository('AppBundle:POD');

        $list_pod = $repository->findAll();


        return $this->render(
            'BackendBundle:Gestion:list_pod.html.twig',array(
            'user' => $user,
			'group' => $group,            'list_pod' => $list_pod
        ));

    }
    /**
     * @Route("/admin/list_connexion", name="list_Connexion")
     */
    public function list_connexion(){
        $user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();
        $repository = $this->getDoctrine()->getRepository('AppBundle:Connexion');

        $list_connexion = $repository->findAll();


        return $this->render(
            'BackendBundle:Gestion:list_connexion.html.twig',array(
            'user' => $user,
			'group' => $group,            'list_connexion' => $list_connexion
        ));

    }
    /**
     * @Route("/admin/list_lab", name="list_LAB")
     */
    public function list_lab(){
        $user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();
        $repository = $this->getDoctrine()->getRepository('AppBundle:LAB');

        $list_lab = $repository->findAll();


        return $this->render(
            'BackendBundle:Gestion:list_lab.html.twig',array(
            'user' => $user,
			'group' => $group,            'list_lab' => $list_lab
        ));

    }
    /**
     * @Route("/admin/list_tp", name="list_TP")
     */
    public function list_tp(){
        $user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();
        $repository = $this->getDoctrine()->getRepository('AppBundle:TP');

        $list_tp = $repository->findAll();


        return $this->render(
            'BackendBundle:Gestion:list_tp.html.twig',array(
            'user' => $user,
			'group' => $group,            'list_tp' => $list_tp
        ));

    }
	 /**
     * @Route("/admin/list_parameter", name="list_Parameter")
     */
    public function list_parameter(){
        $user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();
        $repository = $this->getDoctrine()->getRepository('AppBundle:Parameter');

        $list_parameter = $repository->findAll();

        return $this->render(
            'BackendBundle:Gestion:list_parameter.html.twig',array(
            'user' => $user,
			'group' => $group,            'list_parameter' => $list_parameter
        ));

    }
	 /**
     * @Route("/admin/list_hyperviseur", name="list_Hyperviseur")
     */
    public function list_hyperviseur(){
        $user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();
        $repository = $this->getDoctrine()->getRepository('AppBundle:Hyperviseur');

        $list_hyperviseur = $repository->findAll();


        return $this->render(
            'BackendBundle:Gestion:list_hyperviseur.html.twig',array(
            'user' => $user,
			'group' => $group,            'list_hyperviseur' => $list_hyperviseur
        ));

    }
	 /**
     * @Route("/admin/list_systeme", name="list_Systeme")
     */
    public function list_systeme(){
        $user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();
        $repository = $this->getDoctrine()->getRepository('AppBundle:Systeme');

        $list_systeme = $repository->findAll();


        return $this->render(
            'BackendBundle:Gestion:list_systeme.html.twig',array(
            'user' => $user,
			'group' => $group, 'list_systeme' => $list_systeme
			));

    }

    /**
     * @Route("/admin/delete_entite{id}", name="delete_entite")
     */
    public function delete_device($id, Request $request){
       $bundle = $request->query->get('bundle');
        if ($id != null) {
            $em = $this->getDoctrine()->getManager();
            $entite= $em->getRepository('AppBundle:'.$bundle)->findOneBy(array('id' => $id));
			if ($bundle="TP")
				$run_exist=$em->getRepository('AppBundle:Run')->findOneBy(array('tp' => $id));
            if ($entite != null) {
				if ($bundle=="TP" && $run_exist != null) {
					$request->getSession()->getFlashBag()->add('notice', 'Le TP est en cours d\'exécution');
					return $this->redirect($this->generateUrl('list_'.$bundle));
				}
				else {					
                $em->remove($entite);
                $em->flush();
				if ($bundle=="TP") {
					unlink($this->getParameter('tp_twig_directory').'/'.$entite->getNom().'.html.twig');
					unlink($this->getParameter('tp_directory').'/'.$entite->getNom().'.html');
				}
				$request->getSession()->getFlashBag()->add('notice', "le "." ".$bundle." a bien été supprimée.");
                return $this->redirect($this->generateUrl('list_'.$bundle));
				}
            }
				else throw new NotFoundHttpException("Le device d'id ".$id." n'existe pas.");
        }
        return $this->redirect($this->generateUrl('list_'.$bundle));
    }
	
	
	
    /**
     * @Route("/admin/show_tp{id}", name="show_tp")
     */
    public function show_tp($id,Request $request){
        $em = $this->getDoctrine()->getManager();
        $tp= $em->getRepository('AppBundle:TP')->findOneBy(array('id' => $id));
        if ($tp != null) {
            $chemin = $tp->getFile();
            if ($chemin != "") {
            $response = new Response();
            $response->setContent(file_get_contents($chemin));
            $response->headers->set(
                'Content-Type',
                'application/pdf'
            ); // Affiche le pdf au lieux de le télécharger
            $response->headers->set('Content-disposition', 'filename=' . $fichier);

            return $response;
			}
			else return $this->redirectToRoute('list_TP');
        }
    }
}