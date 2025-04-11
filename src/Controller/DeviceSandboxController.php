<?php

namespace App\Controller;

use App\Entity\Lab;
use App\Entity\Device;

use App\Repository\LabRepository;
use App\Repository\DeviceRepository;
use App\Repository\LabInstanceRepository;
use App\Repository\DeviceInstanceRepository;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Psr\Log\LoggerInterface;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class DeviceSandboxController extends Controller
{
    /** @var LabRepository $labRepository */
    private $labRepository;
    private $deviceRepository;

    private $logger;

    private $serializer;

    public function __construct(LoggerInterface $logger, LabRepository $labRepository, DeviceRepository $deviceRepository, SerializerInterface $serializerInterface)
    {
        $this->logger = $logger;
        $this->labRepository = $labRepository;
        $this->serializer = $serializerInterface;
        $this->deviceRepository = $deviceRepository;
    }

    #[Route(path: '/admin/sandbox', name: 'sandbox')]
     public function indexAction(Request $request, SerializerInterface $serializer)
    {
        $search = $request->query->get('search', '');
        $template = $request->query->get('template', true);

        $criteria = Criteria::create()
            ->where(Criteria::expr()->contains('name', $search))
            ->andWhere(Criteria::expr()->eq('isTemplate', $template))
            ->andWhere(Criteria::expr()->neq('type', 'switch'))
            ->andWhere(Criteria::expr()->eq('virtuality', true))
            ->orderBy([
                'name' => Criteria::ASC
            ]);

        $devices = $this->deviceRepository->matching($criteria);
        $labs = $this->labRepository->findBy(['isTemplate' => true]);
        $deviceArray = array();
        $labArray = array();

        foreach ($devices as $device) {
            if (count($device->getLabs()) == 0) {
                array_push($deviceArray, $device);
            }
        }
        foreach ($labs as $lab) {
            array_push($labArray, $lab);
        }

        $deviceProps = [
            'user' => $this->getUser(),
            'devices' => $deviceArray,
            'labs' => $labArray
        ];
        
        return $this->render('device_sandbox/index.html.twig', [
            'devices' => $devices,
            'labs' => $labs,
            'search' => $search,
            'props' => $serializer->serialize(
                $deviceProps,
                'json',
                SerializationContext::create()->setGroups(['api_get_user', 'api_get_device', 'api_get_lab'])
            )
        ]);
    }

    #[Route(path: '/admin/sandbox/{id<\d+>}', name: 'sandbox_view')]
    public function viewAction(Request $request, int $id, UserInterface $user, LabInstanceRepository $labInstanceRepository, LabRepository $labRepository, SerializerInterface $serializer)
    {
        //$this->logger->debug("Request in DeviceSandboxCtrl viewAction: ".$request);

        $lab = $labRepository->find($id);

        if (!$lab) {
            throw new NotFoundHttpException("Sandbox Lab does not exist.");
        }

        $userLabInstance = $labInstanceRepository->findByUserAndLab($user, $lab);
        $deviceStarted = [];

        foreach ($lab->getDevices()->getValues() as $device) {
            $deviceStarted[$device->getId()] =  false;

            if ($userLabInstance && $userLabInstance->getUserDeviceInstance($device)) {
                $deviceStarted[$device->getId()] = true;
            }
        }

        $instanceManagerProps = [
            'user' => $this->getUser(),
            'labInstance' => $userLabInstance,
            'lab' => $lab,
            'isSandbox' => true,
            "hasBooking" => false
        ];
        //$this->logger->debug("instanceManagerProps from DeviceSandboxCtrl: ", $instanceManagerProps);

        preg_match("/^Sandbox_(Lab).*$/",$lab->getName(),$result);
        //$this->logger->debug("Sandbox lab: ",$result);
        if ($result != null)
            $sandboxlab=true;
        else
            $sandboxlab=false;

        return $this->render('device_sandbox/view.html.twig', [
            'lab' => $lab,
            'labInstance' => $userLabInstance,
            'deviceStarted' => $deviceStarted,
            'user' => $user,
            'sandboxlab' => $sandboxlab,
            'props' => $serializer->serialize(
                $instanceManagerProps,
                'json',
                //SerializationContext::create()->setGroups(['api_get_device_instance','api_get_lab_instance', 'api_get_user', 'group_details', 'instances'])
                //SerializationContext::create()->setGroups(['api_get_lab', 'api_get_user', 'api_get_group', 'api_get_lab_instance', 'api_get_device_instance','sandbox'])
                SerializationContext::create()->setGroups(['sandbox'])
            )
        ]);
    }
}