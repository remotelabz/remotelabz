<?php

namespace BackendBundle\Controller;

use AppBundle\Entity\Connexion;
use AppBundle\Entity\Hyperviseur;
use AppBundle\Entity\LAB;
use AppBundle\Entity\Network_Interface;
use AppBundle\Entity\Parameter;
use AppBundle\Entity\POD;
use AppBundle\Entity\Systeme;
use AppBundle\Form\ConnexionType;
use AppBundle\Form\DeviceType;
use AppBundle\Form\HyperviseurType;
use AppBundle\Form\LABType;
use AppBundle\Form\Network_InterfaceType;
use AppBundle\Form\ParameterType;
use AppBundle\Form\PODType;
use AppBundle\Form\SystemeType;


use Proxies\__CG__\AppBundle\Entity\ConfigReseau;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\HttpFoundation\Request;

use AppBundle\Entity\Device;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;





class DefaultController extends Controller
{	
	/**
     * @Route("/admin/add_device", name="add_device")
     */	
    public function Add_DeviceAction(Request $request)
    {
        $user = $this->get('security.token_storage')->getToken()->getUser();

        $interface = new Network_Interface();
        $interfaceControle = new Network_Interface();
        $hyperviseur = new Hyperviseur();
        $parametre = new Parameter();
        $systeme = new Systeme();
        $device = new Device();

        $InterfaceControleform = $this->get('form.factory')->create(new Network_InterfaceType(), $interfaceControle, array('method' => 'POST'));
        $Interfaceform = $this->get('form.factory')->create(new Network_InterfaceType(), $interface, array('method' => 'POST'));
        $parametreForm = $this->get('form.factory')->create(new ParameterType(),$parametre, array('method' => 'POST'));
        $hyperForm = $this->get('form.factory')->create(new HyperviseurType(),$hyperviseur, array('method' => 'POST'));
        $systemForm = $this->get('form.factory')->create(new SystemeType(),$systeme, array('method' => 'POST'));

        $deviceForm = $this->get('form.factory')->create(new DeviceType(), $device, array('method' => 'POST'));

        $Interfaceform->remove('configreseau');

        if ('POST' === $request->getMethod()){


                if ($InterfaceControleform->handleRequest($request)->isValid() and ($interfaceControle->getConfigReseau()->getIP() != null)) {
//                    echo 'interface de controle  ';
//                    die();
                    $em1 = $this->getDoctrine()->getManager();
                    $em1->persist($interfaceControle->getConfigReseau());
                    $em1->persist($interfaceControle);
                    $em1->flush();
                    $request->getSession()->getFlashBag()->add('notice', 'Interface de controle '.$interfaceControle->getNomInterface(). ' bien enregistrée.');
                    return $this->redirect($this->generateUrl('add_device'));
                }
            if ($Interfaceform->handleRequest($request)->isValid() ) {
                $em = $this->getDoctrine()->getManager();
                $em->persist($interface);
                $em->flush();
                $request->getSession()->getFlashBag()->add('notice', 'Interface  '.$interface->getNomInterface(). ' bien enregistrée .');
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
                    $device->setInterfaceControle($device->getInterfaceControle());
                }
                if ($device->getNetworkInterfaces() != null){

                   foreach($device->getNetworkInterfaces()  as $net) {
                       $device->addNetworkInterface($net);
                   }
                }
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
            'Interfaceform'        => $Interfaceform->createView(),
            'parametreForm'         => $parametreForm->createView(),
            'hyperForm'             =>$hyperForm->createView(),
            'systemForm'            => $systemForm->createView(),
            'deviceForm'            => $deviceForm->createView(),
        ));
    }

    /**
     * @Route("/admin/add_pod", name="add_pod")
     */
        public function Add_PodAction(Request $request)
    {
        $user = $this->get('security.token_storage')->getToken()->getUser();

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
            $request->getSession()->getFlashBag()->add('notice', 'pod ajouté  ');
            return $this->redirect($this->generateUrl('add_pod'));
        }
        return $this->render(
            'BackendBundle::add_pod.html.twig',array(
            'user'                  => $user,
            'podForm' => $podForm->createView()

        ));



    }
    /**
     * @Route("/admin/add_lab", name="add_lab")
     */
    public function Add_LabAction(Request $request)
    {
        $user = $this->get('security.token_storage')->getToken()->getUser();
        $lab = new LAB();
        $labForm = $this->get('form.factory')->create(new LABType(), $lab, array('method' => 'POST'));

        if ('POST' === $request->getMethod()) {
            if ($labForm->handleRequest($request)->isValid()) {
                $em = $this->getDoctrine()->getManager();
                foreach ($lab->getPod() as $pod) {
//                    $pod->setNomDevice( $nomdev);
                    $lab->addPod($pod);
                }

            }
            $em->persist($lab);
            $em->flush();
            $request->getSession()->getFlashBag()->add('notice', 'lab ajouter  ');
            return $this->redirect($this->generateUrl('add_lab'));
        }
        return $this->render(
            'BackendBundle::add_lab.html.twig', array(
            'user' => $user,
            'labForm' => $labForm->createView()

        ));
    }
    /**
     * @Route("/admin/ajax_connexion_call", name="ajax_connexion_call")
     */
    public function ajaxAction(Request $request) {

        if (! $request->isXmlHttpRequest()) {
            throw new NotFoundHttpException();
        }

        // Get the pod ID
        $id = $request->query->get('pod_id');
        $result = array();
        // Return a list of device, based on the selected pod
        $repo = $this->getDoctrine()->getManager()->getRepository('AppBundle:Device');
        $devices = $repo->findByPod($id);
        foreach ($devices as $device) {
            $result[$device->getNom()] = $device->getId();
        }
        return new JsonResponse($result);
    }

    /**
     * @Route("/admin/add_connexion", name="add_connexion")
     */
    public function Add_Connexion(Request $request)
    {
        $user = $this->get('security.token_storage')->getToken()->getUser();
        $connexion = new Connexion();

        //on passe l'entité manager au formulaire pour qu'on puisse valider les donner remplis automatiquement
        $form = $this->createForm(new ConnexionType($this->getDoctrine()->getManager()), $connexion);
        $form->handleRequest($request);
        if ($form->isValid()) {
            /* Do your stuff here */
            die('form is valide');
            $this->getDoctrine()->getManager()->persist($connexion);
            $this->getDoctrine()->getManager()->flush();
        }
        return $this->render('BackendBundle::add_connexion.html.twig', array
                        ('form' => $form->createView(),
                           'user' => $user
        ));
    }



}
