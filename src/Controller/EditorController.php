<?php

namespace App\Controller;

use App\Repository\LabRepository;
use App\Repository\LabInstanceRepository;
use App\Entity\InvitationCode;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;
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

    public function __construct(TokenStorageInterface $tokenStorageInterface, JWTTokenManagerInterface $jwtManager, LabInstanceRepository $labInstanceRepository, LabRepository $labRepository)
    {
        $this->jwtManager = $jwtManager;
        $this->tokenStorageInterface = $tokenStorageInterface;
        $this->labRepository = $labRepository;
        $this->labInstanceRepository = $labInstanceRepository;
    }

    
	#[Post('/api/user/rights/lab/{id<\d+>}', name: 'api_user_rights')]
    public function getToken(Request $request, int $id)
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $data = json_decode($request->getContent(), true);
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
            if ($data['labInstance'] == null) {
                $hasGroupAccess = 0;
                $isGroupOwner = 0;
            }
            else {
                $labInstance = $this->labInstanceRepository->find($data['labInstance']);
                if ($labInstance->getOwnedBy() == "group") {
                    $isGroupOwner = 1;
                    if ($labInstance->getOwner()->isElevatedUser($user)) {
                        $hasGroupAccess = 1;
                    }
                    else {
                        $hasGroupAccess = 0;
                    }
                }
                else {
                    $hasGroupAccess = 0;
                    $isGroupOwner = 0;
                }
            }

            if ($user instanceof InvitationCode) {
                $response->setContent(json_encode([
                    'code'=> 200,
                    'status'=>'success',
                    'message' => 'User connected',
                    'data' => [
                        "token"=> $token, 
                        "role" => "ROLE_USER", 
                        "email"=>$user->getMail(), 
                        "username" => $user->getName(),
                        "author" => $author,
                        "hasGroupAccess" => $hasGroupAccess,
                        "isGroupOwner" => $isGroupOwner,
                        "virtuality" => $lab->getVirtuality()
                    ]]));
            }
            else {
                $response->setContent(json_encode([
                    'code'=> 200,
                    'status'=>'success',
                    'message' => 'User connected',
                    'data' => [
                        "token"=> $token, 
                        "role" => $user->getHighestRole(), 
                        "email"=>$user->getEmail(), 
                        "username" => $user->getName(),
                        "author" => $author,
                        "hasGroupAccess" => $hasGroupAccess,
                        "isGroupOwner" => $isGroupOwner,
                        "virtuality" => $lab->getVirtuality()
                    ]]));
            }

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