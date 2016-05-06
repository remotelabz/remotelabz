<?php

namespace BackenBundle\Controller;

use AppBundle\Entity\Connexion;
use AppBundle\Entity\Device;
use AppBundle\Entity\Hyperviseur;
use AppBundle\Entity\Parameter;
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
        $net1->setNomInterface('eth5');

        $net2 = new Network_Interface();
        $net2->setNomInterface('eth8');

        $net3 = new Network_Interface();
        $net3->setNomInterface('eth10');

        $heper = new Hyperviseur();
        $heper->setNom('lxc');
        $heper->setCommande('create ');

        $parametre = new Parameter();
        $parametre->setSeizeDisque(15.0);
        $parametre->setSeizeMemoire(20.0);


        $syst = new Systeme();
        $syst->setNom('ubuntu');
        $syst->setHyperviseur($heper);
        $syst->setPathMaster('/et/home');
        $syst->setPathRelatif('/etc/var');
        $syst->setParametres($parametre);

        $syst2 = new Systeme();
        $syst2->setNom('debian');
        $syst2->setHyperviseur($heper);
        $syst2->setPathMaster('/et/home');
        $syst2->setPathRelatif('/etc/var');
        $syst2->setParametres($parametre);

        $dev1= new Device();;
        $dev1->addNetworkInterface($net1);
        $dev1->setNom('router1');
        $dev1->setMarque('catalyst5600');
        $dev1->setModele('6200');
        $dev1->setPropriete('router');
        $dev1->setType('virtuel');
        $dev1->setSysteme($syst2);

        $dev2= new Device();
        $dev1->addNetworkInterface($net2);


        $dev2->setNom('router2');
        $dev2->setMarque('catalyst5600');
        $dev2->setModele('6200');
        $dev2->setPropriete('router');
        $dev2->setType('virtuel');
        $dev2->setSysteme($syst);

        $connexion = new Connexion();
        $connexion->setDevice1($dev1);
        $nomdev1=$dev1->getNom();
        $connexion->setNomdevice1($nomdev1);;
        $connexion->setInterface1($net1);

        $connexion->setDevice2($dev2);
        $nomdev2 = $dev2->getNom();
        $connexion->setNomdevice2($nomdev2);

        $connexion->setInterface2($net2);



        $em = $this->getDoctrine()->getManager();
        $em->persist($syst);
        $em->persist($syst2);
        $em->persist($net1);
        $em->persist($net2);
        $em->persist($net3);
        $em->persist($dev1);
        $em->persist($dev2);
        $em->persist($connexion);
        $em->flush();

        return  new Response('ok');
    }
    public function viewAction()
    {


        return  new Response('nook');

    }


}
