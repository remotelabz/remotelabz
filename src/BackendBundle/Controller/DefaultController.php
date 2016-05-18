<?php

namespace BackendBundle\Controller;

use AppBundle\Entity\Hyperviseur;
use AppBundle\Entity\Network_Interface;
use AppBundle\Entity\Parameter;
use AppBundle\Entity\Systeme;
use AppBundle\Form\DeviceType;
use AppBundle\Form\HyperviseurType;
use AppBundle\Form\Network_InterfaceType;
use AppBundle\Form\ParameterType;
use AppBundle\Form\SystemeType;
use AppBundle\Form\TPType;

use Proxies\__CG__\AppBundle\Entity\ConfigReseau;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Entity\Device;
use AppBundle\Entity\TP;


class DefaultController extends Controller
{	
	/**
     * @Route("/admin/add_device", name="add_device")
     */	
    public function add_TpAction(Request $request)
    {
        $user = $this->get('security.token_storage')->getToken()->getUser();

        $interfaces = new Network_Interface();
        $interfaceControle = new Network_Interface();
        $hyperviseur = new Hyperviseur();
        $parametre = new Parameter();
        $systeme = new Systeme();
        $device = new Device();

        $InterfaceControleform = $this->get('form.factory')->create(new Network_InterfaceType(), $interfaceControle, array('method' => 'POST'));
        $Interfacesform = $this->get('form.factory')->create(new Network_InterfaceType(), $interfaces, array('method' => 'POST'));
        $parametreForm = $this->get('form.factory')->create(new ParameterType(),$parametre, array('method' => 'POST'));
        $hyperForm = $this->get('form.factory')->create(new HyperviseurType(),$hyperviseur, array('method' => 'POST'));
        $systemForm = $this->get('form.factory')->create(new SystemeType(),$systeme, array('method' => 'POST'));

        $deviceForm = $this->get('form.factory')->create(new DeviceType(), $device, array('method' => 'POST'));

        $Interfacesform->remove('configreseau');

        if ('POST' === $request->getMethod()){
            if ($InterfaceControleform->handleRequest($request)->isValid()) {
               $em = $this->getDoctrine()->getManager();
                $em->persist($interfaceControle->getConfigReseau());
                $em->persist($interfaceControle);
                $em->flush();
                $request->getSession()->getFlashBag()->add('notice', 'Interface de controle  bien enregistrée.');
                return $this->redirect($this->generateUrl('add_device'));
            }
            if ($Interfacesform->handleRequest($request)->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->persist($interfaces);


                $em->flush();
                $request->getSession()->getFlashBag()->add('notice', 'Interface de controle  bien enregistrée.');
                return $this->redirect($this->generateUrl('add_device'));
            }
            if ($parametreForm->handleRequest($request)->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->persist($parametre);
                $em->flush();
                $request->getSession()->getFlashBag()->add('notice', 'parametres enregistrés');
                return $this->redirect($this->generateUrl('add_device'
                ));
            }
            if ($hyperForm->handleRequest($request)->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->persist($hyperviseur);
                $em->flush();
                $request->getSession()->getFlashBag()->add('notice', 'hyperviseur ajouté avec succé ');
                return $this->redirect($this->generateUrl('add_device'));
            }
            if ($systemForm->handleRequest($request)->isValid()) {
                $em = $this->getDoctrine()->getManager();
                $em->persist($systeme);
                $em->flush();
                $request->getSession()->getFlashBag()->add('notice', 'Systeme ajouté avec succé ');
                return $this->redirect($this->generateUrl('add_device'));
            }
            if ($deviceForm->handleRequest($request)->isValid()) {
                $em = $this->getDoctrine()->getManager();
                if ($device->getInterfaceControle() != null){
                    $em->persist($device->getInterfaceControle());
                    $em->flush();
                }
                $device->setInterfaceControle($device->getInterfaceControle());
                $em->persist($device);
                $em->flush();
                $request->getSession()->getFlashBag()->add('notice', 'device ajouté avec succé  ');
                return $this->redirect($this->generateUrl('add_device'));
            }
        }



        return $this->render(
            'BackendBundle::add_device.html.twig',array(
            'user'                  => $user,
            'InterfaceControleform' => $InterfaceControleform->createView(),
            'Interfacesform'        => $Interfacesform->createView(),
            'parametreForm'         => $parametreForm->createView(),
            'hyperForm'             =>$hyperForm->createView(),
            'systemForm'            => $systemForm->createView(),
            'deviceForm'            => $deviceForm->createView(),
        ));
    }

    /**
     * @Route("/admin/test", name="test")
     */
    public function viewAction()
    {

        $net = new Network_Interface();
        $conf = new \AppBundle\Entity\ConfigReseau();


        $net->setNomInterface('test4');
        $net->setConfigReseau($conf);


        $em = $this->getDoctrine()->getManager();
        $em->persist($net);
        $em->flush();

        return  new Response('good');

    }


}
