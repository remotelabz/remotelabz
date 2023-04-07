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
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Response;


class EditorController extends Controller
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
        ControlProtocolTypeRepository $controlProtocolTypeRepository
        /*HypervisorRepository $hypervisorRepository,
        OperatingSystemRepository $operatingSystemRepository,
        FlavorRepository $flavorRepository*/)
    {
        $this->deviceRepository = $deviceRepository;
        $this->labRepository = $labRepository;
        $this->logger = $logger;
        $this->serializer = $serializerInterface;
        $this->controlProtocolTypeRepository = $controlProtocolTypeRepository;
        /*$this->flavorRepository = $flavorRepository;
        $this->operatingSystemRepository = $operatingSystemRepository;
        $this->hypervisorRepository = $hypervisorRepository;*/
    }

    /**
     * @Route("/jj/", name="editor2")
     * 
     */
    public function indexAction(Request $request)
    {

        return $this->render('lab.html.twig');
    }

}