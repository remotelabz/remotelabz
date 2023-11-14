<?php

namespace App\Controller;

use IPTools;
use App\Entity\Lab;
use App\Entity\Device;
use App\Entity\Network;
use App\Entity\Activity;
use App\Entity\LabInstance;
use App\Entity\EditorData;
use App\Entity\DeviceInstance;
use App\Entity\NetworkInterfaceInstance;
use App\Entity\NetworkInterface;
use App\Entity\NetworkSettings;
use GuzzleHttp\Psr7;
use App\Form\LabType;
use GuzzleHttp\Client;
use App\Form\DeviceType;
use Psr\Log\LoggerInterface;
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
use App\Repository\NetworkInterfaceRepository;
use App\Service\Lab\LabImporter;
use App\Repository\FlavorRepository;
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
use Symfony\Component\HttpFoundation\BinaryFileResponse;


class LabController extends Controller
{
    private $workerServer;

    private $workerPort;

    private $workerAddress;

    /** @var LoggerInterface $logger */
    private $logger;

    /** @var LabRepository $labRepository */
    private $labRepository;
    private $deviceRepository;
    private $operatingSystemRepository;
    private $flavorRepository;
    private $serializer;
    private $labInstanceRepository;

    public function __construct(
        LoggerInterface $logger,
        LabRepository $labRepository,
        DeviceRepository $deviceRepository,
        operatingSystemRepository $operatingSystemRepository,
        FlavorRepository $flavorRepository,
        SerializerInterface $serializerInterface,
        LabInstanceRepository $labInstanceRepository)
    {
        $this->workerServer = (string) getenv('WORKER_SERVER');
        $this->workerPort = (int) getenv('WORKER_PORT');
        $this->workerAddress = $this->workerServer . ":" . $this->workerPort;
        $this->logger = $logger;
        $this->labRepository = $labRepository;
        $this->deviceRepository = $deviceRepository;
        $this->operatingSystemRepository=$operatingSystemRepository;
        $this->flavorRepository=$flavorRepository;
        $this->serializer = $serializerInterface;
        $this->labInstanceRepository = $labInstanceRepository;
    }

    /**
     * @Route("/labs", name="labs")
     * 
     * @Rest\Get("/api/labs", name="api_get_labs")
     * @Rest\QueryParam(name="limit", requirements="\d+", default="10")
     */
    public function indexAction(Request $request, UserRepository $userRepository)
    {
        $search = $request->query->get('search', '');
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
            ->andWhere(Criteria::expr()->eq('isTemplate', false))
            ->orderBy([
                $orderBy => $sortDirection
            ])
        ;

        $labs = $this->labRepository->matching($criteria);
        $count = $labs->count();

        // paging results
        try {
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
        ]);
    }

    /**
     * @Route("/dashboard/labs", name="dashboard_labs")
     */
    public function dashboardIndexAction(Request $request, UserRepository $userRepository)
    {
        $search = $request->query->get('search', '');
        $author = $request->query->get('author', 0);
        $limit = $request->query->get('limit', 10);
        $page = $request->query->get('page', 1);

        $criteria = Criteria::create()
            ->where(Criteria::expr()->contains('name', $search));

        if ($author > 0) {
            $criteria->andWhere(Criteria::expr()->eq('author', $userRepository->find($author)));
        }

        $criteria
            ->orderBy([
                'id' => Criteria::DESC
            ])
        ;

        $labs = $this->labRepository->matching($criteria);
        $count = $labs->count();

        return $this->render('lab/dashboard_index.html.twig', [
            'labs' => $labs->slice($page * $limit - $limit, $limit),
            'count' => $count,
            'search' => $search,
            'limit' => $limit,
            'page' => $page,
            'author' => $author,
        ]);
    }

    /**
     * 
     * @Rest\Get("/api/labs/template", name="api_get_labs_template")
     */
    public function getLabTemplates(Request $request, UserRepository $userRepository)
    {

        $labs = $this->labRepository->findBy(["isTemplate" => true]);

        if ('json' === $request->getRequestFormat()) {
            return $this->json($labs, 200, [], ["api_get_lab"]);
        }
    }

    /**
     * 
     * @Rest\Get("/api/labs/template/{id<\d+>}", name="api_get_lab_template_by_uuid")
     */
    public function getOneLabTemplate(Request $request, int $id)
    {

        $labs = $this->labRepository->find($id);

        if ('json' === $request->getRequestFormat()) {
            return $this->json($labs, 200, [], ["api_get_lab_template"]);
        }
    }

    /**
     * @Route("/labs/{id<\d+>}", name="show_lab", methods="GET")
     * 
     * @Rest\Get("/api/labs/{id<\d+>}", name="api_get_lab")
     */
    public function showAction(
        int $id,
        Request $request,
        UserInterface $user,
        LabInstanceRepository $labInstanceRepository,
        LabRepository $labRepository,
        SerializerInterface $serializer)
    {
        $lab = $labRepository->find($id);

        if (!$lab) {
            throw new NotFoundHttpException("Lab " . $id . " does not exist.");
        }

        // Remove all instances not belongs to current user (changes are not stored in database)
        $userLabInstance = $labInstanceRepository->findByUserAndLab($user, $lab);
        // $lab->setInstances($userLabInstance != null ? [$userLabInstance] : []);
        $deviceStarted = [];

        foreach ($lab->getDevices()->getValues() as $device) {
            $deviceStarted[$device->getId()] = false;

            if ($userLabInstance && $userLabInstance->getUserDeviceInstance($device)) {
                $deviceStarted[$device->getId()] = true;
            }
        }

        if ('json' === $request->getRequestFormat()) {
            $context=$request->get('_route');
            //Change the context value to limit the return information
            return $this->json($lab, 200, [], [$context]);
        }

        $instanceManagerProps = [
            'user' => $this->getUser(),
            'labInstance' => $userLabInstance,
            'lab' => $lab,
            'isJitsiCallEnabled' => (bool) $this->getParameter('app.enable_jitsi_call'),
            'isSandbox' => false
        ];

        $props=$serializer->serialize(
            $instanceManagerProps,
            'json',
            //SerializationContext::create()->setGroups(['api_get_lab', 'api_get_user', 'api_get_group', 'api_get_lab_instance', 'api_get_device_instance'])
            SerializationContext::create()->setGroups(['api_get_lab','api_get_lab_instance'])
        );
        //$this->logger->debug("show_lab props".$props);
        return $this->render('lab/view.html.twig', [
            'lab' => $lab,
            'labInstance' => $userLabInstance,
            'deviceStarted' => $deviceStarted,
            'user' => $user,
            'props' => $props,
        ]);
    }

    /**
     * @Route("/labs/guest/{id<\d+>}", name="show_lab_to_guest", methods="GET")
     * 
     */
    public function showToGuestAction(
        int $id,
        Request $request,
        UserInterface $guestUser,
        LabInstanceRepository $labInstanceRepository,
        LabRepository $labRepository,
        SerializerInterface $serializer)
    {
        $lab = $labRepository->find($id);

        if (!$lab) {
            throw new NotFoundHttpException("Lab " . $id . " does not exist.");
        }

        // Remove all instances not belongs to current user (changes are not stored in database)
        $userLabInstance = $labInstanceRepository->findByGuestAndLab($guestUser, $lab);
        // $lab->setInstances($userLabInstance != null ? [$userLabInstance] : []);
        $deviceStarted = [];

        foreach ($lab->getDevices()->getValues() as $device) {
            $deviceStarted[$device->getId()] = false;

            if ($userLabInstance && $userLabInstance->getUserDeviceInstance($device)) {
                $deviceStarted[$device->getId()] = true;
            }
        }

        if ('json' === $request->getRequestFormat()) {
            $context=$request->get('_route');
            //Change the context value to limit the return information
            return $this->json($lab, 200, [], [$context]);
        }

        $instanceManagerProps = [
            'user' => $this->getUser(),
            'labInstance' => $userLabInstance,
            'lab' => $lab,
            'isJitsiCallEnabled' => (bool) $this->getParameter('app.enable_jitsi_call'),
            'isSandbox' => false
        ];

        $props=$serializer->serialize(
            $instanceManagerProps,
            'json',
            //SerializationContext::create()->setGroups(['api_get_lab', 'api_get_user', 'api_get_group', 'api_get_lab_instance', 'api_get_device_instance'])
            SerializationContext::create()->setGroups(['api_invitation_codes','api_get_lab','api_get_lab_instance'])
        );
        //$this->logger->debug("show_lab props".$props);
        return $this->render('lab/guest_view.html.twig', [
            'lab' => $lab,
            'labInstance' => $userLabInstance,
            'deviceStarted' => $deviceStarted,
            'user' => $guestUser,
            'props' => $props,
        ]);
    }

    /**
     * @Route("/labs/info/{id<\d+>}", name="show_lab_test", methods="GET")
     * 
     * @Rest\Get("/api/labs/info/{id<\d+>}", name="api_get_lab_test")
     */
    public function showActionTest(
        int $id,
        Request $request,
        UserInterface $user,
        LabInstanceRepository $labInstanceRepository,
        LabRepository $labRepository,
        SerializerInterface $serializer)
    {
        $lab = $labRepository->findLabInfoById($id);
        $data = [
            "id"=>$lab["id"],
            "name"=>$lab["name"],
            "description"=>$lab["description"],
            "body"=>$lab["body"],
            "author"=>$lab["author"],
            "version"=>$lab["version"],
            "scripttimeout"=>$lab["scripttimeout"],
            "lock"=>$lab["locked"],
            "banner"=>$lab["banner"],
            "timer"=>$lab["timer"]
        ];

        $response = new Response();
        $response->setContent(json_encode([
            'code'=>200,
            'status'=>'success',
            'data' =>$data]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Route("/labs/{id<\d+>}/see/{instanceId<\d+>}", name="see_lab")
     */
    public function seeAction(Request $request, int $id, int $instanceId)
    {

        $lab = $this->labRepository->find($id);
        //$labInstance = $this->labInstanceRepository->findByUserAndLab($this->getUser(), $lab);
        $labInstance = $this->labInstanceRepository->find($instanceId);
        if($labInstance == null) {
            //$redirectTo = $this->getRedirectUrl();
               /* return new JsonResponse(array(
                    'status' => 'error',
                    'message' => 'Forbidden',
                    //'redirect' => $redirectTo,
                ), Response::HTTP_FORBIDDEN);*/
                return new Response('Forbidden', Response::HTTP_FORBIDDEN);
            //throw new NotFoundHttpException("Lab " . $id . " does not exist.");
        }

        if ( !is_null($lab))
        {
            $this->logger->info("Lab '".$lab->getName()."' is seen by : ".$this->getUser()->getUserIdentifier());
        

        if (!$lab) {
            throw new NotFoundHttpException("Lab " . $id . " does not exist.");
        };

        if ($request->getContentType() === 'json') {
            $lab = json_decode($request->getContent(), true);
        }

        return $this->render('editor.html.twig', ['id' => $id]);
    }
    else
        { 
            if (!is_null($lab))
                $this->logger->warning("User ".$this->getUser()->getUserIdentifier()." has tried to see the lab".$lab->getName());
            else 
                $this->logger->warning("User ".$this->getUser()->getUserIdentifier()." has tried to see a lab");
            return $this->redirectToRoute('index');
        }
    }

    /**
     * 
     * @Rest\Get("/api/labs/{id<\d+>}/html", name="api_get_lab_details")
     */
    public function getLabDetails(
        int $id,
        Request $request,
        UserInterface $user,
        LabInstanceRepository $labInstanceRepository,
        LabRepository $labRepository,
        SerializerInterface $serializer)
    {
        $lab = $labRepository->findLabDetailsById($id);

        $response = new Response();
        $response->setContent(json_encode([
            'code '=> 200,
            'status'=>'success',
            'data' => $lab['tasks']]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Route("/labs/new", name="new_lab")
     * @Route("/labsSandbox/new", name="new_lab_template")
     * 
     * @Rest\Post("/api/labs", name="api_new_lab")
     */
    public function newAction(Request $request)
    {

        $lab = json_decode($request->getContent(), true);

        $this->logger->debug($lab);

        $criteria = Criteria::create()
            ->where(Criteria::expr()->startsWith('name', 'Untitled Lab'));

        $untitledLabsCount = count($this->labRepository->matching($criteria));
        $name = 'Untitled Lab';

        if ($untitledLabsCount != 0) {
            $name .= ' (' . $untitledLabsCount . ')';
        }

        $lab = new Lab();
        $lab->setName($name)
            ->setAuthor($this->getUser());

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($lab);
        $entityManager->flush();

        if ('new_lab_template' == $request->get('_route')) {
            $lab->setIsTemplate(true);
        }

        $filesystem = new Filesystem();
            try {
                $src=$this->getParameter('directory.public.images').'/logo/nopic.jpg';
                $dst=$this->getParameter('directory.public.upload.lab.banner').'/'.$lab->getId().'/nopic.jpg';
            $filesystem->copy($src,$dst);
            $this->logger->debug("Copy from ".$src." to ".$dst);
            $lab->setBanner('nopic.jpg');
            }
            catch (IOExceptionInterface $exception) {
                $this->logger->error("An error occurred while creating your directory at ".$exception->getPath());
            }

        /* foreach($this->getUser()->getGroups() as $group) {
            if ($group->getGroup()->getName() !== "Default group")
                $group->getGroup()->addLab($lab);
        }
        */

        $this->logger->info($this->getUser()->getUserIdentifier() . " creates lab named " . $lab->getName());
        $entityManager->persist($lab);

        if ('json' === $request->getRequestFormat()) {
            return $this->json($lab, 200, [], ['api_get_lab']);
        }
        $entityManager->flush();

        if ('new_lab_template' == $request->get('_route')) {
            return $this->redirectToRoute('edit_lab_template', [
                'id' => $lab->getId()
            ]);
        }

        return $this->redirectToRoute('edit_lab', [
            'id' => $lab->getId()
        ]);
    }

    /**
     * 
     * @Rest\Post("/api/labs/{id<\d+>}/devices", name="api_add_device_lab")
     */
    public function addDeviceAction(Request $request, int $id, NetworkInterfaceRepository $networkInterfaceRepository)
    {
        $lab = $this->labRepository->find($id);

        if ( ($lab->getAuthor()->getId() == $this->getUser()->getId() ) or $this->getUser()->isAdministrator() )
        {
            $this->logger->debug("Add device to a lab from API by : ".$this->getUser()->getUserIdentifier());
        
        $device = new Device();
        
        $deviceForm = $this->createForm(DeviceType::class, $device);
        $deviceForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $device_array = json_decode($request->getContent(), true);
            //$this->logger->debug("json:",$device_array);
            /*$json_example='{
                "id": 121,
                "name": "FortiGate-v7.2.0",
                "brand": "",
                "model": "",
                "launchOrder": 0,
                "launchScript": null,
                "type": "vm",
                "virtuality": 1,
                "hypervisor": 7,
                "operatingSystem": 34,
                "controlInterface": null,
                "flavor": 8,
                "networkInterfaces":2,
                "uuid": "a697b0da-1427-46a7-9dc5-34e3435060c3",
                "createdAt": "2022-09-16T22:16:36+02:00",
                "lastUpdated": "2022-09-18T12:01:23+02:00",
                "controlProtocolTypes": [{ "id": 3 }],
                "editorData": {
                    "id": 119,
                    "x": 0,
                    "y": 0
                },
                "isTemplate": true
            }';
            $device_array = json_decode($json_example, true);*/
            //Delete this key otherwise the validation doesn't work.
            unset($device_array['controlProtocolTypes']);
            $device_array['networkInterfaces']=count($device_array['networkInterfaces']);
            $this->logger->debug("Add a device to lab via API from addDeviceAction: the request and json:",$device_array);
            $deviceForm->submit($device_array);
        }

        if ($deviceForm->isSubmitted()) {
            if ($deviceForm->isValid()) {
                $entityManager = $this->getDoctrine()->getManager();
                $this->logger->debug("Add device in lab form submitted is valid");

                $editorData = new EditorData();
                $editorData->setX($device_array['editorData']['x']);
                $editorData->setY($device_array['editorData']['y']);
                $entityManager->persist($editorData);

                /** @var Device $device */
                $new_device = $deviceForm->getData();
                $new_device->setEditorData($editorData);
                //$new_device->setCount($device_array['count']);
                if (isset($device_array['icon'])) {
                    $new_device->setIcon($device_array['icon']);
                }
                $new_device->setVirtuality($device_array['virtuality']);
                $this->logger->debug("Device added : ".$new_device->getName());
                
                $entityManager->persist($new_device);
                $editorData->setDevice($new_device);
                $entityManager->flush();
                $device = $this->deviceRepository->find($device_array['id']);
                $this->logger->debug("Source device id adds is :".$device_array['id']);
                $i=0;
                if ($device_array['networkInterfaces'] > 0) {
                    foreach ($device->getNetworkInterfaces() as $network_int) {
                        $new_network_inter=new NetworkInterface();
                        $new_setting=new NetworkSettings();
                        $new_setting=clone $network_int->getSettings();
                        $new_network_inter->setSettings($new_setting);
                        $new_network_inter->setName($device->getName()."_"."int".$i);
                        $i=$i+1;
                        $new_network_inter->setIsTemplate(true);
                        $new_network_inter->setVlan($network_int->getVlan());
                        $new_device->addNetworkInterface($new_network_inter);
                        $new_setting->setName($new_network_inter->getName());
                        $entityManager->persist($new_network_inter);
                        $entityManager->persist($new_setting);
                    }
                }

                foreach ($device->getControlProtocolTypes() as $control_protocol) {
                    $new_device->addControlProtocolType($control_protocol);        

                }
                $entityManager->persist($new_device);
                $entityManager->flush();

                $this->adddeviceinlab($new_device, $lab);

                return $this->json($new_device, 201, [], ['api_get_device']);
            } else {
                $this->logger->debug("Add device in lab form submitted is not valid");
                $this->logger->debug($deviceForm->getErrors());
                foreach ($deviceForm->getErrors(true) as $error) {
                    $this->logger->debug("Error validating :".$error->getMessage());
                }
            }
        }
        return $this->json($deviceForm, 200, [], ['api_get_device']);
    }
        else {
            $this->logger->warning("User ".$this->getUser()->getUserIdentifier()." has tried, via API, to add a device to lab".$lab->getName());
            return $this->redirectToRoute('index');
        }
    }

    private function adddeviceinlab(Device $new_device, Lab $lab) {
        
        if ($new_device->getHypervisor()->getName() === 'lxc') {
            $this->logger->debug("Set type to container to device ". $new_device->getName() .",".$new_device->getUuid());
            $new_device->setType('container');
        }
        else 
            $new_device->setType('vm');

        $entityManager = $this->getDoctrine()->getManager();
        $lab->setLastUpdated(new \DateTime());
        $entityManager->persist($new_device);
/*        foreach ($new_device->getNetworkInterfaces() as $network_int) {
            $this->logger->debug("Add Network interface".$network_int->getName());
            $new_network_inter=new NetworkInterface();
            $new_setting=new NetworkSettings();
            $new_setting=clone $network_int->getSettings();
            $entityManager->persist($new_setting);
            $new_network_inter->setSettings($new_setting);
            $new_network_inter->setName($new_device->getName());
            $new_network_inter->setIsTemplate(true);
            $new_device->addNetworkInterface($new_network_inter);
            $entityManager->persist($new_network_inter);
        }*/


        $entityManager->flush();
        $lab->addDevice($new_device);
        $entityManager->persist($lab);
        $entityManager->flush();
        $this->logger->debug("Add device in lab done");
    }

    /**
     * @Route("/admin/labs/{id<\d+>}/edit2", name="edit2_lab")
     */
    public function editAction(Request $request, int $id)
    {

        $lab = $this->labRepository->find($id);
        $this->logger->debug("Lab '".$lab->getName()."' is edited by : ".$this->getUser()->getUserIdentifier());

        if ( !is_null($lab) and (($lab->getAuthor()->getId() == $this->getUser()->getId() ) or $this->getUser()->isAdministrator()) )
        {
            $this->logger->info("Lab '".$lab->getName()."' is edited by : ".$this->getUser()->getUserIdentifier());
        

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
                $this->logger->warning("User ".$this->getUser()->getUserIdentifier()." has tried to edit the lab".$lab->getName());
            else 
                $this->logger->warning("User ".$this->getUser()->getUserIdentifier()." has tried to edit a lab");
            return $this->redirectToRoute('index');
        }
    }

    /**
     * @Route("/admin/labs/{id<\d+>}/edit", name="edit_lab")
     * @Route("/admin/labs_template/{id<\d+>}/edit", name="edit_lab_template")
     */
    public function edit2Action(Request $request, int $id)
    {

        $lab = $this->labRepository->find($id);

        if ( !is_null($lab) and (($lab->getAuthor()->getId() == $this->getUser()->getId() ) or $this->getUser()->isAdministrator()) )
        {
            $this->logger->info("Lab '".$lab->getName()."' is edited by : ".$this->getUser()->getUserIdentifier());
        

        if (!$lab) {
            throw new NotFoundHttpException("Lab " . $id . " does not exist.");
        };

        if ($request->getContentType() === 'json') {
            $lab = json_decode($request->getContent(), true);
        }

        return $this->render('editor.html.twig', ['id' => $id]);
    }
    else
        { 
            if (!is_null($lab))
                $this->logger->warning("User ".$this->getUser()->getUserIdentifier()." has tried to edit the lab".$lab->getName());
            else 
                $this->logger->warning("User ".$this->getUser()->getUserIdentifier()." has tried to edit a lab");
            return $this->redirectToRoute('index');
        }
    }

    /**
     * @Rest\Put("/api/labs/{id<\d+>}", name="api_edit_lab")
     */
    public function updateAction(Request $request, int $id)
    {
        $device=null;
        if (!$lab = $this->labRepository->find($id)) {
            throw new NotFoundHttpException("Lab " . $id . " does not exist.");
        }

        $labForm = $this->createForm(LabType::class, $lab);
        $labForm->handleRequest($request);

        $lab = json_decode($request->getContent(), true);
        $labForm->submit($lab, false);

        if ($labForm->isSubmitted() && $labForm->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            /** @var Lab $lab */
            $lab = $labForm->getData();
            $lab->setLastUpdated(new \DateTime());
            $entityManager->persist($lab);
            $entityManager->flush();

            $lab_name=$lab->getName();
            $this->logger->debug("API Lab updated: ".$lab_name);
            if (strstr($lab_name,"Sandbox_")) 
            { // Add Service container to provide IP address with DHCP
                $this->logger->debug("Update of Lab Sandbox detected: ".$lab_name);
                $srv_device=new Device();
                $device=$this->deviceRepository->findBy(['name' => 'Service', 'isTemplate' => true]);
                $this->logger->debug("Device Service found ? : ",$device);
                if (!is_null($device) && count($device)>0 ) {
                    $srv_device=$this->copyDevice($device[0],'Service_sandbox');
                    $srv_device->setIsTemplate(false);
                    $entityManager->persist($srv_device);
                    $this->logger->debug("Add additional device ".$srv_device->getName()." to lab ".$lab_name);
                    $this->adddeviceinlab($srv_device,$lab);
                }
            $entityManager->persist($lab);
            $entityManager->flush();
            }
            return $this->json($lab, 200, [], ['api_get_lab']);
        }

        /*if ($labForm->isSubmitted() && $labForm->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
           
            $lab = $labForm->getData();
            $lab->setLastUpdated(new \DateTime());
            
            if (strstr($lab_name,"Sandbox_")) 
            { // Add Service container to provide IP address with DHCP
                $this->logger->debug("Update of Lab Sandbox detected: ".$lab_name);
                $srv_device=new Device();
                $device=$this->deviceRepository->findBy(['name' => 'Service']);
                $srv_device=$device;
                $srv_device->IsTemplate(false);
                $entityManager->persist($srv_device);
                $this->adddeviceinlab($srv_device,$lab);
            }

            $entityManager->persist($lab);
            $entityManager->flush();

            return $this->json($lab, 200, [], ['api_get_lab']);
        }*/

        return $this->json($labForm, 200, [], ['api_get_lab']);
    }

    public function copyDevice(Device $device,string $name): Device
    {
        $newDevice = new Device();
        $newDevice->setName($name);
        $newDevice->setBrand($device->getBrand());
        $newDevice->setModel($device->getModel());
        $newDevice->setFlavor($device->getFlavor());
        $newDevice->setType($device->getType());
        $newDevice->setHypervisor($device->getHypervisor());
        $newDevice->setOperatingSystem($device->getOperatingSystem());
        $newDevice->setNbCpu($device->getNbCpu());
        $newDevice->setNbSocket($device->getNbSocket());
        $newDevice->setNbCore($device->getNbCore());
        $newDevice->setNbThread($device->getNbThread());
        $newDevice->setIsTemplate(true);

        $i=0;
        foreach ($device->getNetworkInterfaces() as $network_int) {
            $new_network_inter=new NetworkInterface();
            $new_setting=new NetworkSettings();
            $new_setting=clone $network_int->getSettings();
            
            $new_network_inter->setSettings($new_setting);
            $new_network_inter->setName("int".$i."_".$name);
            $i=$i+1;
            $new_network_inter->setIsTemplate(true);
            $newDevice->addNetworkInterface($new_network_inter);
        }

        foreach ($device->getControlProtocolTypes() as $control_protocol) {
            $newDevice->addControlProtocolType($control_protocol);
        }

        return $newDevice;
    }

    /**
     * @Rest\Put("/api/labs/test/{id<\d+>}", name="api_edit_lab_test")
     */
    public function updateActionTest(Request $request, int $id, LabBannerFileUploader $fileUploader)
    {
        $lab = $this->labRepository->find($id);

        $data = json_decode($request->getContent(), true); 

        $lab->setName($data['name']);
        $lab->setVersion($data['version']);
        $lab->setShortDescription($data['description']);
        $lab->setScripttimeout($data['scripttimeout']);
        if ($data['timer'] !== "" && $data['timer'] != "00:00:00") {
            $lab->setHasTimer(true);
            $lab->setTimer($data['timer']);
        }
        else {
            $lab->setHasTimer(false);
            $lab->setTimer(null);          
        }
        //$lab->setDescription($data['body']);

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($lab);
        $entityManager->flush();


        $this->logger->info("Lab named" . $lab->getName() . " modified");

        $response = new Response();
        $response->setContent(json_encode([
            'code' => 201,
            'status'=> 'success',
            'message' => 'Lab has been saved (60023).']));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Rest\Put("/api/labs/subject/{id<\d+>}", name="api_edit_lab_subject")
     */
    public function updateSubjectAction(Request $request, int $id)
    {
        $lab = $this->labRepository->find($id);

        $data = json_decode($request->getContent(), true);   

        $lab->setDescription($data['body']);

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($lab);
        $entityManager->flush();


        $this->logger->info("Lab named" . $lab->getName() . " modified");

        $response = new Response();
        $response->setContent(json_encode([
            'code' => 201,
            'status'=> 'success',
            'message' => 'Lab has been saved (60023).']));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Route("/admin/labs/{id<\d+>}/delete", name="delete_lab", methods="GET")
     * 
     * @Rest\Delete("/api/labs/{id<\d+>}", name="api_delete_lab")
     */
    public function deleteAction(Request $request, int $id, UserInterface $user,LabInstanceRepository $labInstanceRepository)
    {
        if (!$lab = $this->labRepository->find($id)) {
            throw new NotFoundHttpException();
        }

        $lab = $this->labRepository->find($id);
        
        if ( ($lab->getAuthor()->getId() == $this->getUser()->getId() ) or $this->getUser()->isAdministrator() )
        {
            $this->logger->debug("Lab deletes by : ".$this->getUser()->getUserIdentifier());

        $return=$this->delete_lab($lab);
        if ($return > 0) {
            $this->logger->error('This lab is used by an instance');
            $this->addFlash('danger','This lab '.$lab->getName().' is used by an instance');
            //return $this->redirectToRoute('labs', array('id' => $id));
            return $this->redirectToRoute('labs');
        }
        else {
            if ('json' === $request->getRequestFormat()) {
                return $this->json();
            }
            $this->logger->info($user->getUserIdentifier() . " has deleted lab \"" . $lab->getName()."\"");

            $this->addFlash('success',$lab->getName() . ' has been deleted.');
            return $this->redirectToRoute('labs');
        }
    }
    else 
    { 
        $this->logger->warning("User ".$this->getUser()->getUserIdentifier()." has tried to delete the lab".$lab->getName());
        return $this->redirectToRoute('index');
    }
    }

    public function delete_lab(Lab $lab){
        $entityManager = $this->getDoctrine()->getManager();
        $labInstanceRepository=$entityManager->getRepository(LabInstance::class);
        
        
        if ($labInstanceRepository->findByLab($this->labRepository->find($lab->getId()))) {
            // The lab is used by an instance
            return true;
        }
        else {
            foreach ($lab->getDevices() as $device) {
                foreach($device->getNetworkInterfaces() as $net_int) {
                    
                    $entityManager->remove($net_int);
                    $entityManager->flush();
                }
                $this->logger->debug("Delete device name: ".$device->getName());
                $entityManager->remove($device);
                //$entityManager->flush();
            }

            if (null !== $lab->getPictures()) {
                foreach($lab->getPictures() as $picture) {
                    $type = explode("image/",$picture->getType())[1];
                    if(is_file('/opt/remotelabz/assets/js/components/Editor2/images/pictures/lab'.$lab->getId().'-'.$picture->getName().'.'.$type)) {
                        unlink('/opt/remotelabz/assets/js/components/Editor2/images/pictures/lab'.$lab->getId().'-'.$picture->getName().'.'.$type);
                    }
                }
            }
            $entityManager->remove($lab);
            $entityManager->flush();
            return 0;
        }

    }

    /**
     * @Route("/admin/labs/import", name="import_lab", methods="POST")
     * 
     * @Rest\Post("/api/labs/import", name="api_import_lab")
     */
    public function importAction(Request $request, LabImporter $labImporter)
    {
        $json = $request->request->get('json');

        $data = $labImporter->import($json);

        return $this->redirectToRoute('show_lab', ['id' => $data]);
    }

    /**
     * @Route("/admin/labs/{id<\d+>}/export", name="export_lab", methods="GET")
     */
    public function exportAction(int $id, LabImporter $labImporter)
    {
        if (!$lab = $this->labRepository->find($id)) {
            throw new NotFoundHttpException();
        }
        
        $data = $labImporter->export($lab);

        $response = new Response($data);

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            'lab_'.$lab->getUuid().'.json'
        );

        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    /**
     * @Route("/labs/{id<\d+>}/banner", name="get_lab_banner", methods="GET")
     * @Rest\Get("/labs/{id<\d+>}/banner", name="api_get_lab_banner")
     */
    public function getBannerAction(Request $request, int $id, LabBannerFileUploader $fileUploader)
    {
        if (!$lab = $this->labRepository->find($id)) {
            throw new NotFoundHttpException();
        }

        if (null === $lab->getBanner()) {
            $fileName = 'default_banner.png';
            $file = $this->getParameter('directory.public.images').'/'.$fileName;
            return $this->file($file, $lab->getId(), ResponseHeaderBag::DISPOSITION_INLINE);
        } else {
            $fileName = $lab->getBanner();
            $file = $this->getParameter('directory.public.upload.lab.banner').'/'.$lab->getId().'/'.$fileName;
            return $this->file($file, $lab->getId(), ResponseHeaderBag::DISPOSITION_INLINE);
        }
    }

    /**
     * @Rest\Post("/api/labs/{id<\d+>}/banner", name="api_upload_lab_banner")
     */
    public function uploadBannerAction(Request $request, int $id, LabBannerFileUploader $fileUploader, UrlGeneratorInterface $router)
    {
        if (!$lab = $this->labRepository->find($id)) {
            throw new NotFoundHttpException();
        }

        $pictureFile = $request->files->get('banner');
        
        
        if ($pictureFile) {
            $fileUploader->setLab($lab);
            $pictureFileName = $fileUploader->upload($pictureFile);
            //$this->logger->debug("Add banner with picture file: ".$pictureFileName);
            $lab->setBanner($pictureFileName);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($lab);
            $entityManager->flush();

            return new JsonResponse(['url' => $router->generate('api_get_lab_banner', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL)]);
        }

        return new JsonResponse(null, 400);
    }

    /**
     * @Route("/labs/{id<\d+>}/connect", name="connect_internet")
     */
    public function connectLabInstanceAction(int $id, UserInterface $user)
    {
        $lab = $this->labRepository->find($id);
        $labInstanceTemp = $this->labInstanceRepository->findByUserAndLab($user, $lab);

        if (count($labInstanceTemp) > 0) {
            $labInstance = $labInstanceTemp[0];
        } else {
            $labInstance = null;
        }

        $this->connectLabInstance($labInstance);
        $this->addFlash('success', 'The lab ' . $lab->getName() . ' is connected to the internet.');

        return $this->redirectToRoute('show_lab', [
            'id' => $id
        ]);
    }

    /**
     * @Route("/labs/{id<\d+>}/disconnect", name="disconnect_internet")
     */
    public function disconnectLabInstanceAction(int $id, UserInterface $user)
    {
        $lab = $this->labRepository->find($id);
        $labInstanceTemp = $this->labInstanceRepository->findByUserAndLab($user, $lab);

        if (count($labInstanceTemp) > 0) {
            $labInstance = $labInstanceTemp[0];
        } else {
            $labInstance = null;
        }

        $this->disconnectLabInstance($labInstance);
        $this->addFlash('success', 'The lab ' . $lab->getName() . ' is disconnected from the internet.');

        return $this->redirectToRoute('show_lab', [
            'id' => $id
        ]);
    }

    /**
     * @Route("/labs/{id<\d+>}/interconnect", name="interconnect")
     */
    public function interconnectLabInstanceAction(int $id, UserInterface $user)
    {
        $lab = $this->labRepository->find($id);
        $labInstanceTemp = $this->labInstanceRepository->findByUserAndLab($user, $lab);

        if (count($labInstanceTemp) > 0) {
            $labInstance = $labInstanceTemp[0];
        } else {
            $labInstance = null;
        }

        $this->interconnectLabInstance($labInstance);
        $this->addFlash('success', 'The lab ' . $lab->getName() . ' is interconnected to other labs.');

        return $this->redirectToRoute('show_lab', [
            'id' => $id
        ]);
    }

    /**
     * @Route("/labs/{id<\d+>}/disinterconnect", name="disinterconnect")
     */
    public function disinterconnectLabInstanceAction(int $id, UserInterface $user)
    {
        $lab = $this->labRepository->find($id);
        $labInstanceTemp = $this->labInstanceRepository->findByUserAndLab($user, $lab);

        if (count($labInstanceTemp) > 0) {
            $labInstance = $labInstanceTemp[0];
        } else {
            $labInstance = null;
        }

        $this->disinterconnectLabInstance($labInstance);
        $this->addFlash('success', 'The lab ' . $lab->getName() . ' is dis-interconnected to other labs.');

        return $this->redirectToRoute('show_lab', [
            'id' => $id
        ]);
    }

    private function connectLabInstance(LabInstance $labInstance)
    {
        $client = new Client();
        $serializer = $this->container->get('jms_serializer');
        $workerUrl = $labInsatance->getWorketIp();
        $workerPort = (string) getenv('WORKER_PORT');

        $context = SerializationContext::create()->setGroups("start_lab");
        $serialized = $serializer->serialize($labInstance, 'json', $context);

        $url = "http://{$workerUrl}:{$workerPort}/lab/connect/internet";
        $headers = ['Content-Type' => 'application/json'];
        try {
            $response = $client->post($url, [
                'body' => $serialized,
                'headers' => $headers
            ]);
            $labInstance->setInternetConnected(true);
            $this->entityManager->persist($labInstance);
            $this->entityManager->flush();
        } catch (RequestException $exception) {
            dd($exception->getResponse()->getBody()->getContents());
        }
    }

    private function disconnectLabInstance(LabInstance $labInstance)
    {
        $this->logger->debug("Lab requested to disconnect from the Internet by user.", [
            "lab" => $labInstance->getLab()->getUuid(),
            "instance" => $labInstance->getUuid(),
            "user" => $this->getUser()->getEmail(),
        ]);

        $entityManager = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        $client = new Client();
        $workerUrl = $labInsatance->getWorketIp();
        $workerPort = (string) getenv('WORKER_PORT');

        $context = SerializationContext::create()->setGroups("start_lab");
        $serialized = $this->serializer->serialize($labInstance, 'json', $context);

        $url = "http://{$workerUrl}:{$workerPort}/lab/disconnect/internet";
        $headers = ['Content-Type' => 'application/json'];
        try {
            $response = $client->post($url, [
                'body' => $serialized,
                'headers' => $headers
            ]);
            $labInstance->setInternetConnected(false);
            $entityManager->persist($labInstance);
            $entityManager->flush();
        } catch (ServerException $exception) {
            throw new WorkerException("Cannot disconnect lab instance from the Internet.", $labInstance, $exception->getResponse());
            // dd($exception->getResponse()->getBody()->getContents(), $labXml, $lab->getInstances());
            // dd($exception->getResponse()->getBody()->getContents());
        }

        $this->logger->debug("Lab disconnected from the Internet by user.", [
            "lab" => $labInstance->getLab()->getUuid(),
            "instance" => $labInstance->getUuid(),
            "user" => $this->getUser()->getEmail(),
        ]);
    }

    private function interconnectLabInstance(LabInstance $labInstance)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        $client = new Client();
        $workerUrl = $labInsatance->getWorketIp();
        $workerPort = (string) getenv('WORKER_PORT');

        $context = SerializationContext::create()->setGroups("lab");
        $labXml = $this->serializer->serialize($labInstance, 'json', $context);

        $url = "http://{$workerUrl}:{$workerPort}/lab/interconnect";
        $headers = ['Content-Type' => 'application/json'];
        try {
            $response = $client->post($url, [
                'body' => $labXml,
                'headers' => $headers
            ]);
            $labInstance->setInterconnected(true);
            $entityManager->persist($labInstance);
            $entityManager->flush();
        } catch (RequestException $exception) {
            //dd($exception->getResponse()->getBody()->getContents(), $labXml, $lab->getInstances());
            dd($exception->getResponse()->getBody()->getContents());
        }
    }

    private function disinterconnectLabInstance(LabInstance $labInstance)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        $client = new Client();
        $serializer = $this->container->get('jms_serializer');

        $workerUrl = $labInsatance->getWorketIp();
        $workerPort = (string) getenv('WORKER_PORT');

        $context = SerializationContext::create()->setGroups("lab");
        $labXml = $serializer->serialize($labInstance, 'json', $context);

        $url = "http://{$workerUrl}:{$workerPort}/lab/disinterconnect";
        $headers = ['Content-Type' => 'application/json'];
        try {
            $response = $client->post($url, [
                'body' => $labXml,
                'headers' => $headers
            ]);
            $labInstance->setInterconnected(false);
            $entityManager->persist($labInstance);
            $entityManager->flush();
        } catch (RequestException $exception) {
            //dd($exception->getResponse()->getBody()->getContents(), $labXml, $lab->getInstances());
            dd($exception->getResponse()->getBody()->getContents());
        }
    }

    /**
     * @Route("/labs/close", name="close_lab", methods="GET")
     * 
     * @Rest\Delete("/api/labs/close", name="api_close_lab")
     */
    public function closeLab(
        Request $request,
        UserInterface $user,
        LabInstanceRepository $labInstanceRepository,
        LabRepository $labRepository,
        SerializerInterface $serializer)
    {
        $response = new Response();
        $response->setContent(json_encode(['status' => 'success']));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * 
     * @Rest\Put("/api/labs/{id<\d+>}/Lock", name="api_lock_lab")
     */
    public function lockLab(
        Request $request,
        UserInterface $user,
        LabRepository $labRepository,
        int $id)
    {
        $lab = $labRepository->find($id);

        $lab->setLocked(1);
        $response = new Response();
        $response->setContent(json_encode([
            'code' => 200,
            'status' => 'success',
            'message' => 'Lab has been saved (60023).'
        ]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * 
     * @Rest\Put("/api/labs/{id<\d+>}/Unlock", name="api_unlock_lab")
     */
    public function unlockLab(
        Request $request,
        UserInterface $user,
        LabRepository $labRepository,
        int $id)
    {
        $lab = $labRepository->find($id);

        $lab->setLocked(0);
        $response = new Response();
        $response->setContent(json_encode([
            'code' => 200,
            'status' => 'success',
            'message' => 'Lab has been saved (60023).'
        ]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

}
