<?php

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use UserBundle\Entity\Groupe;
use UserBundle\Entity\User;

class GroupeData extends AbstractFixture implements ContainerAwareInterface
{
  // Dans l'argument de la méthode load, l'objet $manager est l'EntityManager
  public function load(ObjectManager $manager)
  {
    
	$list=array(
		array("Super Admin","ROLE_SUPERADMIN"),
		array("Admin","ROLE_ADMIN"),
		array("Enseignant","ROLE_ENSEIGNANT"),
		array("Etudiant","ROLE_ETUDIANT")
		);
	
	for ($i=1; $i< sizeof($list); $i++) {
		$role[$i]=new Groupe();
		$role[$i]->setNom($list[$i][0]);
		$role[$i]->setRole($list[$i][1]);
		$manager->persist($role[$i]); 
	}	
	
    $manager->flush();
	
  }
  
	public function setContainer(ContainerInterface $container = null){
		$this->container = $container;
	}

}

