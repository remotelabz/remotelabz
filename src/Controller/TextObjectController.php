<?php

namespace App\Controller;

use IPTools;
use App\Entity\TextObject;
use App\Entity\Lab;
use App\Entity\Device;
use App\Entity\Network;
use App\Entity\Activity;
use App\Entity\LabInstance;
use App\Entity\DeviceInstance;
use App\Entity\NetworkInterfaceInstance;
use App\Entity\NetworkInterface;
use App\Entity\NetworkSettings;
use App\Security\ACL\LabVoter;
use GuzzleHttp\Psr7;
use App\Form\LabType;
use GuzzleHttp\Client;
use App\Form\DeviceType;
use Psr\Log\LoggerInterface;
use App\Repository\TextObjectRepository;
use App\Repository\LabRepository;
use App\Repository\UserRepository;
use FOS\RestBundle\Context\Context;
use App\Repository\DeviceRepository;
use Remotelabz\Message\Message\InstanceActionMessage;
use App\Repository\ActivityRepository;
use JMS\Serializer\SerializerInterface;
use App\Exception\NotInstancedException;
use JMS\Serializer\SerializationContext;
use App\Repository\LabInstanceRepository;
use Doctrine\Common\Collections\Criteria;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\RequestException;
use App\Exception\AlreadyInstancedException;
use App\Repository\OperatingSystemRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Service\Lab\LabImporter;
use App\Service\LabBannerFileUploader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\ORMException;
use Exception;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;

class TextObjectController extends Controller
{
    /** @var LoggerInterface $logger */
    private $logger;

    /** @var TextObjectRepository $textobjectRepository */
    private $textobjectRepository;

    public function __construct(
        LoggerInterface $logger,
        operatingSystemRepository $operatingSystemRepository,
        SerializerInterface $serializerInterface,
        TextObjectRepository $textobjectRepository,
        LabRepository $labRepository,
        EntityManagerInterface $entityManager)
    {
        $this->logger = $logger;
        $this->textobjectRepository = $textobjectRepository;
        $this->labRepository = $labRepository;
        $this->entityManager = $entityManager;
    }

    
	#[Get('/api/labs/{id<\d+>}/textobjects', name: 'api_get_textobjects')]
    public function indexAction(int $id, Request $request, UserRepository $userRepository)
    {
        $lab = $this->labRepository->find($id);
        $this->denyAccessUnlessGranted(LabVoter::SEE_TEXTOBJECT, $lab);
        $textobjects = $this->textobjectRepository->findByLab($id);
        $data = [];
        foreach($textobjects as $textobject){

            $data[$textobject->getId()] = [
                "id"=> $textobject->getId(),
                "name"=> $textobject->getName(),
                "type"=> $textobject->getType(),
                "data"=> $textobject->getData(),
                "newdata"=> $textobject->getNewdata(),
            ];
            /*array_push($data, [
                "name"=> $textobject->getName(),
                "type"=> $textobject->getType(),
                "data"=> $textobject->getData(),
                "newdata"=> $textobject->getNewdata(),
            ]);*/
        }

        $response = new Response();
        $response->setContent(json_encode([
            'code'=> 200,
            'status'=>'success',
            'message' => 'Successfully listed textobjects (60062).',
            'data' => $data]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    
	#[Get('/api/labs/{labId<\d+>}/textobjects/{id<\d+>}', name: 'api_get_textobject')]
    public function showAction(
        int $labId,
        int $id,
        Request $request,
        UserInterface $user,
        LabInstanceRepository $labInstanceRepository,
        TextObjectRepository $textobjectRepository,
        SerializerInterface $serializer)
    {
        $lab = $this->labRepository->find($labId);
        $this->denyAccessUnlessGranted(LabVoter::SEE_TEXTOBJECT, $lab);

        $textobject = $textobjectRepository->findByIdAndLab($id, $labId);

        $data = [
            "name"=> $textobject->getName(),
            "type"=> $textobject->getType(),
            "data"=> $textobject->getData(),
            "newdata"=> $textobject->getNewdata(),
            "id"=>$textobject->getId(),
        ];

       
        $response = new Response();
        $response->setContent(json_encode([
            'code' => 200,
            'status'=>'success',
            'message' => 'Object loaded',
            'data' =>$data]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
    

    
	#[Post('/api/labs/{id<\d+>}/textobjects', name: 'api_new_textobject')]
    public function newAction(Request $request, int $id)
    {
        $lab = $this->labRepository->find($id);
        $this->denyAccessUnlessGranted(LabVoter::EDIT_TEXTOBJECT, $lab);

        $textobject = new TextObject();
        $data = json_decode($request->getContent(), true);
        $lab = $this->labRepository->findById($id);
        //$this->logger->debug($textobject);

        
        $textobject->setLab($lab);
        $textobject->setName($data['name']);
        $textobject->setData($data['data']);
        $textobject->setType($data['type']);

        $entityManager = $this->entityManager;
        $entityManager->persist($textobject);
        $entityManager->flush();

        $this->logger->info("TextObject named" . $textobject->getName() . " created");

       /* if ('json' === $request->getRequestFormat()) {
            return $this->json($textobject, 200, [], ['api_get_textobjects']);
        }*/

       /* return $this->redirectToRoute('edit_lab', [
            'id' => $lab->getId()
        ]);*/
        $response = new Response();
        $response->setContent(json_encode([
            'code' => 200,
            'status'=> 'success',
            'message' => 'Lab has been saved (60023).',
            'data' => [
                "id"=>$textobject->getId(),
                'name'=>$textobject->getName(),
                "type"=> $textobject->getType(),
                "data"=> $textobject->getData(),
                "newdata"=> $textobject->getNewdata()

            ]]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    
	#[Put('/api/labs/{labId<\d+>}/textobjects/{id<\d+>}', name: 'api_edit_textobject')]
    public function updateAction(Request $request, int $id, int $labId, TextObjectRepository $textobjectRepository, ManagerRegistry $doctrine)
    {
        $lab = $this->labRepository->find($labId);
        $this->denyAccessUnlessGranted(LabVoter::EDIT_TEXTOBJECT, $lab);

        $textobject = $textobjectRepository->findByIdAndLab($id, $labId);
        $data = json_decode($request->getContent(), true);   

        if (isset($data['name'])) {
            $textobject->setName($data['name']);
        }
        $textobject->setData($data['data']);

        $entityManager = $this->entityManager;
        $entityManager->persist($textobject);
        $entityManager->flush();

        $this->logger->info("TextObject named" . $textobject->getName() . " modified");

        $response = new Response();
        $response->setContent(json_encode([
            'code' => 201,
            'status'=> 'success',
            'message' => 'Lab has been saved (60023).'
            /*'data' => [
                'name'=>$textobject->getName(),
                "type"=> $textobject->getType(),
                "data"=> $textobject->getData(),
                "newdata"=> $textobject->getNewdata()

            ]*/]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

     
	#[Put('/api/labs/{labId<\d+>}/textobjects', name: 'api_edit_textobjects')]
    public function updateMultipleTextObjectsAction(Request $request, int $labId, TextObjectRepository $textobjectRepository, ManagerRegistry $doctrine)
    {
        $lab = $this->labRepository->find($labId);
        $this->denyAccessUnlessGranted(LabVoter::EDIT_TEXTOBJECT, $lab);

        $data = json_decode($request->getContent(), true);   

        foreach($data as $dataObject){
            $textobject = $textobjectRepository->findByIdAndLab($dataObject['id'], $labId);
            $textobject->setData($dataObject['data']);
            $entityManager = $this->entityManager;
            $entityManager->persist($textobject);
            $entityManager->flush();
        }

        $response = new Response();
        $response->setContent(json_encode([
            'code' => 201,
            'status'=> 'success',
            'message' => 'Lab has been saved (60023).'
           ]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;

    }

    
	#[Delete('/api/labs/{labId<\d+>}/textobjects/{id<\d+>}', name: 'api_delete_textobject')]
    public function deleteAction(ManagerRegistry $doctrine, Request $request, int $id, int $labId, TextObjectRepository $textobjectRepository)
    {
        $lab = $this->labRepository->find($labId);
        $this->denyAccessUnlessGranted(LabVoter::EDIT_TEXTOBJECT, $lab);

        $textobject = $textobjectRepository->findByIdAndLab($id, $labId);
        
        $entityManager = $doctrine->getManager();
        $entityManager->remove($textobject);
        $entityManager->flush();

        $response = new Response();
        $response->setContent(json_encode([
            'code' => 200,
            'status'=>'success',
            'message' => 'Lab has been saved (60023).'
        ]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;

    }
}
