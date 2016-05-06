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
  // Dans l'argument de la m�thode load, l'objet $manager est l'EntityManager
  public function load(ObjectManager $manager)
  {
    $adminuser=new User();
    $adminuser->setLastname("Nolot");
	$adminuser->setFirstname("Florent");
	$adminuser->setUsername("admin");
	$adminuser->setEnabled(true);
	
	$encoder = $this->container->get('security.password_encoder');
	$password = $encoder->encodePassword($adminuser, "1Admin-In5crip2");
	$adminuser->setPassword($password);
	$adminuser->setEmail("florent.nolot@univ-reims.fr");
	$adminuser->addRole('ROLE_SUPERADMIN');
	
	$manager->persist($adminuser); 
    $manager->flush();
	
//	$this->addReference('admin-user', $adminuser);
  }
  
	public function setContainer(ContainerInterface $container = null){
		$this->container = $container;
	}

}

