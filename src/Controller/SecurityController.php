<?php

namespace App\Controller;

use DateTime;
use App\Form\NewPasswordType;
use App\Repository\UserRepository;
use App\Entity\PasswordResetRequest;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\PasswordResetRequestRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

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
     * @var UserPasswordEncoderInterface
     */
    public $passwordEncoder;

    public function __construct(UrlGeneratorInterface $urlGenerator, UserRepository $userRepository, PasswordResetRequestRepository $passwordResetRequestRepository, UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->urlGenerator = $urlGenerator;
        $this->userRepository = $userRepository;
        $this->passwordResetRequestRepository = $passwordResetRequestRepository;
        $this->passwordEncoder = $passwordEncoder;
    }

    /**
     * @Route("/login", name="login", methods={"GET", "POST"})
     */
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();    
        
        $stringfromfile = file('../.git/HEAD', FILE_USE_INCLUDE_PATH);

        $firstLine = $stringfromfile[0]; //get the string from the array

        $explodedstring = explode("/", $firstLine, 3); //seperate out by the "/" in the string

        $branchname = $explodedstring[2]; //get the one that is always the branch name


        
        return $this->render('security/login.html.twig', 
            [
                'last_username' => $lastUsername,
                'error' => $error,
                'version' => $branchname
                
            ]);
    }

    /**
     * @Route("/login/shibboleth", name="shibboleth_login", methods={"GET", "POST"})
     */
    public function shibboleth(UrlGeneratorInterface $urlGenerator): Response
    {
        return new RedirectResponse('/');
    }

    /**
     * @Route("/password/reset", name="reset_password", methods={"GET", "POST"})
     */
    public function resetPasswordAction(Request $request, \Swift_Mailer $mailer): Response
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

                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->persist($passwordResetRequest);
                $entityManager->flush();

                $message = (new \Swift_Message('Password reset'))
                    ->setFrom('remotelabz@remotelabz.univ-reims.fr')
                    ->setTo($user->getEmail())
                    ->setBody(
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

                $this->addFlash('info', 'A link has been sent. Please check your emails (please do not forget to check spams).');
            } else {
                $this->addFlash('danger', 'There is no account registered with this email.');
            }
        }

        return $this->render('security/request_reset.html.twig', [
            'resetPasswordForm' => $resetPasswordForm->createView()
        ]);
    }

    /**
     * @Route("/password/reset/new", name="new_password", methods={"GET", "POST"})
     */
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
                        $user->setPassword($this->passwordEncoder->encodePassword($user, $newPassword));

                        $entityManager = $this->getDoctrine()->getManager();
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
}
