<?php

namespace App\Security;

use DateTime;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
//use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    private $entityManager;
    private $router;
    private $csrfTokenManager;
    private $passwordHasher;
    private $JWTManager;
    public const LOGIN_ROUTE = 'login';

    /**
     * @var RefreshTokenManagerInterface
     */
    protected $refreshTokenManager;
    private $config;

    public function __construct(
        EntityManagerInterface $entityManager,
        RouterInterface $router,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $JWTManager,
        RefreshTokenManagerInterface $refreshTokenManager,
        ContainerBagInterface $config,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->passwordHasher = $passwordHasher;
        $this->JWTManager = $JWTManager;
        $this->refreshTokenManager = $refreshTokenManager;
        $this->config = $config;
        $this->urlGenerator = $urlGenerator;
    }

    public function supports(Request $request): bool
    {
        return 'login' === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email', '');

        $request->getSession()->set(Security::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($request->request->get('password', '')),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
            ]
        );
    }

    /*public function getCredentials(Request $request)
    {
        $credentials = [
            'email' => $request->request->get('email'),
            'password' => $request->request->get('password'),
            'csrf_token' => $request->request->get('_csrf_token'),
        ];
        $request->getSession()->set(
            Security::LAST_USERNAME,
            $credentials['email']
        );

        return $credentials;
    }*/

    /*public function getUser($credentials, UserProviderInterface $userProvider): ?UserInterface
    {
        $token = new CsrfToken('authenticate', $credentials['csrf_token']);
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new InvalidCsrfTokenException();
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $credentials['email']]);

        if (!$user) {
            // fail authentication with a custom error
            throw new CustomUserMessageAuthenticationException('Invalid credentials.');
        }

        if (!$user->isEnabled()) {
            throw new DisabledException();
        }

        return $user;
    }*/

    /*public function checkCredentials($credentials, UserInterface $user): bool
    {
        return $this->passwordHasher->isPasswordValid($user, $credentials['password']);
    }*/

    /*public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        $response = new RedirectResponse('/');
        /** @var User $user */
        /*$user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $request->get('email')]);

        $jwtToken = $this->JWTManager->create($user);
        $now = new DateTime();
        $jwtTokenCookie = Cookie::create('bearer', $jwtToken, $now->getTimestamp() + 24 * 3600);

        $response->headers->setCookie($jwtTokenCookie);
        // $response->headers->setCookie(Cookie::create('rt', $this->createRefreshToken($user)));
        $user->setLastActivity(new DateTime());
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        if ($request->query->has('ref_url')) {
            $response->setTargetUrl(urldecode($request->query->get('ref_url')));
        } else if ($targetPath = $this->getTargetPath($request->getSession(), $providerKey)) {
            $response->setTargetUrl($targetPath);
        } else {
            $response->setTargetUrl($this->router->generate('index'));
        }

        return $response;
    }*/

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        /*if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }*/

        $response = new RedirectResponse('/');
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $request->get('email')]);

        $jwtToken = $this->JWTManager->create($user);
        $now = new DateTime();
        $jwtTokenCookie = Cookie::create('bearer', $jwtToken, $now->getTimestamp() + 24 * 3600);

        $response->headers->setCookie($jwtTokenCookie);
        // $response->headers->setCookie(Cookie::create('rt', $this->createRefreshToken($user)));
        $user->setLastActivity(new DateTime());
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        if ($request->query->has('ref_url')) {
            $response->setTargetUrl(urldecode($request->query->get('ref_url')));
        } else if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            $response->setTargetUrl($targetPath);
        } else {
            $response->setTargetUrl($this->router->generate('index'));
        }
        return $response;
    }


    /**
     * @inheritDoc
     */
    /*public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        if ($request->hasSession()) {
            $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
        }

        $parameters = $request->query->has('ref_url') ? ['ref_url' => $request->query->get('ref_url')] : [];
        $url = $this->getLoginUrl($parameters);

        return new RedirectResponse($url);
    }*/

    /*protected function getLoginUrl($parameters = []): ?string
    {
        return $this->router->generate('login', $parameters);
    }*/

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }


    /*protected function createRefreshToken($user)
    {
        $valid = new \DateTime('now');
        $valid->add(new \DateInterval('P3D'));

        $refreshToken = $this->refreshTokenManager->create();
        $refreshToken->setUsername($user->getEmail());
        $refreshToken->setRefreshToken();
        $refreshToken->setValid($valid);

        $this->refreshTokenManager->save($refreshToken);

        return $refreshToken->getRefreshToken();
    }*/
}
