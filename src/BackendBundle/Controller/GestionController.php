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
use Symfony\Component\HttpFoundation\Request;


class GestionController extends Controller
{
    /**
     * @Route("/admin/list_device", name="list_device")
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
     * @Route("/admin/delete_device{id}", name="delete_device")
     */
    public function delete_device($id){
        $user = $this->get('security.token_storage')->getToken()->getUser();
        if ($id != null) {
            $em = $this->getDoctrine()->getManager();
            $device = $em->getRepository('AppBundle:Device')->findOneBy(array('id' => $id));
            if ($device != null) {
                $em->remove($device);
                $em->flush();
                return $this->redirect($this->generateUrl('list_device'));
            }else throw new NotFoundHttpException("Le device d'id ".$id." n'existe pas.");
        }
        return $this->redirect($this->generateUrl('list_device'));
    }
}