<?php

namespace BackenBundle\Controller;

use AppBundle\Form\TPType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\HttpFoundation\Response;

use AppBundle\Entity\TP;
class DefaultController extends Controller
{
    public function addTpAction()
    {


        $user = $this->get('security.token_storage')->getToken()->getUser();

        $tp = new TP();
        $form = $this->get('form.factory')->create(new TPType(),$tp);

        return $this->render(
            'BackenBundle::add_tp.html.twig',array(
            'user' => $user,
            'form' => $form->createView(),
        ));
    }
    public function viewAction()
    {


        return  new Response('nook');

    }


}
