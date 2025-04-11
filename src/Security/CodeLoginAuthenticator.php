<?php

namespace App\Security;

use DateTime;
use App\Entity\InvitationCode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\SecurityBundle\Security;;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
//use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;


class CodeLoginAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    private $entityManager;
    private $router;
    private $csrfTokenManager;
    private $passwordHasher;
    private $JWTManager;
    public const LOGIN_ROUTE = 'code_login';

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
        return 'code_login' === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

/*    public function authenticate(Request $request): Passport
    {
        $code = $request->request->get('code', '');
        $invitedUser = $this->entityManager->getRepository(InvitationCode::class)->findOneBy(['code' => $code]);
        return new Passport(
            new UserBadge($invitedUser->getMail()." ".$code,  function($credentials) {
                $mail = explode(" ", $credentials)[0];
                $userCode = explode(" ", $credentials)[1];

                return $this->entityManager->getRepository(InvitationCode::class)->findOneBy(['mail'=>$mail, "code"=>$userCode]);
            }),
            new PasswordCredentials($code),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
            ]
        );
    }
*/

public function authenticate(Request $request): Passport
{
    $code = $request->request->get('code', '');
    $invitedUser = $this->entityManager->getRepository(InvitationCode::class)->findOneBy(['code' => $code]);

    if (is_null($invitedUser)) {
        throw new AccessDeniedException();
    }

    return new Passport(
        new UserBadge($invitedUser->getMail()." ".$code, function($credentials) {
            [$mail, $userCode] = explode(" ", $credentials);

            return $this->entityManager->getRepository(InvitationCode::class)->findOneBy([
                'mail' => $mail, 
                'code' => $userCode
            ]);
        }),
        new PasswordCredentials($code),
        [
            new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
        ]
    );
}












    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        /*if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }*/
        $response = new RedirectResponse('/');
        $user = $this->entityManager->getRepository(InvitationCode::class)->findOneBy(['code' => $request->get('code')]);

        $jwtToken = $this->JWTManager->create($user);
        $now = new DateTime();
        $jwtTokenCookie = Cookie::create('bearer', $jwtToken, $now->getTimestamp() + 24 * 3600);

        $response->headers->setCookie($jwtTokenCookie);
        $response->setTargetUrl($this->router->generate('show_lab_to_guest', ['id'=> $user->getLab()->getId()]));
        return $response;
    }


    /**
     * @inheritDoc
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception):Response
    {
        /*var_dump('failure'); exit;
        if ($request->hasSession()) {
            $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
        }

        $parameters = $request->query->has('ref_url') ? ['ref_url' => $request->query->get('ref_url')] : [];
        $url = $this->getLoginUrl($parameters);

        return new RedirectResponse($url);*/
        $data = [
            // you may want to customize or obfuscate the message first
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData())

            // or to translate this message
            // $this->translator->trans($exception->getMessageKey(), $exception->getMessageData())
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    /*protected function getLoginUrl($parameters = []): ?string
    {
        return $this->router->generate('login', $parameters);
    }*/

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }

}
