<?php

namespace App\Controller;


use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Response;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class EditorController extends Controller
{
    private $deviceRepository;
    private $labRepository;
    private $controlProtocolTypeRepository;
    private $hypervisorRepository;
    private $flavorRepository;
    private $operatingSystemRepository;

    /** @var LoggerInterface $logger */
    private $logger;

    public function __construct(TokenStorageInterface $tokenStorageInterface, JWTTokenManagerInterface $jwtManager)
    {
        $this->jwtManager = $jwtManager;
        $this->tokenStorageInterface = $tokenStorageInterface;
    }

    /**
     * @Rest\Get("/api/token", name="api_token")
     * 
     */
    public function getToken(Request $request)
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        
        if($this->getUser() !== null) {

            $user = $this->tokenStorageInterface->getToken()->getUser();
            $token = $this->jwtManager->create($user);

            $response->setContent(json_encode([
                'code'=> 200,
                'status'=>'success',
                'message' => 'User connected',
                'data' => ["token"=> $token]]));
        }
        else {

            $response->setContent(json_encode([
                'code'=> 403,
                'status'=>'Forbidden',
                'message' => 'No user connected']));
        }

        return $response; 
    }

}