<?php

namespace App\Controller;

use DateTime;
use App\Entity\Device;
use App\Entity\NetworkInterface;
use App\Entity\NetworkSettings;

use App\Form\DeviceType;
use App\Entity\EditorData;
use App\Form\EditorDataType;
use App\Repository\DeviceRepository;
use App\Repository\LabRepository;
use App\Repository\EditorDataRepository;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;


class DeviceController extends Controller
{
    private $deviceRepository;
    private $labRepository;

    /** @var LoggerInterface $logger */
    private $logger;

    public function __construct(
        LoggerInterface $logger,
        LabRepository $labRepository,
        DeviceRepository $deviceRepository,
        SerializerInterface $serializerInterface)
    {
        $this->deviceRepository = $deviceRepository;
        $this->labRepository = $labRepository;
        $this->logger = $logger;
        $this->serializer = $serializerInterface;
    }

    /**
     * @Route("/admin/devices", name="devices")
     * 
     * @Rest\Get("/api/devices", name="api_devices")
     */
    public function indexAction(Request $request)
    {
        $search = $request->query->get('search', '');
        $template = $request->query->get('template', true);

        $criteria = Criteria::create()
            ->where(Criteria::expr()->contains('name', $search))
            ->andWhere(Criteria::expr()->eq('isTemplate', $template))
            ->orderBy([
                'id' => Criteria::DESC
            ]);

        $devices = $this->deviceRepository->matching($criteria);

        if ('json' === $request->getRequestFormat()) {
            return $this->json($devices->getValues(), 200, [], ['api_get_device']);
        }

        return $this->render('device/index.html.twig', [
            'devices' => $devices,
            'search' => $search
        ]);
    }

    /**
     * @Route("/admin/devices/{id<\d+>}", name="show_device", methods="GET")
     * @Route("/devices/{id<\d+>}", name="show_device_public", methods="GET")
     * 
     * @Rest\Get("/api/devices/{id<\d+>}", name="api_get_device")
     */
    public function showAction(Request $request, int $id)
    {
        $device = $this->deviceRepository->find($id);

        if (!$device) {
            throw new NotFoundHttpException("Device " . $id . " does not exist.");
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($device, 200, [], ['api_get_device']);
        }

        return $this->render('device/view.html.twig', ['device' => $device]);
    }

    /**
     * @Route("/admin/devices/new", name="new_device")
     * 
     * @Rest\Post("/api/devices", name="api_new_device")
     */
    public function newAction(Request $request)
    {
        $device = new Device();
        $deviceForm = $this->createForm(DeviceType::class, $device);
        $deviceForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $device = json_decode($request->getContent(), true);
            $deviceForm->submit($device);
        }

        if ($deviceForm->isSubmitted() && $deviceForm->isValid()) {
            /** @var Device $device */
            $device = $deviceForm->getData();

            $networkInterface = new NetworkInterface();
            $networkInterface->setName($device->getName()."_net");
            $networkInterface->setIsTemplate(true);
            $networkSettings = new NetworkSettings();
            $networkSettings->setName($networkInterface->getName()."_set");
            $networkInterface->setSettings($networkSettings);
            $device->addNetworkInterface($networkInterface);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($device);
            $entityManager->flush();

            if ('json' === $request->getRequestFormat()) {
                return $this->json($device, 201, [], ['api_get_device']);
            }

            $this->addFlash('success', 'Device has been created.');

            return $this->redirectToRoute('devices');
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($deviceForm, 200, [], ['api_get_device']);
        }

        return $this->render('device/new.html.twig', [
            'form' => $deviceForm->createView(),
            'data' => $device
        ]);
    }

    /**
     * @Route("/admin/devices/{id<\d+>}/edit", name="edit_device")
     * 
     * @Rest\Put("/api/devices/{id<\d+>}", name="api_edit_device")
     */
    public function updateAction(Request $request, int $id)
    {
        $this->logger->info("Device modification asked by user ");
        if (!$device = $this->deviceRepository->find($id)) {
            throw new NotFoundHttpException("Device " . $id . " does not exist.");
        }

        $deviceForm = $this->createForm(DeviceType::class, $device);
        $deviceForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $device_json = json_decode($request->getContent(), true);
            $deviceForm->submit($device_json, false);
        }

        if ($deviceForm->isSubmitted() && $deviceForm->isValid()) {
            /** @var Device $device */
            
            $device = $deviceForm->getData();
            $device->setLastUpdated(new DateTime());
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($device);
            $entityManager->flush();

            if ('json' === $request->getRequestFormat()) {
                return $this->json($device, 200, [], ['api_get_device']);
            }

            $this->addFlash('success', 'Device has been updated.');

            return $this->redirectToRoute('show_device', ['id' => $id]);
        } elseif ($deviceForm->isSubmitted() && !$deviceForm->isValid())
            $this->logger->info("Device modification submitted");
            else
            $this->logger->info("Device modification aborted, form not valid");

        if ('json' === $request->getRequestFormat()) {
            return $this->json($device, 200, [], ['api_get_device']);
        }

        return $this->render('device/new.html.twig', [
            'form' => $deviceForm->createView(),
            'data' => $device
        ]);
    }

    /**
     * @Rest\Put("/api/devices/{id<\d+>}/editor-data", name="api_edit_device_editor_data")
     */
    public function updateEditorDataAction(Request $request, int $id, EditorDataRepository $editorDataRepository)
    {
        /** @var EditorDataRepository $editorDataRepository */
        $editorDataRepository = $this->getDoctrine()->getRepository(EditorData::class);
        $deviceEditorData = $editorDataRepository->findByDeviceId($id);
        //$device = $this->deviceRepository->find($id);

        // if (! ($deviceEditorData instanceof EditorData)) {
        //     throw new NotFoundHttpException("Device " . $id . " does not exist.");
        // }

        if ($request->getContentType() === 'json') {
            $editorData = json_decode($request->getContent(), true);

            if (!$editorData) {
                throw new BadRequestHttpException("Incorrect JSON.");
            }
        }

        //$deviceEditorData = $device->getEditorData();
        if (array_key_exists('x', $editorData)) {
            $deviceEditorData->setX($editorData['x']);
        }
        if (array_key_exists('y', $editorData)) {
            $deviceEditorData->setY($editorData['y']);
        }

        $lab = $deviceEditorData->getDevice()->getLabs()[0];
        $lab->setLastUpdated(new \DateTime());

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($deviceEditorData);
        $entityManager->persist($lab);
        $entityManager->flush();

        return new JsonResponse();
    }

    /**
     * @Route("/admin/devices/{id<\d+>}/delete", name="delete_device", methods="GET")
     * 
     * @Rest\Delete("/api/devices/{id<\d+>}", name="api_delete_device")
     */
    public function deleteAction(Request $request, int $id)
    {
        $user = $this->get('security.token_storage')->getToken()->getUser();
        $username=$user->getUserIdentifier();
        $device = $this->deviceRepository->find($id);

        $this->delete_device($device);
        $this->logger->info("Device ".$device->getName()." deleted by user ".$username);

        if ('json' === $request->getRequestFormat()) {
            return $this->json();
        }

        $this->addFlash('success', $device->getName() . ' has been deleted.');

        return $this->redirectToRoute('devices');
    }

    public function delete_device(Device $device) {

        if (!$device = $this->deviceRepository->find($device->getId())) {
            throw new NotFoundHttpException();
        }

        $entityManager = $this->getDoctrine()->getManager();

        foreach ($device->getNetworkInterfaces() as $networkInterface) {
            $entityManager->remove($networkInterface);
        }

// TODO : if hypervisor === LXC -> send a delete msg to worker to delete the container
        if ($device->getHypervisor() === "LXC") {
            $this->logger->info("Delete the device ".$device->getId());
        }

        $entityManager->flush();
        $entityManager->remove($device);
        $entityManager->flush();
    }
}
