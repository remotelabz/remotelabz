<?php

namespace App\Controller;

use IPTools;
use App\Entity\NetworkDevice;
use App\Entity\Lab;
use GuzzleHttp\Psr7;
use App\Form\LabType;
use GuzzleHttp\Client;
use App\Form\DeviceType;
use Psr\Log\LoggerInterface;
use App\Repository\NetworkDeviceRepository;
use App\Repository\LabRepository;
use App\Exception\WorkerException;
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
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\ORMException;
use Exception;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\HeaderUtils;

use Doctrine\Persistence\ManagerRegistry;


class NetworkDeviceController extends Controller
{
    private $workerServer;

    private $workerPort;

    private $workerAddress;

    /** @var LoggerInterface $logger */
    private $logger;

    /** @var NetworkDeviceRepository $networkdeviceRepository */
    private $networkdeviceRepository;

    public function __construct(
        LoggerInterface $logger,
        operatingSystemRepository $operatingSystemRepository,
        SerializerInterface $serializerInterface,
        NetworkDeviceRepository $networkdeviceRepository,
        LabRepository $labRepository)
    {
        $this->workerServer = (string) getenv('WORKER_SERVER');
        $this->workerPort = (int) getenv('WORKER_PORT');
        $this->workerAddress = $this->workerServer . ":" . $this->workerPort;
        $this->logger = $logger;
        $this->networkdeviceRepository = $networkdeviceRepository;
        $this->labRepository = $labRepository;
    }

    /**
     * @Route("/networks", name="networks")
     * 
     * @Rest\Get("/api/labs/{id<\d+>}/networks", name="api_get_networks")
     * 
     */
    public function indexAction(int $id, Request $request)
    {
        $networks = $this->networkdeviceRepository->findByLab($id);
        $data = [];
        foreach($networks as $network){
            array_push($data, [
                "id"=>$network->getId(),
                "name"=> $network->getName(),
                "type"=> $network->getType(),
                "count"=> $network->getCount(),
                "top"=> $network->getTop(),
                "left"=> $network->getLeftPosition(),
                "visibility"=> $network->getVisibility(),
                "postfix"=> $network->getPostfix(),   
            ]);
        }

        $response = new Response();
        $response->setContent(json_encode([
            'code'=> 200,
            'status'=>'success',
            'message' => 'Successfully listed networks (60004).',
            'data' => $data]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

 /**
     * @Route("/labs/{labId<\d+>}/networks/{id<\d+>}", name="show_network", methods="GET")
     * 
     * @Rest\Get("/api/labs/{labId<\d+>}/networks/{id<\d+>}", name="api_get_network")
     */
    public function showAction(
        int $labId,
        int $id,
        Request $request,
        UserInterface $user,
        LabInstanceRepository $labInstanceRepository,
        NetworkDeviceRepository $networkdeviceRepository,
        SerializerInterface $serializer)
    {
        //$lab = $labRepository->findById($LabId);
        $network = $networkdeviceRepository->findByIdAndLab($id, $labId);

        $data = [
            "name"=> $network->getName(),
            "type"=> $network->getType(),
            "count"=> $network->getCount(),
            "top"=> $network->getTop(),
            "left"=> $network->getLeftPosition(),
            "visibility"=> $network->getVisibility(),          
        ];

       
        $response = new Response();
        $response->setContent(json_encode([
            'code' => 200,
            'status'=>'success',
            'message' => 'Successfully listed network (60005).',
            'data' =>$data]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
    

    /**
     * @Route("/networkDevice/new", name="new_networkdevice")
     * 
     * @Rest\Post("/api/labs/{id<\d+>}/networks", name="api_new_networkdevice")
     */
    public function newAction(Request $request, int $id)
    {
        $network = new NetworkDevice();
        $data = json_decode($request->getContent(), true);
        $lab = $this->labRepository->findById($id);
        //$this->logger->debug($textobject);

        
        $network->setLab($lab);
        $network->setName($data['name']);
        $network->setCount($data['count']);
        $network->setType($data['type']);
        $network->setLeftPosition($data['left']);
        $network->setTop($data['top']);
        $network->setVisibility($data['visibility']);
        $network->setPostfix($data['postfix']);

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($network);
        $entityManager->flush();


        $this->logger->info("Network named" . $network->getName() . " created");

        $response = new Response();
        $response->setContent(json_encode([
            'code' => 201,
            'status'=> 'success',
            'message' => 'Network has been added to the lab (60006).',
            'data' => [
                'id'=>$network->getId(),
            ]]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Rest\Put("/api/labs/{labId<\d+>}/networks/{id<\d+>}", name="api_edit_network")
     */
    public function updateAction(Request $request, int $id, int $labId, NetworkDeviceRepository $networkdeviceRepository, ManagerRegistry $doctrine)
    {
        $network = $networkdeviceRepository->findByIdAndLab($id, $labId);
        $data = json_decode($request->getContent(), true);   

        if (isset($data['visibility'])) {
            $network->setVisibility($data['visibility']);
        }
        if (isset($data['top'])) {
            $network->setTop($data['top']);
        }
        if (isset($data['left'])) {
            $network->setLeftPosition($data['left']);
        }
        if (isset($data['name'])) {
            $network->setName($data['name']);
            $network->setType($data['type']);
            $network->setCount($data['count']);
            $network->setPostfix($data['postfix']);
        }

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($network);
        $entityManager->flush();


        $this->logger->info("Network named" . $network->getName() . " modified");

        $response = new Response();
        $response->setContent(json_encode([
            'code' => 201,
            'status'=> 'success',
            'message' => 'Lab has been saved (60023).'
           ]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Rest\Put("/api/labs/{labId<\d+>}/networks", name="api_edit_networks")
     */
    public function updateMultipleNetwoksAction(Request $request, int $labId, NetworkDeviceRepository $networkdeviceRepository, ManagerRegistry $doctrine)
    {
       // $network = $networkdeviceRepository->findByIdAndLab($id, $labId);
        $data = json_decode($request->getContent(), true);   

        foreach($data as $dataObject){
            $network = $networkdeviceRepository->findByIdAndLab($dataObject['id'], $labId);
            $network->setTop($dataObject['top']);
            $network->setLeftPosition($dataObject['left']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($network);
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

    /**
     * 
     * @Rest\Delete("/api/labs/{labId<\d+>}/networks/{id<\d+>}", name="api_delete_network")
     */
    public function deleteAction(ManagerRegistry $doctrine, Request $request, int $id, int $labId, NetworkDeviceRepository $networkdeviceRepository)
    {
        $network = $networkdeviceRepository->findByIdAndLab($id, $labId);
        
        $entityManager = $doctrine->getManager();
        $entityManager->remove($network);
        $entityManager->flush();

        $data = [
            "id"=> $network->getId(),
            "name"=> $network->getName(),
            "type"=> $network->getType(),
            "count"=> $network->getCount(),
            "top"=> $network->getTop(),
            "left"=> $network->getLeftPosition(),          
        ];

        $response = new Response();
        $response->setContent(json_encode([
            'code' => 200,
            'status'=>'success',
            'message' => 'Lab has been saved (60023).',
            'data' => $data
        ]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;

    }
}
