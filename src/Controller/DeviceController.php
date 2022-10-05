<?php

namespace App\Controller;

use DateTime;
use App\Entity\Device;
use App\Entity\NetworkInterface;
use App\Entity\NetworkSettings;
use App\Entity\EditorData;
use App\Entity\ControlProtocolType;
use App\Form\DeviceType;
use App\Form\EditorDataType;
use App\Form\ControlProtocolTypeType;
use App\Repository\DeviceRepository;
use App\Repository\LabRepository;
use App\Repository\EditorDataRepository;
use App\Repository\ControlProtocolTypeRepository;
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
    private $controlProtocolTypeRepository;

    /** @var LoggerInterface $logger */
    private $logger;

    public function __construct(
        LoggerInterface $logger,
        LabRepository $labRepository,
        DeviceRepository $deviceRepository,
        SerializerInterface $serializerInterface,
        ControlProtocolTypeRepository $controlProtocolTypeRepository)
    {
        $this->deviceRepository = $deviceRepository;
        $this->labRepository = $labRepository;
        $this->logger = $logger;
        $this->serializer = $serializerInterface;
        $this->controlProtocolTypeRepository = $controlProtocolTypeRepository;
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
            foreach ($device->getControlProtocols() as $proto) {
                $proto->addDevice($device);
                $this->logger->debug($proto->getName());
            }
            $this->addNetworkInterface($device);
            $device->setHypervisor($device->getOperatingSystem()->getHypervisor());
            switch($device->getOperatingSystem()->getHypervisor()->getName()) {
                case 'lxc':
                    $device->setType('container');
                break;
                case 'qemu':
                    $device->setType('vm');
                break;
            }

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
        if (!$device = $this->deviceRepository->find($id)) {
            throw new NotFoundHttpException("Device " . $id . " does not exist.");
        }

        $this->logger->info("Device ".$device->getName()." modification asked by user ".$this->getUser()->getFirstname()." ".$this->getUser()->getName());
        $deviceForm = $this->createForm(DeviceType::class, $device, [
            'nb_network_interface' => count($device->getNetworkInterfaces())]
        );
        $deviceForm->handleRequest($request);

        //$this->logger->debug("Nb network interface:".$request->query->get('nb_network_interface'));

        foreach ($device->getControlProtocolTypes() as $proto) {
            $proto->removeDevice($device);
            //$this->logger->debug("Before submit: ".$device->getName()." has control protocol ".$proto->getName());
        }
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($device);
        $entityManager->flush();

        if ($request->getContentType() === 'json') {
            $device_json = json_decode($request->getContent(), true);
            
            $device_json['networkInterfaces']=count($device->getNetworkInterfaces());
            $controlProtocolType_json=$device_json['controlProtocolTypes'];
            $device_json['controlProtocolTypes']=array();
            foreach ($controlProtocolType_json as $controlProtoType){
                //array_push($device_json['controlProtocolTypes'],$this->controlProtocolTypeRepository->find($controlProtoType['id']));
                array_push($device_json['controlProtocolTypes'],$controlProtoType['id']);
            }
            /*$device_json=["id" => 225,
            "name"=>"Forti-DHCP","brand"=>"","model"=>"","operatingSystem"=>39,
            "hypervisor"=>7,"flavor"=>9,"nbCpu"=>"1","networkInterfaces"=>1,
            "controlProtocolTypes" => [ 3, 2]];*/

            //$this->logger->debug("before submit json :",$device_json);

            $deviceForm->submit($device_json, false);
        }

        if ($deviceForm->isSubmitted() && $deviceForm->isValid()) {
            /** @var Device $device */
            $nbNetworkInterface=count($device->getNetworkInterfaces());
            $wanted_nbNetworkInterface=$deviceForm->get("networkInterfaces")->getData();
            if (!is_int($wanted_nbNetworkInterface) || ($wanted_nbNetworkInterface > 19)) {
                if ($nbNetworkInterface < $wanted_nbNetworkInterface ){
                    for ($j=0; $j<($wanted_nbNetworkInterface-$nbNetworkInterface); $j++)
                        $this->addNetworkInterface($device);
                }
                elseif ($nbNetworkInterface > $wanted_nbNetworkInterface){
                    for ($j=0; $j<($nbNetworkInterface-$wanted_nbNetworkInterface); $j++)
                        $this->removeNetworkInterface($device);
                }
            } else {
                $this->logger->error("Value in interface number field in edit device form is not integer");
                $this->addFlash('error', 'Incorrect value.');
                return $this->redirectToRoute('show_device', ['id' => $id]);
            }
            //$this->logger->debug("Add for ".$device->getName()." nbcore ".$device->getNbCore());

            
            foreach ($device->getControlProtocolTypes() as $proto) {
                $proto->addDevice($device);
                $this->logger->debug("Add for ".$device->getName()." control protocol ".$proto->getName());
                //$this->logger->debug($device->getName()." has control protocol ".$proto->getName());
            }
            
            $device->setLastUpdated(new DateTime());
            //Check validity of cpu number with other parameters
            $total=1;
            if ($device->getNbCore()!=0)
                $total=$total*$device->getNbCore();
            if ($device->getNbSocket()!=0)
                $total=$total*$device->getNbSocket();
            if ($device->getNbThread()!=0)
                 $total=$total*$device->getNbThread();
            $this->logger->debug("Total CPU :".$total);
            
            if ($device->getNbCpu() < $total ) {
                $device->setNbCpu($total);
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($device);
            $entityManager->flush();

            if ('json' === $request->getRequestFormat()) {
                return $this->json($device, 200, [], ['api_get_device']);
            }
            $this->logger->info("Device ".$device->getName()." modification submitted");

            $this->addFlash('success', 'Device has been updated.');

            return $this->redirectToRoute('show_device', ['id' => $id]);
        } elseif ($deviceForm->isSubmitted() && !$deviceForm->isValid()) {
            $this->logger->error("Device ".$device->getName()."modification submitted but form not valid");
            $this->logger->error("Device form error ".$deviceForm->getErrors());
            $this->logger->error("Device form error ".$deviceForm["controlProtocolTypes"]->getErrors());
                foreach ($deviceForm as $fieldName => $formField) {
                    $this->logger->debug($fieldName." ".$formField->getErrors());
                }

            
        }

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
        $username=$user->getUsername();
        $device = $this->deviceRepository->find($id);

        $this->delete_device($device);
        $this->logger->info("Device ".$device->getName()." deleted by user ".$username);

        if ('json' === $request->getRequestFormat()) {
            return $this->json();
        }


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

        if ($device->getHypervisor()->getName() === "lxc") {
            $this->logger->info("Delete the device ".$device->getId());
        }
        $entityManager->flush();
        try {
            $entityManager->remove($device);
            $entityManager->flush();        
        $this->addFlash('success', $device->getName() . ' has been deleted.');

        }
        catch (ForeignKeyConstraintViolationException $e) {
            $this->logger->error("ForeignKeyConstraintViolationException".$e->getMessage());
            $this->addFlash('danger', 'This device is still used in some lab. Please delete them first.');

        }
    }

    private function addNetworkInterface(Device $device) {
        $i=count($device->getNetworkInterfaces());
        $networkInterface = new NetworkInterface();
        $networkInterface->setName($device->getName()."_net".($i+1));
        $networkInterface->setIsTemplate(true);
        $networkSettings = new NetworkSettings();
        $networkSettings->setName($networkInterface->getName()."_set".($i+1));
        $networkInterface->setSettings($networkSettings);
        $device->addNetworkInterface($networkInterface);
    }

    private function removeNetworkInterface(Device $device) {
        $entityManager = $this->getDoctrine()->getManager();
        $networkInterface = $device->getNetworkInterfaces()->last();
        $entityManager->remove($networkInterface);
        //$networkInterface->removeNetworkSetting($networkInterface->getSetting());
        $device->removeNetworkInterface($networkInterface);
        $entityManager->persist($device);
        $entityManager->flush();
    }

    /**
     * @Rest\Get("/api/device/{id<\d+>}/networkinterface", name="api_get_device_interface")
     */
    public function getNetworkInterface(Request $request, int $id)
    {
        $device = $this->deviceRepository->find($id);

        if (!$device) {
            throw new NotFoundHttpException("Device " . $id . " does not exist.");
        }

        if ('json' === $request->getRequestFormat()) {
            $i=count($device->getNetworkInterfaces());
            
        $response=new JsonResponse();
        $response->setData($i);
        return $response;
        }
        
        return new JsonResponse();
        
    }


}
