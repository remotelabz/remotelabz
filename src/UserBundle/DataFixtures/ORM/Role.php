<?php

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use UserBundle\Entity\Role;


class RoleData extends AbstractFixture implements ContainerAwareInterface
{
  // Dans l'argument de la méthode load, l'objet $manager est l'EntityManager
  public function load(ObjectManager $manager)
  {
    
	$list=array(
		array("Super Admin","ROLE_SUPERADMIN"),
		array("Admin","ROLE_ADMIN"),//Administrator
		array("Enseignant","ROLE_ENSEIGNANT"),//Teacher
		array("Etudiant","ROLE_ETUDIANT")//Student
		);
	
	for ($i=1; $i< sizeof($list); $i++) {
		$role[$i]=new Role();
		$role[$i]->setNom($list[$i][0]);
		$role[$i]->setRole($list[$i][1]);
		if ($i==1) $this->addReference('admin-group', $role[$i]);
		$manager->persist($role[$i]); 
	}	
	
    $manager->flush();
	
  }
  
  public function getOrder()
    {
        // the order in which fixtures will be loaded
        // the lower the number, the sooner that this fixture is loaded
        return 1;
    }
	public function setContainer(ContainerInterface $container = null){
		$this->container = $container;
	}

}

