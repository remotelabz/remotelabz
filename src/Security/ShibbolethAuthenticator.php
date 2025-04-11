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
//use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;


use Psr\Log\LoggerInterface;

class ShibbolethAuthenticator extends AbstractAuthenticator
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

    private $passwordHasher;

    private $JWTManager;
    private $params;
    private $tokenStorage;
    /**
     * @var string (format "domain1, domain2, ... ")
     */
    private $authorized_affiliation;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        $idpUrl,
        $remoteUserVar,
        TokenStorageInterface $tokenStorage,
        EntityManagerInterface $entityManager = null,
        UserPasswordHasherInterface $passwordHasher = null,
        JWTTokenManagerInterface $JWTManager,
        LoggerInterface $logger,
        ParameterBagInterface $params,
        $authorized_affiliation
    ) {
        $this->idpUrl = $idpUrl ?: 'unknown';
        $this->remoteUserVar = $remoteUserVar ?: 'HTTP_EPPN';
        $this->urlGenerator = $urlGenerator;
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->JWTManager = $JWTManager;
        $this->logger = $logger;
        $this->params = $params;
        $this->tokenStorage = $tokenStorage;
        $this->authorized_affiliation = $authorized_affiliation;
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
    /*public function getCredentials(Request $request)
    {
        $credentials = [
            'eppn' => $request->server->get($this->remoteUserVar),
            'email' => $request->server->get('mail'),
            'firstName' => $request->server->get('givenName'),
            'lastName' => $request->server->get('sn'),
            'affiliation' => $request->server->get('o'),
            'statut' => $request->server->get('eduPersonPrimaryAffiliation')
        ];
        $this->logger->info("User information from getCredentials of shibboleth :", $credentials);
        return $credentials;
    }*/

    /**
     *
     * @param mixed $credentials
     * @param UserProviderInterface $userProvider
     *
     * @throws AuthenticationException
     *
     * @return UserInterface|null
     */
   /* public function getUser($credentials, UserProviderInterface $userProvider)
    {
        /** @var User $user */
        /*$user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $credentials['email']]);

        if (! (is_null($credentials['email']) or $credentials['email']==="")) {
            $this->logger->debug("Shibboleth email not null: ".$credentials['email']);
            if (!$user) {
                $this->logger->debug("Shibboleth user doesn't exist in local user base: ".$credentials['email']);
                $user = new User();
                $email=$credentials['email'];
                $firstName=$credentials['firstName'];
                $lastName=$credentials['lastName'];
                $role = array("ROLE_USER");
                $user
                    ->setEmail($email)
                    ->setPassword($this->passwordHasher->encodePassword(
                        $user,
                        random_bytes(32)
                    ))
                    ->setFirstName(ucfirst(strtolower($firstName)))
                    ->setLastName($lastName)
                    ->setIsShibbolethUser(true)
                    ->setRoles($role);
            
                $this->entityManager->persist($user);
                $this->entityManager->flush();
            
            }
            if (!$user->isShibbolethUser()) {
                // backward compatibility to already created shib users
                $user->setIsShibbolethUser(true);
            }

            if (!$user->isEnabled()) {
                throw new DisabledException();
            }
        }

        $this->logger->info("All shibboleth credentials from getUser: ",$credentials);
        
        return $user;
    }*/

    public function authenticate(Request $request): Passport
    {
        $credentials = [
            'eppn' => $request->server->get($this->remoteUserVar),
            'email' => $request->server->get('mail'),
            'firstName' => $request->server->get('givenName'),
            'lastName' => $request->server->get('sn'),
            'affiliation' => $request->server->get('o'),
            'statut' => $request->server->get('eduPersonPrimaryAffiliation')
        ];

        $this->logger->info("User information from getCredentials of shibboleth :", $credentials);

	    $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $credentials['email']]);
        if (! (is_null($credentials['email']) or $credentials['email']==="")) {
            $this->logger->debug("Shibboleth email not null: ".$credentials['email']);
            if (!$user) {
                $this->logger->debug("Shibboleth user doesn't exist in local user base: ".$credentials['email']);
                $user = new User();
                $email=$credentials['email'];
                $firstName=$credentials['firstName'];
                $lastName=$credentials['lastName'];
                $role = array("ROLE_USER");
                $user
                    ->setEmail($email)
                    ->setPassword($this->passwordHasher->hashPassword(
                        $user,
                        random_bytes(32)
                    ))
                    ->setFirstName(ucfirst(strtolower($firstName)))
                    ->setLastName($lastName)
                    ->setIsShibbolethUser(true)
                    ->setRoles($role);
            
                $this->entityManager->persist($user);
                $this->entityManager->flush();
            
            }

            if (!$user->isShibbolethUser()) {
                // backward compatibility to already created shib users
                $user->setIsShibbolethUser(true);
            }

            if (!$user->isEnabled()) {
                throw new DisabledException();
            }
            return new Passport(
                new UserBadge($credentials['email']),
                new CustomCredentials(
                    function ($credentials, UserInterface $user)
                    {
                        $this->logger->debug("Check credentials",$credentials);
                        $authorized=explode(",",$this->authorized_affiliation);
                        //Looking for affiliation in the string and before, delete all spaces and tab
    
                        $affiliation=explode("@",$credentials['email']);//Looking for the domain of the mail
                        $this->logger->debug("Your affiliation: ".$affiliation[1]);
                        if (in_array($affiliation[1],preg_replace('/\s+/', '', $authorized))) {
                            $this->logger->info("This user is from an authorized shibboleth affiliation : ",$credentials);
                            return true;
                        }
                        else {
                            $this->logger->warning("This user is not in an authorized shibboleth affiliation : ",$credentials);
                            
                return false;
                        }
                    },
                    $credentials
                )
            );
        }
        return new SelfValidatingPassport(
            new UserBadge("")
        );
        
        
    }

    /**
     * @param mixed $credentials
     * @param UserInterface $user
     *
     * @return bool
     *
     *
     */
    /*public function checkCredentials($credentials, UserInterface $user)
    {
        $this->logger->debug("Check credentials",$credentials);
        $authorized=explode(",",$this->authorized_affiliation);
        //Looking for affiliation in the string and before, delete all spaces and tab

        $affiliation=explode("@",$credentials['email']);//Looking for the domain of the mail
        $this->logger->debug("Your affiliation: ".$affiliation[1]);
        if (in_array($affiliation[1],preg_replace('/\s+/', '', $authorized))) {
            $this->logger->info("This user is from an authorized shibboleth affiliation : ",$credentials);
            return true;
        }
        else {
            $this->logger->warning("This user is not in an authorized shibboleth affiliation : ",$credentials);
            throw new CustomUserMessageAuthenticationException();
            return false;
        }
    }*/

    /**
     * @param Request $request
     * @param AuthenticationException $exception
     *
     * @return Response|null
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->logger->debug("authentification shibboleth failure");

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
            if ($exception instanceof CustomUserMessageAuthenticationException) {
                /** @var FlashBagInterface $flashbag */
                $flashbag = $request->getSession()->getBag('flashes');
                $flashbag->add('danger', 'Your affiliation is not allowed to use this application.');
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
    /*public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
            $this->logger->debug("authentification shibboleth success");
        if (!$request->cookies->has('bearer')) {
            $response = new RedirectResponse($this->urlGenerator->generate('index'));
            /** @var User $user */
            /*$user = $token->getUser();

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
    }*/

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $this->logger->debug("authentification shibboleth success");
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
    public function supportsRememberMe(): bool
    {
        return false;
    }

    public function supports(Request $request): bool
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
        $this->logger->debug("logout shibboleth success");

        $redirectTo = $this->urlGenerator->generate('shib_logout', array(
            'return'  => $this->idpUrl . '/profile/Logout'
        ));
        return new RedirectResponse($redirectTo);
    }
}
