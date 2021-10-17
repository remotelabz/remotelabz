<?php

namespace App\Controller;

use IPTools;
use App\Entity\Lab;
use GuzzleHttp\Psr7;
use App\Form\LabType;
use App\Entity\Device;
use GuzzleHttp\Client;
use App\Entity\Network;
use App\Entity\Activity;
use App\Form\DeviceType;
use App\Entity\LabInstance;
use Psr\Log\LoggerInterface;
use App\Entity\DeviceInstance;
use App\Repository\LabRepository;
use App\Exception\WorkerException;
use App\Repository\UserRepository;
use FOS\RestBundle\Context\Context;
use App\Repository\DeviceRepository;
use Remotelabz\Message\Message\InstanceActionMessage;
use App\Repository\ActivityRepository;
use JMS\Serializer\SerializerInterface;
use App\Entity\NetworkInterfaceInstance;
use App\Exception\NotInstancedException;
use JMS\Serializer\SerializationContext;
use App\Repository\LabInstanceRepository;
use Doctrine\Common\Collections\Criteria;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\RequestException;
use App\Exception\AlreadyInstancedException;
use App\Repository\DeviceInstanceRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\NetworkInterfaceRepository;
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


class LabController extends Controller
{
    private $workerServer;

    private $workerPort;

    private $workerAddress;

    /** @var LoggerInterface $logger */
    private $logger;

    /** @var LabRepository $labRepository */
    private $labRepository;

    private $serializer;

    public function __construct(
        LoggerInterface $logger,
        LabRepository $labRepository,
        SerializerInterface $serializerInterface)
    {
        $this->workerServer = (string) getenv('WORKER_SERVER');
        $this->workerPort = (int) getenv('WORKER_PORT');
        $this->workerAddress = $this->workerServer . ":" . $this->workerPort;
        $this->logger = $logger;
        $this->labRepository = $labRepository;
        $this->serializer = $serializerInterface;
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
        $author = $request->query->get('author', 0);
        $limit = $request->query->get('limit', 10);
        $page = $request->query->get('page', 1);
        $orderBy = $request->query->get('order_by', 'lastUpdated');
        $sortDirection = $request->query->get('sort_direction', Criteria::DESC);

        $criteria = Criteria::create()
            ->where(Criteria::expr()->contains('name', $search));

        if ($author > 0) {
            $criteria->andWhere(Criteria::expr()->eq('author', $userRepository->find($author)));
        }

        $criteria
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
            return $this->json($lab, 200, [], [$request->get('_route')]);
        }

        $instanceManagerProps = [
            'user' => $this->getUser(),
            'labInstance' => $userLabInstance,
            'lab' => $lab,
            'isJitsiCallEnabled' => (bool) $this->getParameter('app.enable_jitsi_call'),
            'isSandbox' => false
        ];

        return $this->render('lab/view.html.twig', [
            'lab' => $lab,
            'labInstance' => $userLabInstance,
            'deviceStarted' => $deviceStarted,
            'user' => $user,
            'props' => $serializer->serialize(
                $instanceManagerProps,
                'json',
                SerializationContext::create()->setGroups(['api_get_lab', 'api_get_user', 'api_get_group', 'api_get_lab_instance', 'api_get_device_instance'])
            )
        ]);
    }

    /**
     * @Route("/labs/new", name="new_lab")
     * 
     * @Rest\Post("/api/labs", name="api_new_lab")
     */
    public function newAction(Request $request)
    {
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
        
        $this->logger->info($this->getUser()->getUsername() . " creates lab named " . $lab->getName());

        $entityManager->persist($lab);
        $entityManager->flush();
      

        if ('json' === $request->getRequestFormat()) {
            return $this->json($lab, 200, [], ['api_get_lab']);
        }

        return $this->redirectToRoute('edit_lab', [
            'id' => $lab->getId()
        ]);
    }

    /**
     * @Rest\Post("/api/labs/{id<\d+>}/devices", name="api_add_device_lab")
     */
    public function addDeviceAction(Request $request, int $id, NetworkInterfaceRepository $networkInterfaceRepository)
    {
        $device = new Device();
        $deviceForm = $this->createForm(DeviceType::class, $device);
        $deviceForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $device = json_decode($request->getContent(), true);
            // fetch network interfaces to copy them later
            $networkInterfaces = $device['networkInterfaces'];
            $deviceForm->submit($device);
        }

        if ($deviceForm->isSubmitted() && $deviceForm->isValid()) {
            /** @var Device $device */
            $device = $deviceForm->getData();
            $entityManager = $this->getDoctrine()->getManager();
            $lab = $this->labRepository->find($id);
            $lab->setLastUpdated(new \DateTime());

            $entityManager->persist($device);
            $lab->addDevice($device);
            $entityManager->persist($lab);
            $entityManager->flush();

            return $this->json($device, 201, [], ['api_get_device']);
        }

        return $this->json($deviceForm, 200, [], ['api_get_device']);
    }

    /**
     * @Route("/admin/labs/{id<\d+>}/edit", name="edit_lab")
     */
    public function editAction(Request $request, int $id)
    {
        $lab = $this->labRepository->find($id);

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

    /**
     * @Rest\Put("/api/labs/{id<\d+>}", name="api_edit_lab")
     */
    public function updateAction(Request $request, int $id)
    {
        if (!$lab = $this->labRepository->find($id)) {
            throw new NotFoundHttpException("Lab " . $id . " does not exist.");
        }

        $labForm = $this->createForm(LabType::class, $lab);
        $labForm->handleRequest($request);

        $lab = json_decode($request->getContent(), true);
        $labForm->submit($lab, false);

        if ($labForm->isSubmitted() && $labForm->isValid()) {
            /** @var Lab $lab */
            $lab = $labForm->getData();
            $lab->setLastUpdated(new \DateTime());

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($lab);
            $entityManager->flush();

            return $this->json($lab, 200, [], ['api_get_lab']);
        }

        return $this->json($labForm, 200, [], ['api_get_lab']);
    }

    /**
     * @Route("/admin/labs/{id<\d+>}/delete", name="delete_lab", methods="GET")
     * 
     * @Rest\Delete("/api/labs/{id<\d+>}", name="api_delete_lab")
     */
    public function deleteAction(Request $request, int $id, UserInterface $user)
    {
        if (!$lab = $this->labRepository->find($id)) {
            throw new NotFoundHttpException();
        }

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($lab);
        $entityManager->flush();

        $this->logger->info($user->getUsername() . " deleted lab " . $lab->getName());

        if ('json' === $request->getRequestFormat()) {
            return $this->json();
        }

        $this->addFlash('success', $lab->getName() . ' has been deleted.');

        return $this->redirectToRoute('labs');
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
        $workerUrl = (string) getenv('WORKER_SERVER');
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
        $workerUrl = (string) getenv('WORKER_SERVER');
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
        $workerUrl = (string) getenv('WORKER_SERVER');
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

        $workerUrl = (string) getenv('WORKER_SERVER');
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
}
