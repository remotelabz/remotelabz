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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
        
        $props=$serializer->serialize(
                $deviceProps,
                'json',
                SerializationContext::create()->setGroups(['sandbox']));

        $propsArray = json_decode($props, true);
        $prettyProps = json_encode($propsArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->logger->debug("[DeviceSandboxController:indexAction]::Serialized props:\n" . $prettyProps);

        return $this->render('device_sandbox/index.html.twig', [
            'devices' => $devices,
            'labs' => $labs,
            'search' => $search,
            'props' => $props
        ]);
    }

    #[Route(path: '/admin/sandbox/{id<\d+>}', name: 'sandbox_view')]
    public function viewAction(Request $request, int $id, UserInterface $user, LabInstanceRepository $labInstanceRepository, LabRepository $labRepository, SerializerInterface $serializer)
    {
        $lab = $labRepository->find($id);

        if (!$lab) {
            throw new NotFoundHttpException("Sandbox Lab does not exist.");
        }

        $userLabInstance = $labInstanceRepository->findByUserAndLab($user, $lab);

        if (!$userLabInstance) {
            $this->logger->warning("[DeviceSandboxController:viewAction]:: Lab instance not found for lab " . $id . " and user " . $user->getId());
            
            // Rediriger vers la liste avec un message
            $this->addFlash('warning', 'Lab instance is being created. Please wait a moment and try again.');
            return $this->redirectToRoute('sandbox');
        }

        $deviceStarted = [];

        foreach ($lab->getDevices()->getValues() as $device) {
            $deviceStarted[$device->getId()] = false;
            if ($userLabInstance && $userLabInstance->getUserDeviceInstance($device)) {
                $deviceStarted[$device->getId()] = true;
                //$this->logger->debug("[DeviceSandboxController:viewAction]:: device started true for ".$device->getName());
            }
            else {
                //$this->logger->debug("[DeviceSandboxController:viewAction]:: device started false for ".$device->getName());
            }
        }

        $instanceManagerProps = [
            'user' => $this->getUser(),
            'labInstance' => $userLabInstance,
            'lab' => $lab,
            'isSandbox' => true,
            'hasBooking' => false,
        ];

        preg_match("/^Sandbox_Lab.*$/", $lab->getName(), $result);
        // Use to add or not a message to start first the DHCP Service
        $sandboxlab = ($result != null);
        $props = $serializer->serialize(
                    $instanceManagerProps,
                    'json',
                    SerializationContext::create()->setGroups(['sandbox'])
                );
        
        $propsArray = json_decode($props, true);
        $prettyProps = json_encode($propsArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->logger->debug("[DeviceSandboxController:viewAction]::Serialized props:\n" . $prettyProps);

        foreach ($userLabInstance->getDeviceInstances() as $dev) {
            $this->logger->debug("[DeviceSandboxController:viewAction]::Device of this lab:" . $dev->getDevice()->getId()." ".$dev->getDevice()->getName());
            foreach ($dev->getDevice()->getIsos() as $iso) {
                $this->logger->debug("[DeviceSandboxController:viewAction]::Iso for this device :" . $iso->getName());
            }
        }

        if ($userLabInstance) {
            foreach ($userLabInstance->getDeviceInstances() as $dev) {
                $this->logger->debug("[DeviceSandboxController:viewAction]::Device of this lab:" . $dev->getDevice()->getId()." ".$dev->getDevice()->getName());
                foreach ($dev->getDevice()->getIsos() as $iso) {
                    $this->logger->debug("[DeviceSandboxController:viewAction]::Iso for this device :" . $iso->getName());
                }
            }
        }
        
        return $this->render('device_sandbox/view.html.twig', [
            'lab' => $lab,
            'labInstance' => $userLabInstance,
            'deviceStarted' => $deviceStarted,
            'user' => $user,
            'sandboxlab' => $sandboxlab,
            'props' => $props
        ]);
    }
}