<?php

namespace AppBundle\Controller;

use AppBundle\Entity\ConfigReseau;
use AppBundle\Entity\Device;
use AppBundle\Form\ConfigReseauType;
use AppBundle\Form\DeviceType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class AddTpController extends Controller
{
    /**
     * @Route("/admin/add_tp", name="add")
     */
    public function add_tp()
    {
        $user = $this->get('security.token_storage')->getToken()->getUser();

        $device = new Device();
        $form = $this->get('form.factory')->create(new DeviceType(),$device);

                return $this->render(
                    'AppBundle::add_tp.html.twig',array(
                    'user' => $user,
                    'form' => $form->createView(),
                ));




    }


}
