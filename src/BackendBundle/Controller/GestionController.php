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
                return $this->redirect($this->generateUrl('list_'.$bundle));
            }else throw new NotFoundHttpException("Le device d'id ".$id." n'existe pas.");
        }
        return $this->redirect($this->generateUrl('list_'.$bundle));
    }




}