<?php

namespace App\Controller;

use App\Entity\Device;
use App\Form\DeviceType;
use App\Utils\RequestType;
use App\Service\FileUploader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class DeviceController extends AppController
{
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

        if ($this->getRequestedFormat($request) === RequestType::JsonRequest) {
            return $this->json($data);
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
     */
    public function showAction(Request $request, $id)
    {
        $repository = $this->getDoctrine()->getRepository('App:Device');

        $data = $repository->find($id);

        if ($this->getRequestedFormat($request) === RequestType::JsonResponse) {
            return $this->json($data);
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
        $launchScript = $device->getLaunchScript();

        $deviceForm = $this->createForm(DeviceType::class, $device);
        $deviceForm->handleRequest($request);
        
        if ($deviceForm->isSubmitted() && $deviceForm->isValid()) {
            $device = $deviceForm->getData();

            if ($device->getLaunchScript() != null && $device->getLaunchScript() != $launchScript) {
                $file = $device->getLaunchScript();
                $fileName = $fileUploader->upload($file);

                $device->setLaunchScript($fileName);
            } else {
                $device->setLaunchScript($launchScript);
            }
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($device);
            $entityManager->flush();
            
            $this->addFlash('success', 'Device has been edited.');

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
     * @Route("/admin/devices/{id<\d+>}", name="delete_device", methods="DELETE")
     */
    public function deleteAction(Request $request, $id)
    {
        $repository = $this->getDoctrine()->getRepository('App:Device');
            
        $data = null;
        $status = 200;
            
        $device = $repository->find($id);
            
        if ($device == null) {
            $status = 404;
        } else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($device);
            $em->flush();
                
            $data = [
                'message' => 'Device has been deleted.'
            ];
        }
            
        if ($this->getRequestedFormat($request) === RequestType::JsonRequest) {
            return $this->json($data, $status);
        }

        return $this->redirectToRoute('devices');
    }
}
