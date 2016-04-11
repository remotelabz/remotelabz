<?php
namespace UserBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;


use UserBundle\Entity\User;
use UserBundle\Form\Type\AddUserFormType;


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
     * @Route("/admin/change_passwd", name="change_passwd")
     */
	function changepasswd() {
		$user = $this->getUser();
        if (!is_object($user) || !$user instanceof UserInterface) {
            throw new AccessDeniedException('This user does not have access to this section.');
        }

        /** @var $dispatcher \Symfony\Component\EventDispatcher\EventDispatcherInterface */
        $dispatcher = $this->get('event_dispatcher');
		
        $event = new GetResponseUserEvent($user, $request);
        $dispatcher->dispatch(FOSUserEvents::PROFILE_EDIT_INITIALIZE, $event);
		
        if (null !== $event->getResponse()) {
            return $event->getResponse();
        }
		
        /** @var $formFactory \FOS\UserBundle\Form\Factory\FactoryInterface */
        $formFactory = $this->get('fos_user.profile.form.factory');
		
        $form = $formFactory->createForm();
        $form->setData($user);
		
        $form->handleRequest($request);
		
        if ($form->isValid()) {
            /** @var $userManager \FOS\UserBundle\Model\UserManagerInterface */
            $userManager = $this->get('fos_user.user_manager');
			
            $event = new FormEvent($form, $request);
            $dispatcher->dispatch(FOSUserEvents::PROFILE_EDIT_SUCCESS, $event);
			
            $userManager->updateUser($user);
			
            if (null === $response = $event->getResponse()) {
                $url = $this->generateUrl('fos_user_profile_show');
                $response = new RedirectResponse($url);
            }
			
            $dispatcher->dispatch(FOSUserEvents::PROFILE_EDIT_COMPLETED, new FilterUserResponseEvent($user, $request, $response));
			
            return $response;
        }
		
        return $this->render('UserBundle:Profile:edit.html.twig', array(
		'form' => $form->createView(),
		'user' => $user
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