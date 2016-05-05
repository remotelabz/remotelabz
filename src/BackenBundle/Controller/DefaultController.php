<?php

namespace BackenBundle\Controller;

use AppBundle\Entity\Device;
use AppBundle\Entity\Hyperviseur;
use AppBundle\Entity\Systeme;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\DBAL\Schema\Index;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use AppBundle\Entity\Network_Interface;
use AppBundle\Entity\ConfigReseau;
use Symfony\Component\HttpFoundation\Response;


class DefaultController extends Controller
{
    public function addAction()
    {
        $net1 = new Network_Interface();
        $net1->setNomInterface('eth1');

        $net2 = new Network_Interface();
        $net2->setNomInterface('eth2');

        $net3 = new Network_Interface();
        $net3->setNomInterface('eth3');

        $heper = new Hyperviseur();
        $heper->setNom('lxc');
        $heper->setCommande('create ');


        $syst = new Systeme();
        $syst->setNom('ubuntu');
        $syst->setHyperviseur($heper);

        $syst2 = new Systeme();
        $syst2->setNom('ubuntu');
        $syst2->setHyperviseur($heper);

        $dev1= new Device();
        $dev1->addNetworkInterface($net3);
        $dev1->addNetworkInterface($net2);
        $dev1->addNetworkInterface($net1);
        $dev1->setNom('switch1');
        $dev1->setMarque('catalyst5600');
        $dev1->setModele('6200');
        $dev1->setPropriete('switch');
        $dev1->setType('virtuel');
        $dev1->setSysteme($syst2);

        $dev2= new Device();
        $dev1->addNetworkInterface($net3);
        $dev2->addNetworkInterface($net2);
        $dev2->addNetworkInterface($net1);
        $dev2->setNom('switch2');
        $dev2->setMarque('catalyst5600');
        $dev2->setModele('6200');
        $dev2->setPropriete('switch');
        $dev2->setType('virtuel');
        $dev2->setSysteme($syst);


        $em = $this->getDoctrine()->getManager();

        $em->persist($heper);
        $em->persist($syst);
        $em->persist($syst2);
        $em->persist($net1);
        $em->persist($net2);
        $em->persist($net3);
        $em->persist($dev1);
        $em->persist($dev2);
        $em->flush();

        return  new Response('ok');
    }
    public function viewAction()
    {


        return  new Response('ok');

    }


}
