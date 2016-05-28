<?php
/**
 * Created by PhpStorm.
 * User: zohir
 * Date: 20/05/2016
 * Time: 01:00
 */

namespace BackendBundle\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class GestionController extends Controller
{
    /**
     * @Route("/admin/list_device", name="list_Device")
     */
    public function list_device(){
        $user = $this->get('security.token_storage')->getToken()->getUser();

        $repository = $this->getDoctrine()->getRepository('AppBundle:Device');

        $list_device = $repository->findAll();


        return $this->render(
            'BackendBundle:Gestion:list_device.html.twig',array(
            'user' => $user,
            'list_device' => $list_device
        ));

    }
    /**
     * @Route("/admin/list_interface", name="list_Network_Interface")
     */
    public function list_interface(){
        $user = $this->get('security.token_storage')->getToken()->getUser();

        $repository = $this->getDoctrine()->getRepository('AppBundle:Network_Interface');

        $list_interface = $repository->findAll();


        return $this->render(
            'BackendBundle:Gestion:list_interface.html.twig',array(
            'user' => $user,
            'list_interface' => $list_interface
        ));

    }
    /**
     * @Route("/admin/list_pod", name="list_POD")
     */
    public function list_pod(){
        $user = $this->get('security.token_storage')->getToken()->getUser();

        $repository = $this->getDoctrine()->getRepository('AppBundle:POD');

        $list_pod = $repository->findAll();


        return $this->render(
            'BackendBundle:Gestion:list_pod.html.twig',array(
            'user' => $user,
            'list_pod' => $list_pod
        ));

    }
    /**
     * @Route("/admin/list_connexion", name="list_Connexion")
     */
    public function list_connexion(){
        $user = $this->get('security.token_storage')->getToken()->getUser();

        $repository = $this->getDoctrine()->getRepository('AppBundle:Connexion');

        $list_connexion = $repository->findAll();


        return $this->render(
            'BackendBundle:Gestion:list_connexion.html.twig',array(
            'user' => $user,
            'list_connexion' => $list_connexion
        ));

    }
    /**
     * @Route("/admin/list_lab", name="list_LAB")
     */
    public function list_lab(){
        $user = $this->get('security.token_storage')->getToken()->getUser();

        $repository = $this->getDoctrine()->getRepository('AppBundle:LAB');

        $list_lab = $repository->findAll();


        return $this->render(
            'BackendBundle:Gestion:list_LAB.html.twig',array(
            'user' => $user,
            'list_lab' => $list_lab
        ));

    }
    /**
     * @Route("/admin/list_tp", name="list_TP")
     */
    public function list_tp(){
        $user = $this->get('security.token_storage')->getToken()->getUser();

        $repository = $this->getDoctrine()->getRepository('AppBundle:TP');

        $list_tp = $repository->findAll();


        return $this->render(
            'BackendBundle:Gestion:list_tp.html.twig',array(
            'user' => $user,
            'list_tp' => $list_tp
        ));

    }
	 /**
     * @Route("/admin/list_parameter", name="list_Parameter")
     */
    public function list_parameter(){
        $user = $this->get('security.token_storage')->getToken()->getUser();

        $repository = $this->getDoctrine()->getRepository('AppBundle:Parameter');

        $list_parameter = $repository->findAll();


        return $this->render(
            'BackendBundle:Gestion:list_parameter.html.twig',array(
            'user' => $user,
            'list_parameter' => $list_parameter
        ));

    }
	 /**
     * @Route("/admin/list_hyperviseur", name="list_Hyperviseur")
     */
    public function list_hyperviseur(){
        $user = $this->get('security.token_storage')->getToken()->getUser();

        $repository = $this->getDoctrine()->getRepository('AppBundle:Hyperviseur');

        $list_hyperviseur = $repository->findAll();


        return $this->render(
            'BackendBundle:Gestion:list_hyperviseur.html.twig',array(
            'user' => $user,
            'list_hyperviseur' => $list_hyperviseur
        ));

    }
	 /**
     * @Route("/admin/list_systeme", name="list_Systeme")
     */
    public function list_systeme(){
        $user = $this->get('security.token_storage')->getToken()->getUser();

        $repository = $this->getDoctrine()->getRepository('AppBundle:Systeme');

        $list_systeme = $repository->findAll();


        return $this->render(
            'BackendBundle:Gestion:list_systeme.html.twig',array(
            'user' => $user,
            'list_systeme' => $list_systeme
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
            if ($entite != null) {
                $em->remove($entite);
                $em->flush();
				$request->getSession()->getFlashBag()->add('notice', "le "." ".$bundle." a bien été supprimée.");
                return $this->redirect($this->generateUrl('list_'.$bundle));
            }else throw new NotFoundHttpException("Le device d'id ".$id." n'existe pas.");
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
            $chemin = $tp->getWebPath();
            $fichier = $id.".pdf";
            $response = new Response();
            $response->setContent(file_get_contents($chemin . $fichier));
            $response->headers->set(
                'Content-Type',
                'application/pdf'
            ); // Affiche le pdf au lieux de le télécharger
            $response->headers->set('Content-disposition', 'filename=' . $fichier);

            return $response;
        }


    }




}