<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class ShibbolethAuthenticator extends AbstractGuardAuthenticator
{
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

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        $idpUrl = null,
        $remoteUserVar = null,
        EntityManagerInterface $entityManager = null,
        UserPasswordEncoderInterface $passwordEncoder = null
    ) {
        $this->idpUrl = $idpUrl ?: 'unknown';
        $this->remoteUserVar = $remoteUserVar ?: 'HTTP_EPPN';
        $this->urlGenerator = $urlGenerator;
        $this->entityManager = $entityManager;
        $this->passwordEncoder = $passwordEncoder;
    }

    protected function getRedirectUrl()
    {
        if (!\getenv('ENABLE_SHIBBOLETH')) {
            return $this->urlGenerator->generate('login');
        }

        return $this->urlGenerator->generate('shib_login');
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
            'mail' => $request->server->get('mail'),
            'firstName' => $request->server->get('givenName'),
            'lastName' => $request->server->get('sn')
        ];

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
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $credentials['mail']]);

        if (!$user) {
            $user = new User();
            $role = array("ROLE_USER");
            $user
                ->setEmail($credentials['mail'])
                ->setPassword($this->passwordEncoder->encodePassword(
                    $user,
                    random_bytes(32)
                ))
                ->setFirstName(ucfirst(strtolower($credentials['firstName'])))
                ->setLastName($credentials['lastName'])
                ->setRoles($role);

            # TODO: Add user's firstname and lastname fetching

            $this->entityManager->persist($user);
            $this->entityManager->flush();
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
            return new Response(); //RedirectResponse($redirectTo);
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
        $response = new RedirectResponse('/');

        // // if ($targetPath = $this->getTargetPath($request->getSession(), $providerKey)) {
        // //     $response->setTargetUrl($targetPath);
        // // }
        // // else {
        // //     $response->setTargetUrl($this->router->generate('users'));
        // // }

        //$response->setTargetUrl($this->urlGenerator->generate('users'));

        //return $response;
        return null;
    }

    /**
     * @param Request $request The request that resulted in an AuthenticationException
     * @param AuthenticationException $authException The exception that started the authentication process
     *
     * @return Response
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        $redirectTo = $this->getRedirectUrl();
        if (in_array('application/json', $request->getAcceptableContentTypes())) {
            return new JsonResponse(array(
                'status' => 'error',
                'message' => 'You are not authenticated.',
                'redirect' => $redirectTo,
            ), Response::HTTP_FORBIDDEN);
        } else {
            return new RedirectResponse($redirectTo);
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
        if (!\getenv('ENABLE_SHIBBOLETH')) {
            return false;
        }

        if ($request->server->has($this->remoteUserVar)) {
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
