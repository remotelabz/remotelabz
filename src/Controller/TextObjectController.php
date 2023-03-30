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
use GuzzleHttp\Psr7;
use App\Form\LabType;
use GuzzleHttp\Client;
use App\Form\DeviceType;
use Psr\Log\LoggerInterface;
use App\Repository\TextObjectRepository;
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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Doctrine\Persistence\ManagerRegistry;


class TextObjectController extends Controller
{
    private $workerServer;

    private $workerPort;

    private $workerAddress;

    /** @var LoggerInterface $logger */
    private $logger;

    /** @var TextObjectRepository $textobjectRepository */
    private $textobjectRepository;

    public function __construct(
        LoggerInterface $logger,
        operatingSystemRepository $operatingSystemRepository,
        SerializerInterface $serializerInterface,
        TextObjectRepository $textobjectRepository,
        LabRepository $labRepository)
    {
        $this->workerServer = (string) getenv('WORKER_SERVER');
        $this->workerPort = (int) getenv('WORKER_PORT');
        $this->workerAddress = $this->workerServer . ":" . $this->workerPort;
        $this->logger = $logger;
        $this->textobjectRepository = $textobjectRepository;
        $this->labRepository = $labRepository;
    }

    /**
     * @Route("/textobjects", name="textobjects")
     * 
     * @Rest\Get("/api/labs/{id<\d+>}/textobjects", name="api_get_textobjects")
     * 
     */
    public function indexAction(int $id, Request $request, UserRepository $userRepository)
    {
        $textobjects = $this->textobjectRepository->findByLab($id);
        $data = [];
        foreach($textobjects as $textobject){
            array_push($data, [
                "name"=> $textobject->getName(),
                "type"=> $textobject->getType(),
                "data"=> $textobject->getData(),
                "newdata"=> $textobject->getNewdata(),
                "id"=>$textobject->getId(),
            ]);
        }
        /*$search = $request->query->get('search', '');
        //$this->logger->debug("Search:".$search);
        //$this->logger->debug("User id:".$this->getUser()->getId());
        if  ($this->getUser()->isAdministrator())
            $author = $request->query->get('author', 1);
        else 
            $author = $request->query->get('author', $this->getUser()->getId());
        //$this->logger->debug("Author :".$author);
        
        $limit = $request->query->get('limit', 10);
        $page = $request->query->get('page', 1);
        $orderBy = $request->query->get('order_by', 'lastUpdated');
        $sortDirection = $request->query->get('sort_direction', Criteria::DESC);

        //Have to distinguish exact request from Sandbox and other request
        if (strpos($search,"Sandbox_") === false ) {
            $criteria = Criteria::create()
                ->where(Criteria::expr()->contains('name', $search));
        }
        else {$criteria = Criteria::create()
            ->where(Criteria::expr()->eq('name', $search));
            //$this->logger->debug("Sandbox search detected");
            
        }

        if ($author > 1) {
            $criteria->andWhere(Criteria::expr()->eq('author', $userRepository->find($author)));
        }

        $criteria
            ->orderBy([
                $orderBy => $sortDirection
            ])
        ;

        $labs = $this->labRepository->matching($criteria);
        $count = $labs->count();*/

        // paging results
        /*try {
            $labs = $labs->slice($page * $limit - $limit, $limit);
        } catch (ORMException $e) {
            throw new NotFoundHttpException('Incorrect order field or sort direction', $e, $e->getCode());
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($labs, 200, [], ["api_get_lab"]);
        }

        return $this->render('lab/index.html.twig', [
            'labs' => $labs,
            'count' => $count,
            'search' => $search,
            'limit' => $limit,
            'page' => $page,
            'author' => $author,
        ]);*/

        $response = new Response();
        $response->setContent(json_encode([
            'code'=> 200,
            'status'=>'success',
            'message' => 'Successfully listed textobjects (60062).',
            'data' => $data]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

 /**
     * @Route("/labs/{labId<\d+>}/textobjects/{id<\d+>}", name="show_textobject", methods="GET")
     * 
     * @Rest\Get("/api/labs/{labId<\d+>}/textobjects/{id<\d+>}", name="api_get_textobject")
     */
    public function showAction(
        int $labId,
        int $id,
        Request $request,
        UserInterface $user,
        LabInstanceRepository $labInstanceRepository,
        TextObjectRepository $textobjectRepository,
        SerializerInterface $serializer)
    {
        //$lab = $labRepository->findById($LabId);
        $textobject = $textobjectRepository->findByIdAndLab($id, $labId);

        $data = [
            "name"=> $textobject[0]->getName(),
            "type"=> $textobject[0]->getType(),
            "data"=> $textobject[0]->getData(),
            "newdata"=> $textobject[0]->getNewdata(),
            "id"=>$textobject[0]->getId(),
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
    

    /**
     * @Route("/textobjects/new", name="new_textobject")
     * 
     * @Rest\Post("/api/labs/{id<\d+>}/textobjects", name="api_new_textobject")
     */
    public function newAction(Request $request, int $id)
    {
        $textobject = new TextObject();
        $data = json_decode($request->getContent(), true);
        $lab = $this->labRepository->findById($id);
        //$this->logger->debug($textobject);

        
        $textobject->setLab($lab);
        $textobject->setName($data['name']);
        $textobject->setData($data['data']);
        $textobject->setType($data['type']);

        $entityManager = $this->getDoctrine()->getManager();
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
                'name'=>$textobject->getName(),
                "type"=> $textobject->getType(),
                "data"=> $textobject->getData(),
                "newdata"=> $textobject->getNewdata()

            ]]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }


    /**
     * @Route("/admin/labs/{id<\d+>}/edit", name="edit_lab")
     */
    /*public function editAction(Request $request, int $id)
    {

        $lab = $this->labRepository->find($id);
        $this->logger->debug("Lab '".$lab->getName()."' is edited by : ".$this->getUser()->getUsername());

        if ( !is_null($lab) and (($lab->getAuthor()->getId() == $this->getUser()->getId() ) or $this->getUser()->isAdministrator()) )
        {
            $this->logger->info("Lab '".$lab->getName()."' is edited by : ".$this->getUser()->getUsername());
        

        if (!$lab) {
            throw new NotFoundHttpException("Lab " . $id . " does not exist.");
        }

        $labForm = $this->createForm(LabType::class, $lab);
        $labForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $lab = json_decode($request->getContent(), true);
            $labForm->submit($lab, false);
        }

        return $this->render('lab/editor.html.twig', ['lab' => $lab]);
    }
    else
        { 
            if (!is_null($lab))
                $this->logger->warning("User ".$this->getUser()->getUsername()." has tried to edit the lab".$lab->getName());
            else 
                $this->logger->warning("User ".$this->getUser()->getUsername()." has tried to edit a lab");
            return $this->redirectToRoute('index');
        }
    }*/

    /**
     * @Rest\Put("/api/labs/{labId<\d+>}/textobjects/{id<\d+>}", name="api_edit_textobject")
     */
    public function updateAction(Request $request, int $id, int $labId, TextObjectRepository $textobjectRepository, ManagerRegistry $doctrine)
    {
        $textobject = $textobjectRepository->findByIdAndLab($id, $labId);
        $data = json_decode($request->getContent(), true);   

        $textobject = $textobject[0];
        if (isset($data['name'])) {
            $textobject->setName($data['name']);
        }
        $textobject->setData($data['data']);

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($textobject);
        $entityManager->flush();


        $this->logger->info("TextObject named" . $textobject->getName() . " modified");

       /* if ('json' === $request->getRequestFormat()) {
            return $this->json($textobject, 200, [], ['api_get_textobjects']);
        }*/

       /* return $this->redirectToRoute('edit_lab', [
            'id' => $lab->getId()
        ]);*/
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

        return $this->json($labForm, 200, [], ['api_get_textobjects']);
    }

    /**
     * @Route("/admin/labs/{id<\d+>}/delete", name="delete_lab", methods="GET")
     * 
     * @Rest\Delete("/api/labs/{labId<\d+>}/textobjects/{id<\d+>}", name="api_delete_lab")
     */
    public function deleteAction(ManagerRegistry $doctrine, Request $request, int $id, int $labId, TextObjectRepository $textobjectRepository)
    {
        $textobject = $textobjectRepository->findByIdAndLab($id, $labId);
        
        $entityManager = $doctrine->getManager();
        $entityManager->remove($textobject[0]);
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
