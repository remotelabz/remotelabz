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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class DeviceSandboxController extends Controller
{
    /** @var LabRepository $labRepository */
    private $labRepository;

    private $serializer;

    public function __construct(LabRepository $labRepository, SerializerInterface $serializerInterface)
    {
        $this->labRepository = $labRepository;
        $this->serializer = $serializerInterface;
        /*
        $this->deviceRepository = $deviceRepository;
        $this->instanceRepository = $instanceRepository;
        $this->fakelab = '{
            "name": "Sandbox Lab",
            "devices": [],
            "author": {
                "name": "Florent Administrator",
                "email": "root@localhost",
                "lastName": "Administrator",
                "firstName": "Florent",
                "enabled": true,
                "uuid": "e1eb8089-2523-4cd2-8df6-2731cc70bff7"
            },
            "uuid": "b532f955-d308-4aa5-8d31-a8b2b395c87a",
            "createdAt": "2021-05-07T13:21:03+00:00",
            "lastUpdated": "2021-05-07T13:21:18+00:00",
            "isInternetAuthorized": false
        }';
        */
    }

    /*
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

        $deviceProps = [
            'user' => $this->getUser(),
            'devices' => $devices
        ];

        if ('json' === $request->getRequestFormat()) {
            return $this->json($devices->getValues());
        }

        return $this->render('device_sandbox/index.html.twig', [
            'devices' => $devices,
            'search' => $search
        ]);
    }
    */

    /**
     * @Route("/admin/devices_sandbox", name="devices_sandbox")
     */
    public function indexAction(Request $request, UserInterface $user, LabInstanceRepository $labInstanceRepository, LabRepository $labRepository, SerializerInterface $serializer)
    {
        // Search for Sandbox Lab (id should be 0)
        $lab = $labRepository->find(0);

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

        return $this->render('device_sandbox/index.html.twig', [
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