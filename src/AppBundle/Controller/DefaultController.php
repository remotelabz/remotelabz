<?php
namespace AppBundle\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Router;
use UserBundle\Entity\User;
use UserBundle\Entity\Groupe;


class DefaultController extends Controller
{
    /**
     * @Route("/", name="accueil")
     */
    public function indexAction(Request $request)
    {
	
    return $this->render(
        'default/index.html.twig'
	);
	
    }
//Shibboleth non terminé
	/**
     * @Route("/login_shib", name="login_shib")
     */
    public function login_shibAction(Request $request)
    {
	return $this->redirect('https://remotelabz.univ-reims.fr/Shibboleth.sso/Login?target='.$this->generateUrl('auth'));
	
    }
	
	/**
     * @Route("/logout_shib", name="logout_shib")
     */
    public function logout_shibAction(Request $request)
    {
	return $this->redirect('https://remotelabz.univ-reims.fr/Shibboleth.sso/Logout');
	
    }
	
	
	/**
     * @Route("/auth", name="auth")
     */
    public function authAction()
    {
		$authenticationUtils = $this->get('security.authentication_utils');
		$em = $this->getDoctrine()->getManager();
		if (isset($_SERVER['REMOTE_USER']) && $_SERVER['REMOTE_USER'] != '') { // authentification externe
			$users=$em->getRepository('UserBundle:User')->findByEmail($_SERVER['REMOTE_USER']);
			
			if (count($users)==0) {
			
			//L'utilisateur actuellement connecté n'existe pas encore dans la base
			$user=new User();
			$user->setLastname("Nom_Ext");
			$user->setFirstname("Prenom_Ext");
			$user->setEmail($_SERVER['REMOTE_USER']);
			$user->setUsername($_SERVER['REMOTE_USER']);
			$encoder = $this->container->get('security.password_encoder');
			$password = $encoder->encodePassword($user, $_SERVER['REMOTE_USER']);
			$user->setPassword($password);
			
            $em = $this->getDoctrine()->getManager();
			$groupe = $em->getRepository('UserBundle:Groupe')->findByNom('Etudiant');
			$user->setGroupe($groupe[0]);
			$em->persist($user);
            $em->flush();
			}
			else 
				$user=$users[0];
			
			$group=$user->getRole();
			
		} else { //Authentification local et non externe
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();
		}

	// Si l'utilisateur courant est anonyme, $user vaut « anon. »

	// Sinon, c'est une instance de notre entité User, on peut l'utiliser normalement
	
		if ($group->getRole() != 'ROLE_ADMIN')
			return $this->render('AppBundle::accueil_auth.html.twig', array(
					'user' => $user,
					'group' => $group
			));
		else
			
			return $this->redirect($this->generateUrl('admin'));

	
    }
	
	/**
     * @Route("/admin", name="admin")
     */
    public function adminAction()
    {
	$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$group=$user->getRole();

// Si l'utilisateur courant est anonyme, $user vaut « anon. »

// Sinon, c'est une instance de notre entité User, on peut l'utiliser normalement
	

		return $this->render('AppBundle::accueil_auth.html.twig', array(
					'user' => $user,
					'group' => $group
				));
	
	
    }

}

?>