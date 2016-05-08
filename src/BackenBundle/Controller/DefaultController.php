<?php

namespace BackenBundle\Controller;

use AppBundle\Entity\Network_Interface;
use AppBundle\Form\DeviceType;
use AppBundle\Form\Network_InterfaceType;
use AppBundle\Form\TPType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Entity\Device;

use AppBundle\Entity\TP;
class DefaultController extends Controller
{
    public function addTpAction(Request $request)
    {


        $user = $this->get('security.token_storage')->getToken()->getUser();
        $interfaces = new Network_Interface();
        $interfaceControle = new Network_Interface();

        $InterfaceControleform = $this->get('form.factory')->create(new Network_InterfaceType(), $interfaceControle, array('method' => 'POST'));
        $Interfacesform = $this->get('form.factory')->create(new Network_InterfaceType(), $interfaces, array('method' => 'POST'));

        $Interfacesform->remove('configreseau');

        if ('POST' === $request->getMethod()){

            if ($InterfaceControleform->handleRequest($request)->isValid()) {

//                echo $interfaceControle->getNomInterface();
//                die();
               $em = $this->getDoctrine()->getManager();

                $em->persist($interfaceControle->getConfigReseau());
                $em->persist($interfaceControle);
                $em->flush();

                $request->getSession()->getFlashBag()->add('notice', 'Interface de controle  bien enregistrée.');

                return $this->redirect($this->generateUrl('ajout_interface', array(
                    'user' => $user,
                    'form' => $InterfaceControleform
                )));
            }


            if ($Interfacesform->handleRequest($request)->isValid()) {


                echo $interfaces->getNomInterface();
                die();

                $em = $this->getDoctrine()->getManager();

                $em->persist($interfaces);
                $em->flush();

                $request->getSession()->getFlashBag()->add('notice', 'Interface de controle  bien enregistrée.');

                return $this->redirect($this->generateUrl('ajout_interface', array(
                    'user' => $user,
                    'form' => $InterfaceControleform
                )));
            }
        }




        return $this->render(
            'BackenBundle::add_interfaces.html.twig',array(
            'user' => $user,
            'InterfaceControleform' => $InterfaceControleform->createView(),
            'Interfacesform' => $Interfacesform->createView(),
        ));
    }
    public function viewAction()
    {


        return  new Response('nook');

    }


}
