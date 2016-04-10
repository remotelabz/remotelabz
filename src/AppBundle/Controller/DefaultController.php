<?php
namespace AppBundle\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

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
	
	/**
     * @Route("/admin", name="admin")
     */
    public function adminAction()
    {
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();


// Si l'utilisateur courant est anonyme, $user vaut « anon. »

// Sinon, c'est une instance de notre entité User, on peut l'utiliser normalement
	

    return $this->render('AppBundle::accueil_auth.html.twig', array(
					'user' => $user,
				));
	
	
    }
}

?>