<?php
namespace UserBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use UserBundle\Entity\User;
use UserBundle\Form\Type\AddUserFormType;
use UserBundle\Form\Type\PasswordFormType;
use UserBundle\Form\Type\GroupeFormType;


class DefaultController extends Controller
{
	/**
     * @Route("/admin/add_user", name="add_user")
     */
	public function add_user(Request $request)
	{
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$userManager = $this->container->get('fos_user.user_manager');
	
		$new_user = new User();
		$form = $this->get('form.factory')->create(AddUserFormType::class, $new_user,array('method' => 'PUT'));

		if ($form->handleRequest($request)->isValid()) {
			
			$test = $userManager->findUserByEmail($form->get('email')->getData());	
			if (count($test)==0) {
			$last_name = $this->stripAccents($new_user->getLastName());
            $first_name = $this->stripAccents($new_user->getFirstName());
            

            // Première lettre du prénom
            $username = substr($first_name, 0, 1);
            // 6 première lettres du nom
            $username .= substr($last_name, 0, 6);
			$username = str_replace(' ','',$username);
            // Calcul d'une suite de chiffres aléatoires
            $identifiant_unique = $this->genchaine((10-strlen($username)));

            $username .= $identifiant_unique;
			
            $new_user->setUsername(strtolower($username));
			
			$encoder = $this->container->get('security.password_encoder');
			$clear_password=$this->genpassword(10);
			$new_user->setPassword($encoder->encodePassword($new_user,$clear_password));
			$new_user->SetEnabled(1);
			$em = $this->getDoctrine()->getManager();
			$em->persist($new_user);
			$em->flush();

			$request->getSession()->getFlashBag()->add('notice', 'Utilisateur créé.');
			
			$subject = "Compte créé - ".$new_user->getfirstname()." ".strtoupper($this->stripAccents($new_user->getLastName()));
			
			$mail_from=$this->getParameter('mail_from');
			$message = \Swift_Message::newInstance()
				->setSubject($subject)
				->setFrom(array($mail_from => 'Administrateur'))
				->setTo($new_user->getEmail())
				->setBcc($mail_from)
				->setBody($this->container->get('templating')->render('UserBundle:Gestion:add_user.email.twig',array('user' => $new_user,
					'password' => $clear_password,				
					),'text/plain'));
			$this->container->get('mailer')->send($message);
				
			return $this->render(
				'UserBundle:Gestion:add_user.html.twig',array(
				'new_user' => $new_user,
				'user' => $user,
				'form' => $form->createView(),
			));
			
			}
			else
			{
			$this->container->get('session')->getFlashBag()->add('danger',
            'Ce mail est déjà utilisé');			
				
			}

			return $this->redirect($this->generateUrl('add_user', array(
				'id' => $new_user->getId(),
				)		
			));
    }

    	return $this->render(
        'UserBundle:Gestion:add_user.html.twig',array(
		'new_user' => $new_user,
		'user' => $user,
		'form' => $form->createView(),
		)
	);
	}
	
	
	/**
     * @Route("/admin/list_user", name="list_user")
     */
	public function list_user(Request $request)
	{
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$userManager = $this->container->get('fos_user.user_manager');

		$repository = $this->getDoctrine()->getRepository('UserBundle:User');
	
		$list_user = $repository->findAll();
		
		
		return $this->render(
        'UserBundle:Gestion:list_user.html.twig',array(
		'user' => $user,
		'list_user' => $list_user
		));
		
		
	}
	
	/**
     * @Route("/admin/active_user", name="active_user")
     */
	public function active_user(Request $request)
	{
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$userManager = $this->container->get('fos_user.user_manager');

        if($request->isXmlHttpRequest()) {
		
		$id='';
		$id=$request->get('data');
		if ($id != '' AND $id != '1') {

			$repository = $this->getDoctrine()->getRepository('UserBundle:User');
			$select_user = new User();
			$user_id=preg_split("/_/",$id)[1];
			$select_user = $repository->find($user_id);
			if($select_user->isEnabled()) {
				$select_user->SetEnabled(0);
				$return="Activer";
			}
			else 
			{
				$select_user->SetEnabled(1);
				$return="Désactiver";
			}
			
			
			$em = $this->getDoctrine()->getManager();
			$em->persist($select_user);
			$em->flush();
			$result=$user_id.":".$return;
			return new Response($result);
			}
		return new Response(0);
		}
		else return new Response(0);
		/*return $this->render(
        'UserBundle:Gestion:list_user.html.twig',array(
		'user' => $user,
		'list_user' => $list_user
		));*/
	}
	
	/**
     * @Route("/admin/change_role", name="change_role")
     */
	public function change_role(Request $request)
	{
		$authenticationUtils = $this->get('security.authentication_utils');
		$user = $this->get('security.token_storage')->getToken()->getUser();
		$userManager = $this->container->get('fos_user.user_manager');
		
		$repository = $this->getDoctrine()->getRepository('UserBundle:User');
				
		$form = $this->get('form.factory')->create(new GroupeFormType(), array('method' => 'POST'));
		
		$check="";
		
		if ($form->handleRequest($request)->isValid()) {
			$test = $form->get('Groupe')->getData();
		}
	
		return $this->render(
        'UserBundle:Gestion:change_role.html.twig',array(
		'user' => $user,
		'form' => $form->createView(),
		));
		
	}
	
	function stripAccents($str, $encoding='utf-8')
	{
	    // transformer les caractères accentués en entités HTML
	    $str = htmlentities($str, ENT_NOQUOTES, $encoding);
	 
	    // remplacer les entités HTML pour avoir juste le premier caractères non accentués
	    // Exemple : "&ecute;" => "e", "&Ecute;" => "E", "Ã " => "a" ...
	    $str = preg_replace('#&([A-za-z])(?:acute|grave|cedil|circ|orn|ring|slash|th|tilde|uml);#', '\1', $str);
	 
	    // Remplacer les ligatures tel que : Œ, Æ ...
	    // Exemple "Å“" => "oe"
	    $str = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str);
	    // Supprimer tout le reste
	    $str = preg_replace('#&[^;]+;#', '', $str);
	 
	    return $str;
	}

    function genchaine( $chrs = "") {
        if( $chrs == "" ) $chrs = 8;
        $chaine = ""; 
        $list = "123456789";
        mt_srand((double)microtime()*1000000);
        $newstring="";
        while( strlen( $newstring )< $chrs ) {
            $newstring .= $list[mt_rand(0, strlen($list)-1)];
        }
        return $newstring;
    }
	
	function genpassword( $chrs = "") {
        if( $chrs == "" ) $chrs = 8;
        $chaine = ""; 
        $list = "123456789ABCDEFGHIJKLMNPQRSTUVWXYZazertyuipqsdfghjklmwxcvbn$@-_&;:,?!";
        mt_srand((double)microtime()*1000000);
        $newstring="";
        while( strlen( $newstring )< $chrs ) {
            $newstring .= $list[mt_rand(0, strlen($list)-1)];
        }
        return $newstring;
    }
	
}

?>