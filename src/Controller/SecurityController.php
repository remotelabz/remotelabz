<?php

namespace App\Controller;

use DateTime;
use App\Form\NewPasswordType;

use App\Repository\UserRepository;
use App\Entity\PasswordResetRequest;
use Doctrine\Common\Collections\Criteria;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\KernelInterface;
use App\Repository\PasswordResetRequestRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;
use Doctrine\ORM\EntityManagerInterface;

class SecurityController extends AbstractController
{
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var PasswordResetRequestRepository
     */
    private $passwordResetRequestRepository;

    /**
     * @var UserPasswordHasherInterface
     */
    public $passwordHasher;

    protected $maintenance;
    protected $general_message;

    public function __construct(UrlGeneratorInterface $urlGenerator,
        UserRepository $userRepository,
        PasswordResetRequestRepository $passwordResetRequestRepository,
        UserPasswordHasherInterface $passwordHasher,
        bool $maintenance,
        string $general_message = null,
        string $contact_mail,
        EntityManagerInterface $entityManager
    )
    {
        $this->urlGenerator = $urlGenerator;
        $this->userRepository = $userRepository;
        $this->passwordResetRequestRepository = $passwordResetRequestRepository;
        $this->passwordHasher = $passwordHasher;
        $this->maintenance = $maintenance;
        $this->general_message=$general_message;
        $this->contact_mail = $contact_mail;
        $this->entityManager = $entityManager;
    }

    #[Route(path: '/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils, KernelInterface $kernel): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();
        if ($lastUsername === null) $lastUsername = "";
        
        $version = file_get_contents($kernel->getProjectDir() . '/version');
        
        
        
        return $this->render('security/login.html.twig', 
        [
            'last_username' => $lastUsername,
            'error' => $error,
            'version' => $version,
            'maintenance' => $this->maintenance,
            'general_message' => $this->general_message
        ]);
    }

    #[Route(path: '/login/shibboleth', name: 'shibboleth_login', methods: ['GET', 'POST'])]
    public function shibboleth(UrlGeneratorInterface $urlGenerator): Response
    {
        return new RedirectResponse('/');
    }

    #[Route(path: '/api/auth', name: 'api_login_check', methods: ['POST'])]
    public function jsonLogin(Request $request)
    {
        // logic is managed by JWT
    }

    
	#[Get('/api/logout', name: 'api_logout')]
    #[Route(path: '/logout', name: 'logout')]
    public function logout()
    {
        $response = new Response();
        $response->headers->clearCookie('bearer', '/', null);
        $response->setContent(json_encode([
            'code'=> 200,
            'status'=>'success',
            'message' => 'logout']));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    #[Route(path: '/password/reset', name: 'reset_password', methods: ['GET', 'POST'])]
    public function resetPasswordAction(Request $request, MailerInterface $mailer): Response
    {
        $resetPasswordForm = $this->createFormBuilder([])
            ->add('email', EmailType::class)
            ->add('submit', SubmitType::class)
            ->getForm()
        ;

        $resetPasswordForm->handleRequest($request);

        if ($resetPasswordForm->isSubmitted() && $resetPasswordForm->isValid()) {
            $formData = $resetPasswordForm->getData();

            $user = $this->userRepository->findOneBy([
                'email' => $formData['email']
            ]);

            if (null ==! $user) {
                $token = sha1($user->getEmail()) . sha1(uniqid("", true));
                $passwordResetRequest = new PasswordResetRequest();
                $passwordResetRequest
                    ->setUser($user)
                    ->setToken($token)
                    ->setCreatedAt(new DateTime())
                ;

                $entityManager = $this->entityManager;
                $entityManager->persist($passwordResetRequest);
                $entityManager->flush();

                $message = (new Email())
                    ->from($this->contact_mail)
                    ->to($user->getEmail())
                    ->subject('Password reset')
                    ->html(
                        $this->renderView(
                            'emails/reset.html.twig',
                            [
                                'name' => $user->getName(),
                                'link' => $this->generateUrl('new_password', [
                                    'token' => $token
                                ], UrlGeneratorInterface::ABSOLUTE_URL)
                            ]
                        ),
                        'text/html'
                    )
                ;

                $mailer->send($message);

                //$this->addFlash('info', 'A link has been sent. Please check your emails (please do not forget to check spams).');
                $this->addFlash('info', 'If your account exists, you will receive a link. Please check your emails (please do not forget to check spams).');
            } else {
                //$this->addFlash('danger', 'There is no account registered with this email.');
                $this->addFlash('info', 'If your account exists, you will receive a link. Please check your emails (please do not forget to check spams).');
            }
        }

        return $this->render('security/request_reset.html.twig', [
            'resetPasswordForm' => $resetPasswordForm->createView()
        ]);
    }

    #[Route(path: '/password/reset/new', name: 'new_password', methods: ['GET', 'POST'])]
    public function newPasswordAction(Request $request)
    {
        $newPasswordForm = $this->createForm(NewPasswordType::class);
        $invalidToken = false;
        $expiredToken = false;

        $token = $request->query->get('token');
        $passwordResetRequest = $this->passwordResetRequestRepository->findOneBy(['token' => $token]);
        $invalidToken = (null === $passwordResetRequest);

        if (!$invalidToken) {
            $expiredToken = ($passwordResetRequest->getCreatedAt()->diff(new DateTime())->d >= 1);

            if (!$expiredToken) {
                $newPasswordForm->handleRequest($request);

                if ($newPasswordForm->isSubmitted() && $newPasswordForm->isValid()) {
                    $user = $passwordResetRequest->getUser();
                    $newPassword = $newPasswordForm->get('newPassword')->getData();
                    $confirmPassword = $newPasswordForm->get('confirmPassword')->getData();

                    if ($newPassword == $confirmPassword) {
                        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));

                        $entityManager = $this->entityManager;
                        $entityManager->persist($user);

                        foreach ($this->passwordResetRequestRepository->findBy(['user' => $user]) as $request) {
                            $entityManager->remove($request);
                        }

                        $entityManager->flush();

                        $this->addFlash('success', 'Your password has been changed.');

                        return $this->redirectToRoute('login');
                    } else {
                        $this->addFlash('danger', "Passwords doesn't match.");
                    }
                }
            }
        }

        return $this->render('security/password_reset.html.twig', [
            'invalidToken' => $invalidToken,
            'expiredToken' => $expiredToken,
            'newPasswordForm' => $newPasswordForm->createView()
        ]);
    }

    #[Route(path: '/login/code', name: 'code_login', methods: ['GET', 'POST'])]
    public function Codelogin(AuthenticationUtils $authenticationUtils, KernelInterface $kernel): Response
    {
        return $this->render('security/code_login.html.twig');
    }
}
