<?php

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use UserBundle\Entity\User;


class UserData extends AbstractFixture implements ContainerAwareInterface
{
  // Dans l'argument de la méthode load, l'objet $manager est l'EntityManager
  public function load(ObjectManager $manager)
  {
    $adminuser=new User();
    $adminuser->setLastname("Nolot");
	$adminuser->setFirstname("Florent");
	$adminuser->setUsername("admin");
	$adminuser->setEnabled(true);
	
	$encoder = $this->container->get('security.password_encoder');
	//$password = $encoder->encodePassword($adminuser, "1Admin-In5crip2");
	$password = $encoder->encodePassword($adminuser, "admin");
	$adminuser->setPassword($password);
	$adminuser->setEmail("florent.nolot@univ-reims.fr");
	$adminuser->addRole('ROLE_SUPERADMIN');
	
	$adminuser->setGroupe($this->getReference('admin-group'));
		
	$manager->persist($adminuser); 
    $manager->flush();
	
	

  }

public function getOrder()
    {
        // the order in which fixtures will be loaded
        // the lower the number, the sooner that this fixture is loaded
        return 2;
    }
	
	public function setContainer(ContainerInterface $container = null){
		$this->container = $container;
	}

}

