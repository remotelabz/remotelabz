<?php

namespace App\Controller;

use App\Repository\LabRepository;
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
    private $labRepository;

    /** @var LoggerInterface $logger */
    private $logger;

    public function __construct(TokenStorageInterface $tokenStorageInterface, JWTTokenManagerInterface $jwtManager, LabRepository $labRepository)
    {
        $this->jwtManager = $jwtManager;
        $this->tokenStorageInterface = $tokenStorageInterface;
        $this->labRepository = $labRepository;
    }

    /**
     * @Rest\Get("/api/user/rights/lab/{id<\d+>}", name="api_user_rights")
     * 
     */
    public function getToken(Request $request, int $id)
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        
        if($this->getUser() !== null) {

            $user = $this->tokenStorageInterface->getToken()->getUser();
            $token = $this->jwtManager->create($user);
            $lab = $this->labRepository->find($id);
            if($lab->getAuthor() == $user) {
                $author = 1;
            }
            else {
                $author = 0;
            }

            $response->setContent(json_encode([
                'code'=> 200,
                'status'=>'success',
                'message' => 'User connected',
                'data' => [
                    "token"=> $token, 
                    "role" => $user->getHighestRole(), 
                    "email"=>$user->getEmail(), 
                    "username" => $user->getName(),
                    "author" => $author
                ]]));
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