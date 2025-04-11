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
use App\Repository\FlavorRepository;
use App\Repository\OperatingSystemRepository;
use App\Repository\HypervisorRepository;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;

class StartupConfigController extends Controller
{
    private $deviceRepository;
    private $labRepository;
    private $controlProtocolTypeRepository;
    private $hypervisorRepository;
    private $flavorRepository;
    private $operatingSystemRepository;

    /** @var LoggerInterface $logger */
    private $logger;

    public function __construct(
        LoggerInterface $logger,
        LabRepository $labRepository,
        DeviceRepository $deviceRepository,
        SerializerInterface $serializerInterface,
        ControlProtocolTypeRepository $controlProtocolTypeRepository,
        HypervisorRepository $hypervisorRepository,
        OperatingSystemRepository $operatingSystemRepository,
        FlavorRepository $flavorRepository,
        EntityManagerInterface $entityManager)
    {
        $this->deviceRepository = $deviceRepository;
        $this->labRepository = $labRepository;
        $this->logger = $logger;
        $this->serializer = $serializerInterface;
        $this->controlProtocolTypeRepository = $controlProtocolTypeRepository;
        $this->flavorRepository = $flavorRepository;
        $this->operatingSystemRepository = $operatingSystemRepository;
        $this->hypervisorRepository = $hypervisorRepository;
        $this->entityManager = $entityManager;
    }

    
    /*public function indexAction(Request $request, int $id)
    {
        $devices = $this->deviceRepository->findByLab($id);
        $data = [];
        foreach($devices as $device){
            if (strlen($device->getConfigData()) == 0) {
                $len = 0;
            }
            else {
                $len = 1;
            }
            $data[$device->getId()] = [
                "config"=>$device->getConfig(),
                "name"=> $device->getName(),
                "icon"=> $device->getIcon(),
                "len"=> $len
            ];
        }

        $response = new Response();
        $response->setContent(json_encode([
            'code'=> 200,
            'status'=>'success',
            'message' => 'Successfully listed startup-configs (60055).',
            'data' => $data]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }*/

    
    /*public function showAction(Request $request, int $id, int $labId)
    {
        $device = $this->deviceRepository->find($id);

            $data = [
                "id"=>$device->getId(),
                "name"=> $device->getName(),
                "data"=> $device->getConfigData()
            ];

        $response = new Response();
        $response->setContent(json_encode([
            'code'=> 200,
            'status'=>'success',
            'message' => 'Got startup-config (60057).',
            'data' => $data]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }*/

    
    /*public function updateAction(Request $request, int $id, int $labId)
    {
        $device = $this->deviceRepository->find($id);

        $data = json_decode($request->getContent(), true);   

        $device->setConfigData($data['data']);

        $entityManager = $this->entityManager;
        $entityManager->flush();

        $this->logger->info("Device named" . $device->getName() . " modified");

        $response = new Response();
        $response->setContent(json_encode([
            'code' => 201,
            'status'=> 'success',
            'message' => 'Lab has been saved (60023).']));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }*/
    
}
