<?php

namespace App\Controller;

use App\Entity\Device;
use App\Form\DeviceType;

use App\Entity\EditorData;
use App\Service\FileUploader;
use Swagger\Annotations as SWG;
use FOS\RestBundle\Context\Context;
use App\Repository\DeviceRepository;
use JMS\Serializer\SerializerInterface;
use App\Repository\EditorDataRepository;
use Doctrine\Common\Collections\Criteria;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class DeviceController extends AbstractFOSRestController
{
    private $deviceRepository;

    public function __construct(DeviceRepository $deviceRepository)
    {
        $this->deviceRepository = $deviceRepository;
    }

    /**
     * @Route("/devices", name="devices")
     * 
     * @Rest\Get("/api/devices", name="api_devices")
     * 
     * @SWG\Parameter(
     *     name="search",
     *     in="query",
     *     type="string",
     *     description="Filter devices by name. All devices with a name containing this value will be shown."
     * )
     * 
     * @SWG\Response(
     *     response=200,
     *     description="Returns all existing devices",
     *     @SWG\Schema(
     *         type="array",
     *         @SWG\Items(ref=@Model(type=Lab::class))
     *     )
     * )
     * 
     * @SWG\Tag(name="Device")
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
            ])
        ;

        $devices = $this->deviceRepository->matching($criteria);

        $context = new Context();
        $context
            ->addGroup("device")
        ;

        $view = $this->view($devices->getValues())
            ->setTemplate("device/index.html.twig")
            ->setTemplateData([
                'devices' => $devices,
                'search' => $search
            ])
            ->setContext($context)
        ;

        return $this->handleView($view);
    }

    /**
     * @Route("/admin/devices/{id<\d+>}", name="show_device", methods="GET")
     * @Route("/devices/{id<\d+>}", name="show_device_public", methods="GET")
     * 
     * @Rest\Get("/api/devices/{id<\d+>}", name="api_get_device")
     */
    public function showAction(int $id)
    {
        $context = new Context();
        $context->addGroup("device");

        $view = $this->view($this->deviceRepository->find($id))
            ->setTemplate("device/view.html.twig")
            ->setContext($context)
        ;

        return $this->handleView($view);
    }

    /**
     * @Route("/admin/devices/new", name="new_device")
     * 
     * @Rest\Post("/api/devices", name="api_new_device")
     * 
     * @SWG\Parameter(
     *     name="device",
     *     in="body",
     *     @SWG\Schema(ref=@Model(type=Device::class, groups={"api"})),
     *     description="Device data."
     * )
     * 
     * @SWG\Response(
     *     response=201,
     *     description="Returns the newly created device.",
     *     @SWG\Schema(ref=@Model(type=Device::class))
     * )
     * 
     * @SWG\Tag(name="Device")
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

        $view = $this->view($deviceForm)
            ->setTemplate("device/new.html.twig")
            ->setTemplateData([
                "form" => $deviceForm->createView(),
                "data" => $device
            ])
        ;

        if ($deviceForm->isSubmitted() && $deviceForm->isValid()) {
            /** @var Device $device */
            $device = $deviceForm->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($device);
            $entityManager->flush();

            $this->addFlash('success', 'Device has been created.');

            $view->setLocation($this->generateUrl('devices'));
            $view->setStatusCode(201);
            $view->setData($device);
            $context = new Context();
            $context
                ->addGroup("device")
            ;
            $view->setContext($context);
        }

        return $this->handleView($view);
    }

    /**
     * @Route("/admin/devices/{id<\d+>}/edit", name="edit_device")
     * 
     * @Rest\Put("/api/devices/{id<\d+>}", name="api_edit_device")
     * 
     * @SWG\Parameter(
     *     name="device",
     *     in="body",
     *     @SWG\Schema(ref=@Model(type=Device::class, groups={"api"})),
     *     description="Device data."
     * )
     * 
     * @SWG\Response(
     *     response=200,
     *     description="Returns the newly edited device.",
     *     @SWG\Schema(ref=@Model(type=Device::class))
     * )
     * 
     * @SWG\Tag(name="Device")
     */
    public function updateAction(Request $request, int $id)
    {
        $device = $this->deviceRepository->find($id);

        if (null === $device) {
            throw new NotFoundHttpException("Device " . $id . " does not exist.");
        }

        $deviceForm = $this->createForm(DeviceType::class, $device);
        $deviceForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $device = json_decode($request->getContent(), true);
            $deviceForm->submit($device, false);
        } 

        $view = $this->view($deviceForm)
            ->setTemplate("device/new.html.twig")
            ->setTemplateData([
                "form" => $deviceForm->createView(),
                "data" => $device
            ])
        ;

        if ($deviceForm->isSubmitted() && $deviceForm->isValid()) {
            /** @var Device $device */
            $device = $deviceForm->getData();
            $device->setLastUpdated(new \DateTime());

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($device);
            $entityManager->flush();

            if ($request->getRequestFormat() === 'html') {
                $this->addFlash('info', 'Device has been edited.');
                $view->setLocation($this->generateUrl('show_device', ['id' => $id]));
            }

            $view->setStatusCode(200);
            $view->setData($this->deviceRepository->find($device->getId()));
            // $context = new Context();
            // $context
            //     ->addGroup("device")
            // ;
            // $view->setContext($context);
        }

        return $this->handleView($view);
    }

    /**
     * @Rest\Put("/api/devices/{id<\d+>}/editor-data", name="api_edit_device_editor_data")
     * 
     * @SWG\Parameter(
     *     name="device",
     *     in="body",
     *     @SWG\Schema(ref=@Model(type=Device::class, groups={"api"})),
     *     description="Device data."
     * )
     * 
     * @SWG\Response(
     *     response=200,
     *     description="Returns the newly edited device.",
     *     @SWG\Schema(ref=@Model(type=Device::class))
     * )
     * 
     * @SWG\Tag(name="Device")
     */
    public function updateEditorDataAction(Request $request, int $id)
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

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($deviceEditorData);
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
        $device = $this->deviceRepository->find($id);

        if (null === $device) {
            throw new NotFoundHttpException("Device " . $id . " does not exist.");
        }

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($device);
        $entityManager->flush();

        if ($request->getRequestFormat() === 'html') {
            $this->addFlash('success', $device->getName() . ' has been deleted.');
        }
        
        $view = $this->view()
            ->setLocation($this->generateUrl('devices'));
        ;

        return $this->handleView($view);
    }
}
