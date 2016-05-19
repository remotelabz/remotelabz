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
}