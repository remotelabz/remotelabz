<?php

namespace App\Security;

use DateTime;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use Psr\Log\LoggerInterface;

class ShibbolethAuthenticator extends AbstractGuardAuthenticator
{
    private $logger;

    /**
     * @var
     */
    private $idpUrl;

    /**
     * @var string|null
     */
    private $remoteUserVar;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    private $entityManager;

    private $passwordEncoder;

    private $JWTManager;
    private $params;
    private $tokenStorage;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        $idpUrl = null,
        $remoteUserVar = null,
        TokenStorageInterface $tokenStorage,
        EntityManagerInterface $entityManager = null,
        UserPasswordEncoderInterface $passwordEncoder = null,
        JWTTokenManagerInterface $JWTManager,
        LoggerInterface $logger,
        ParameterBagInterface $params
        
    ) {
        $this->idpUrl = $idpUrl ?: 'unknown';
        $this->remoteUserVar = $remoteUserVar ?: 'HTTP_EPPN';
        $this->urlGenerator = $urlGenerator;
        $this->entityManager = $entityManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->JWTManager = $JWTManager;
        $this->logger = $logger;
        $this->params = $params;
        $this->tokenStorage = $tokenStorage;
    }

    protected function getRedirectUrl()
    {
        return $this->urlGenerator->generate('login');
    }

    /**
     * @param Request $request
     *
     * @return mixed|null
     */
    public function getCredentials(Request $request)
    {
        $credentials = [
            'eppn' => $request->server->get($this->remoteUserVar),
            'email' => $request->server->get('mail'),
            'firstName' => $request->server->get('givenName'),
            'lastName' => $request->server->get('sn'),
            'affiliation' => $request->server->get('o'),
            'statut' => $request->server->get('eduPersonPrimaryAffiliation')
        ];
        $this->logger->debug("User authentification with shibboleth :".$request->server->get('mail'));
        return $credentials;
    }

    /**
     *
     * @param mixed $credentials
     * @param UserProviderInterface $userProvider
     *
     * @throws AuthenticationException
     *
     * @return UserInterface|null
     */
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        /** @var User $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $credentials['email']]);

        if (!$user) {
            $user = new User();
            $role = array("ROLE_USER");
            $user
                ->setEmail($credentials['email'])
                ->setPassword($this->passwordEncoder->encodePassword(
                    $user,
                    random_bytes(32)
                ))
                ->setFirstName(ucfirst(strtolower($credentials['firstName'])))
                ->setLastName($credentials['lastName'])
                ->setIsShibbolethUser(true)
                ->setRoles($role);

            # TODO: Add user's firstname and lastname fetching

            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } else if (!$user->isShibbolethUser()) {
            // backward compatibility to already created shib users
            $user->setIsShibbolethUser(true);
        }

        if (!$user->isEnabled()) {
            throw new DisabledException();
        }

        return $user;
    }

    /**
     * @param mixed $credentials
     * @param UserInterface $user
     *
     * @return bool
     *
     * @throws AuthenticationException
     */
    public function checkCredentials($credentials, UserInterface $user)
    {
        return true;
    }

    /**
     * @param Request $request
     * @param AuthenticationException $exception
     *
     * @return Response|null
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $redirectTo = $this->getRedirectUrl();
        if (in_array('application/json', $request->getAcceptableContentTypes())) {
            return new JsonResponse(array(
                'status' => 'error',
                'message' => 'Authentication failed.',
                'redirect' => $redirectTo,
            ), Response::HTTP_FORBIDDEN);
        } else {
            if ($exception instanceof DisabledException) {
                /** @var FlashBagInterface $flashbag */
                $flashbag = $request->getSession()->getBag('flashes');
                $flashbag->add('danger', 'This university account has been locked by a RemoteLabz administrator. Please try again later.');
            }

            return null;
        }
    }

    /**
     * @param Request $request
     * @param TokenInterface $token
     * @param string $providerKey The provider (i.e. firewall) key
     *
     * @return Response|null
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        if (!$request->cookies->has('bearer')) {
            $response = new RedirectResponse($this->urlGenerator->generate('index'));
            /** @var User $user */
            $user = $token->getUser();

            $jwtToken = $this->JWTManager->create($user);
            $now = new DateTime();
            $jwtTokenCookie = Cookie::create('bearer', $jwtToken, $now->getTimestamp() + 24 * 3600);

            $response->headers->setCookie($jwtTokenCookie);
            $user->setLastActivity(new DateTime());
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $response;
        }

        return new RedirectResponse($this->urlGenerator->generate('index'));
    }

    /**
     * @param Request $request The request that resulted in an AuthenticationException
     * @param AuthenticationException $authException The exception that started the authentication process
     *
     * @return Response
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        if (in_array('application/json', $request->getAcceptableContentTypes())) {
            return new JsonResponse(array(
                'status' => 'error',
                'message' => 'You are not authenticated.',
                'redirect' => $this->urlGenerator->generate('shib_login'),
            ), Response::HTTP_FORBIDDEN);
        } else {
            return new RedirectResponse($this->urlGenerator->generate('shib_login'));
        }
    }

    /**
     * @return bool
     */
    public function supportsRememberMe()
    {
        return false;
    }

    public function supports(Request $request)
    {
        if (!$this->params->get("enable_shibboleth")) {
            return false;
        }

        if ($request->server->has($this->remoteUserVar) && $request->getPathInfo() == $this->getRedirectUrl()) {
            return true;
        }

        return false;
    }

    /**
     * @param Request $request
     *
     * @return Response never null
     */
    public function onLogoutSuccess(Request $request)
    {
        $redirectTo = $this->urlGenerator->generate('shib_logout', array(
            'return'  => $this->idpUrl . '/profile/Logout'
        ));
        return new RedirectResponse($redirectTo);
    }
}
