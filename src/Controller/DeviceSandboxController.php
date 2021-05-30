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

    /**
     * @Route("/admin/devices_sandbox", name="devices_sandbox")
     */
     public function indexAction(Request $request, SerializerInterface $serializer)
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
        $deviceArray = array();

        foreach ($devices as $device) {
            array_push($deviceArray, $device);
        }

        $deviceProps = [
            'user' => $this->getUser(),
            'devices' => $deviceArray
        ];

        return $this->render('device_sandbox/index.html.twig', [
            'devices' => $devices,
            'search' => $search,
            'props' => $serializer->serialize(
                $deviceProps,
                'json',
                SerializationContext::create()->setGroups(['user', 'device', 'lab'])
            )
        ]);
    }

    /**
     * @Route("/admin/devices_sandbox/{id<\d+>}", name="devices_sandbox_view")
     */
    public function viewAction(Request $request, int $id, UserInterface $user, LabInstanceRepository $labInstanceRepository, LabRepository $labRepository, SerializerInterface $serializer)
    {
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
            'isSandbox' => true
        ];

        return $this->render('device_sandbox/view.html.twig', [
            'lab' => $lab,
            'labInstance' => $userLabInstance,
            'deviceStarted' => $deviceStarted,
            'user' => $user,
            'props' => $serializer->serialize(
                $instanceManagerProps,
                'json',
                SerializationContext::create()->setGroups(['instance_manager', 'user', 'group_details', 'instances'])
            )
        ]);
    }
}