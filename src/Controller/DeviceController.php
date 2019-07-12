<?php

namespace App\Controller;

use App\Entity\Device;
use App\Form\DeviceType;

use App\Service\FileUploader;
use App\Repository\DeviceRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class DeviceController extends AppController
{
    private $deviceRepository;

    public function __construct(DeviceRepository $deviceRepository)
    {
        $this->deviceRepository = $deviceRepository;
    }

    /**
     * @Route("/admin/devices", name="devices")
     */
    public function indexAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository('App:Device');

        $search = $request->query->get('search', '');
        
        if ($search !== '') {
            $data = $repository->findByName($search);
        } else {
            $data = $repository->findAll();
        }

        if ($this->getRequestedFormat($request) === JsonRequest::class) {
            return $this->renderJson($data);
        }
        
        return $this->render('device/index.html.twig', [
            'devices' => $data,
            'search' => $search
        ]);
    }

    /**
     * @Route("/admin/devices/{id<\d+>}.{_format}",
     *  defaults={"_format": "html"},
     *  requirements={"_format": "html|json"},
     *  name="show_device",
     *  methods="GET")
     * @Route("/devices/{id<\d+>}.{_format}",
     *  defaults={"_format": "html"},
     *  requirements={"_format": "html|json"},
     *  name="show_device_public",
     *  methods="GET")
     * )
     */
    public function showAction(Request $request, $id)
    {
        $repository = $this->getDoctrine()->getRepository('App:Device');

        $data = $repository->find($id);

        if (null === $data) {
            throw new NotFoundHttpException();
        }

        if ($this->getRequestedFormat($request) === JsonRequest::class) {
            return $this->renderJson($data);
        }
        
        return $this->render('device/view.html.twig', [
            'device' => $data
        ]);
    }

    /**
     * @Route("/admin/devices/new", name="new_device")
     */
    public function newAction(Request $request, FileUploader $fileUploader)
    {
        $device = new Device();
        $deviceForm = $this->createForm(DeviceType::class, $device);
        $deviceForm->handleRequest($request);
        
        if ($deviceForm->isSubmitted() && $deviceForm->isValid()) {
            $device = $deviceForm->getData();

            if ($device->getLaunchScript() != null) {
                $file = $device->getLaunchScript();
                $fileName = $fileUploader->upload($file);

                $device->setLaunchScript($fileName);
            }
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($device);
            $entityManager->flush();
            
            $this->addFlash('success', 'Device has been created.');

            return $this->redirectToRoute('devices');
        }
        
        return $this->render('device/new.html.twig', [
            'deviceForm' => $deviceForm->createView(),
        ]);
    }

    /**
     * @Route("/admin/devices/{id<\d+>}/edit", name="edit_device", methods={"GET", "POST"})
     */
    public function editAction(Request $request, $id, FileUploader $fileUploader)
    {
        $repository = $this->getDoctrine()->getRepository('App:Device');

        $device = $repository->find($id);

        if (null === $device) {
            throw new NotFoundHttpException();
        }

        $launchScript = $device->getLaunchScript();

        $deviceForm = $this->createForm(DeviceType::class, $device);
        $deviceForm->handleRequest($request);
        
        if ($deviceForm->isSubmitted() && $deviceForm->isValid()) {
            /** @var Device $device */
            $device = $deviceForm->getData();

            if ($device->getLaunchScript() != null && $device->getLaunchScript() != $launchScript) {
                $file = $device->getLaunchScript();
                $fileName = $fileUploader->upload($file);

                $device->setLaunchScript($fileName);
            } else {
                $device->setLaunchScript($launchScript);
            }

            if ($device->getInstances()->count() > 0) {
                $this->addFlash('danger', "You cannot edit a device which still has instances.");
            } else {
                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->persist($device);
                $entityManager->flush();
                
                $this->addFlash('success', 'Device has been edited.');
            }

            return $this->redirectToRoute('show_device', [
                'id' => $id
            ]);
        }
        
        return $this->render('device/new.html.twig', [
            'deviceForm' => $deviceForm->createView(),
            'id' => $id,
            'name' => $device->getName()
        ]);
    }
        
    /**
     * @Route("/admin/devices/{id<\d+>}/delete", name="delete_device", methods="GET")
     */
    public function deleteAction(int $id)
    {
        $device = $this->deviceRepository->find($id);

        if (null === $device) {
            throw new NotFoundHttpException();
        }

        $deviceInstances = $device->getInstances();

        if ($deviceInstances->count() > 0) {
            $this->addFlash('danger', "You cannot delete a device which still has instances.");

            return $this->redirectToRoute('show_device', [ 'id' => $id ]);
        } else {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($device);
            $entityManager->flush();

            $this->addFlash('success', $device->getName() . ' has been deleted.');
        }

        return $this->redirectToRoute('devices');
    }
}
