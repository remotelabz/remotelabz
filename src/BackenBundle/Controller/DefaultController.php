<?php

namespace BackenBundle\Controller;

use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Schema\Index;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use AppBundle\Entity\Network_Interface;
use AppBundle\Entity\ConfigReseau;


class DefaultController extends Controller
{
    public function indexAction()
    {
        $network_interface = new Network_Interface();
        $config_reseau= new ConfigReseau();
        $id = $config_reseau->getId();

        $config_reseau->setIP('192.168.1.1');
        $config_reseau->setIPDNS('8.8.8.8');
        $config_reseau->setMasque('255.255.255.0');

        $network_interface->setNom('eth1');
        $network_interface->setConfigReseau($config_reseau);

        $em = $this->getDoctrine()->getManager();
        $em->persist($config_reseau);
        $em->persist($network_interface);
        $em->flush();



        return $this->render('BackenBundle:Default:index.html.twig',Array('id'=> $id
                                                                                ));
    }
}
