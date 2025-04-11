<?php

namespace App\Controller;

use App\Entity\NetworkSettings;
use App\Entity\NetworkInterface;
use App\Form\NetworkInterfaceType;
use App\Security\ACL\LabVoter;
use FOS\RestBundle\Context\Context;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\NetworkInterfaceRepository;
use App\Repository\DeviceRepository;
use App\Repository\LabRepository;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;

class NetworkInterfaceController extends Controller
{
    public $networkInterfaceRepository;

    public function __construct(NetworkInterfaceRepository $networkInterfaceRepository, DeviceRepository $deviceRepository, LabRepository $labRepository, EntityManagerInterface $entityManager)
    {
        $this->networkInterfaceRepository = $networkInterfaceRepository;
        $this->deviceRepository = $deviceRepository;
        $this->labRepository = $labRepository;
        $this->entityManager = $entityManager;
    }

    
	#[Get('/api/network-interfaces', name: 'api_get_network_interfaces')]
	#[IsGranted("ROLE_ADMINISTRATOR", message: "Access denied.")]
    #[Route(path: '/admin/network-interfaces', name: 'network_interfaces')]
    public function indexAction(Request $request)
    {
        if ('json' === $request->getRequestFormat()) {
            $search = $request->query->get('search', '');
            $template = $request->query->get('template', true);

            $criteria = Criteria::create()
                ->where(Criteria::expr()->contains('name', $search))
                ->andWhere(Criteria::expr()->eq('isTemplate', $template))
                ->orderBy([
                    'name' => Criteria::ASC
                ]);

            $networkInterfaces = $this->networkInterfaceRepository->matching($criteria);

            return $this->json($networkInterfaces->getValues(), 200, [], ['api_get_network_interface']);
        }

        $networkInterface = new NetworkInterface();
        $networkInterfaceForm = $this->createForm(NetworkInterfaceType::class, $networkInterface);
        $networkInterfaceForm->handleRequest($request);

        if ($networkInterfaceForm->isSubmitted() && $networkInterfaceForm->isValid()) {
            $networkInterface = $networkInterfaceForm->getData();

            $entityManager = $this->entityManager;
            $entityManager->persist($networkInterface);
            $entityManager->flush();

            $this->addFlash('success', 'Interface has been created.');
        }

        return $this->render('network_interface/index.html.twig', [
            'networkInterfaceForm' => $networkInterfaceForm->createView(),
        ]);
    }

    /*    /*public function showAction(Request $request, int $id)
    {
        if (!$networkInterface = $this->networkInterfaceRepository->find($id))
            throw new NotFoundHttpException("Network interface " . $id . " does not exist.");

        return $this->json($networkInterface, 200, [], [$request->get('_route')]);
    }*/

    
    /*public function newAction(Request $request)
    {
        $networkInterface = new NetworkInterface();
        $networkInterfaceForm = $this->createForm(NetworkInterfaceType::class, $networkInterface);
        $networkInterfaceForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $networkInterface = json_decode($request->getContent(), true);
            $networkInterfaceForm->submit($networkInterface, false);
        }

        if ($networkInterfaceForm->isSubmitted() && $networkInterfaceForm->isValid()) {
            /** @var NetworkInterface $networkInterface */
            /*$networkInterface = $networkInterfaceForm->getData();
            $networkSettings = new NetworkSettings();
            $networkSettings
                ->setName($networkInterface->getName() . '_settings');
            $networkInterface->setSettings($networkSettings);

            $entityManager = $this->entityManager;
            $entityManager->persist($networkInterface);
            $entityManager->flush();

            if ('json' === $request->getRequestFormat()) {
                return $this->json($networkInterface, 201, [], ['api_get_network_interface']);
            }

            $this->addFlash('success', 'Network interface has been created.');

            return $this->redirectToRoute('network_interfaces');
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($networkInterfaceForm, 400, [], ['api_get_network_interface']);
        }

        return $this->render('network_interface/new.html.twig', [
            'networkInterfaceForm' => $networkInterfaceForm->createView()
        ]);
    }*/

     
	#[Put('/api/labs/{labId<\d+>}/nodes/{deviceId<\d+>}/interfaces', name: 'api_update_device_interfaces')]
    public function updateNetworkInterface(Request $request, int $labId, int $deviceId)
    {
        $lab = $this->labRepository->find($labId);
        $this->denyAccessUnlessGranted(LabVoter::EDIT_INTERFACE, $lab);

        $device = $this->deviceRepository->find($deviceId);
        $data = json_decode($request->getContent(), true);
        $i=count($device->getNetworkInterfaces());
        $networkInterface = new NetworkInterface();
        //$networkInterface = $this->networkInterfaceRepository->findByDeviceAndName($deviceId, "eth". $data["interface id"]);
        //$networkInterface->setDevice($device);
        if ($device->getNetworkInterfaceTemplate() == "") {
            $networkInterface->setName("eth".$data["interface id"]);
        }
        else {
            $networkInterface->setName($device->getNetworkInterfaceTemplate().$data["interface id"]);
        }
        $networkSettings = new NetworkSettings();
        $networkSettings->setName($networkInterface->getName()."_set".$data["interface id"]);
        $networkInterface->setSettings($networkSettings);
        $device->addNetworkInterface($networkInterface);
        //$networkInterface->setName("eth". $data["interface id"]);
        if ($data["vlan"] == 'none') {
            $networkInterface->setVlan(0);
        }
        else {
            $networkInterface->setVlan($data["vlan"]);
        }

        $networkInterface->setConnection($data["connection"]);
        if (isset($data["connector"])) {
            $networkInterface->setConnectorType($data["connector"]);
        }
        if (isset($data["connector_label"]) && $data["connector_label"]!== "") {
            $networkInterface->setConnectorLabel($data["connector_label"]);
        }
        $entityManager = $this->entityManager;
        $entityManager->persist($device);
        $entityManager->flush();

        $response = new Response();
        $response->setContent(json_encode([
            'code'=> 201,
            'status'=>'success',
            'message' => 'Lab has been saved (60023).',
            'data' => $data]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;

    }

    
	#[Put('/api/labs/{labId<\d+>}/interfaces/{connection<\d+>}/edit', name: 'api_edit_connection')]
    public function editConnection(Request $request, int $labId, int $connection)
    {
        $lab = $this->labRepository->find($labId);
        $this->denyAccessUnlessGranted(LabVoter::EDIT_INTERFACE, $lab);

        $networkInterfaces = $this->networkInterfaceRepository->findByLabAndConnection($labId, $connection);
        $data = json_decode($request->getContent(), true);

        $entityManager = $this->entityManager;
        foreach($networkInterfaces as $networkInterface) {
            $networkInterface->setConnectorType($data["connector"]);
            $networkInterface->setConnectorLabel($data["connector_label"]);

        }
        $entityManager->flush();

        $response = new Response();
        $response->setContent(json_encode([
            'code'=> 201,
            'status'=>'success',
            'message' => 'Lab has been saved (60023).']));
        $response->headers->set('Content-Type', 'application/json');
        return $response;

    }

    
	#[Put('/api/labs/{labId<\d+>}/interfaces/{connection<\d+>}', name: 'api_remove_connection')]
    public function removeConnection(int $labId, int $connection)
    {
        $lab = $this->labRepository->find($labId);
        $this->denyAccessUnlessGranted(LabVoter::EDIT_INTERFACE, $lab);

        $networkInterfaces = $this->networkInterfaceRepository->findByLabAndConnection($labId, $connection);

        $entityManager = $this->entityManager;
        foreach($networkInterfaces as $networkInterface) {
            $device = $networkInterface->getDevice();
            $device->removeNetworkInterface($networkInterface);
            $entityManager->remove($networkInterface);
        }
        $entityManager->flush();

        $response = new Response();
        $response->setContent(json_encode([
            'code'=> 201,
            'status'=>'success',
            'message' => 'Lab has been saved (60023).']));
        $response->headers->set('Content-Type', 'application/json');
        return $response;

    }

    
	#[Get('/api/labs/{labId<\d+>}/vlans', name: 'api_get_vlan')]
    public function getVlan(Request $request, int $labId)
    {
        $lab = $this->labRepository->find($labId);
        $this->denyAccessUnlessGranted(LabVoter::EDIT_INTERFACE, $lab);
        //get the vlan id to set to the device
        $vlans = $this->networkInterfaceRepository->getVlans($labId);
        if ($vlans == null) {
            $vlan = 1;
        }
        else {
            $vlan = (int)$vlans[0]['vlan'] +1;
        }

        $response = new Response();
        $response->setContent(json_encode([
            'code'=> 200,
            'status'=>'success',
            'message' => 'Successfully listed vlan.',
            'data' => [
                "vlan"=>$vlan
            ]
        ]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;

    }

    
	#[Get('/api/labs/{labId<\d+>}/connections', name: 'api_get_connection')]
    public function getConnection(Request $request, int $labId)
    {
        $lab = $this->labRepository->find($labId);
        $this->denyAccessUnlessGranted(LabVoter::EDIT_INTERFACE, $lab);

        //get the connection id to set to the device
        $connections = $this->networkInterfaceRepository->getConnections($labId);
        if ($connections == null) {
            $connection = 1;
        }
        else {
            $connection = (int)$connections[0]['connection'] +1;
        }

        $response = new Response();
        $response->setContent(json_encode([
            'code'=> 200,
            'status'=>'success',
            'message' => 'Successfully listed connection.',
            'data' => [
                "connection"=>$connection
            ]
        ]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;

    }

    
	#[Get('/api/labs/{labId<\d+>}/topology', name: 'api_get_topology')]
    public function getTopology(Request $request, int $labId)
    {
        $lab = $this->labRepository->find($labId);
        $this->denyAccessUnlessGranted(LabVoter::SEE_INTERFACE, $lab);

        $topology = $this->networkInterfaceRepository->getTopology($labId);
        $data = [];
        foreach($topology as $line) {
            if ($line['connectors'] == NULL) {
                $connector = 'Straight';
            }
            else {
                if (count(explode(",", $line["connectors"]))==1) {
                    $connector = $line["connectors"];
                }
                else {
                    $connectors = explode(",", $line["connectors"]);
                    if($connectors[0] == $connectors[1]) {
                        $connector = $connectors[0];
                    }
                    else {
                        $bezier = false;
                        $flowchart = false;
                        foreach($connectors as $connectorType) {
                            if ($connectorType == 'Bezier') {
                                $bezier = true;
                            }
                            if ($connectorType == 'Flowchart') {
                                $flowchart = true;
                            }
                        }
                        if ($bezier == true && $flowchart == true) {
                            $connector = 'Flowchart';
                        }
                        else if ($bezier == true && $flowchart == false) {
                            $connector = 'Bezier';
                        }
                        else if ($bezier == false && $flowchart == true) {
                            $connector = 'Flowchart';
                        }
                        else {
                            $connector = 'Straight';
                        }
                    }
                }
            }

            if ($line['connectorsLabel'] == NULL) {
                $connectorLabel = NULL;
            }
            else {
                if (count(explode(",", $line["connectorsLabel"]))==1) {
                    $connectorLabel = $line["connectorsLabel"];
                }
                else {
                    $connectorsLabel = explode(",", $line["connectorsLabel"]);
                    $connectorLabel = $connectorsLabel[0];
                }
            }

            /*array_push($data, [
                "type"=>"ethernet",
                "source"=> "node".explode(",", $line["devices"])[0],
                "source_type"=> "node",
                "source_label"=> explode(",", $line["names"])[0],
                "destination"=> "node".explode(",", $line["devices"])[1],
                "destination_type"=> "node",
                "destination_label"=> explode(",", $line["names"])[1],
                "network_id"=> $line["connection"], 
                "vlan"=> $line["vlan"], 
                "connector" => $connector,  
                "connector_label" => $connectorLabel,  
            ]);*/

            $data[$line['connection']] = [
                "type"=>"ethernet",
                "source"=> "node".explode(",", $line["devices"])[0],
                "source_type"=> "node",
                "source_label"=> explode(",", $line["names"])[0],
                "destination"=> "node".explode(",", $line["devices"])[1],
                "destination_type"=> "node",
                "destination_label"=> explode(",", $line["names"])[1],
                "network_id"=> $line["connection"], 
                "vlan"=> $line["vlan"], 
                "connector" => $connector,  
                "connector_label" => $connectorLabel,  
            ];
        }
        $response = new Response();
        $response->setContent(json_encode([
            'code'=> 200,
            'status'=>'success',
            'message' => 'Topology loaded.',
            'data' => $data
        ]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;

    }

    
    /*public function editAction(Request $request, int $id)
    {
        $networkInterface = $this->networkInterfaceRepository->find($id);

        if (null === $networkInterface) {
            throw new NotFoundHttpException("Network interface " . $id . " does not exist.");
        }

        $networkInterfaceForm = $this->createForm(NetworkInterfaceType::class, $networkInterface);
        $networkInterfaceForm->handleRequest($request);

        if ($request->getContentType() === 'json') {
            $networkInterface = json_decode($request->getContent(), true);
            $networkInterfaceForm->submit($networkInterface, false);
        }

        if ($networkInterfaceForm->isSubmitted() && $networkInterfaceForm->isValid()) {
            /** @var NetworkInterface $networkInterface */
            /*$networkInterface = $networkInterfaceForm->getData();
            $networkSettings = $networkInterface->getSettings();
            $networkSettings
                ->setName($networkInterface->getName() . '_settings');

            $entityManager = $this->entityManager;
            $entityManager->persist($networkInterface);
            $entityManager->flush();

            if ('json' === $request->getRequestFormat()) {
                return $this->json($networkInterface, 200, [], ['api_get_network_interface']);
            }

            $this->addFlash('success', 'Network interface has been edited.');

            return $this->redirectToRoute('network_interfaces');
        }

        if ('json' === $request->getRequestFormat()) {
            return $this->json($networkInterfaceForm, 400, [], ['api_get_network_interface']);
        }

        return $this->render('network_interface/new.html.twig', [
            'networkInterfaceForm' => $networkInterfaceForm->createView(),
            'networkInterface' => $networkInterface
        ]);
    }*/

    /*    /*public function deleteAction(Request $request, int $id)
    {
        if (!$networkInterface = $this->networkInterfaceRepository->find($id)) {
            throw new NotFoundHttpException("Network interface " . $id . " does not exist.");
        }

        $entityManager = $this->entityManager;
        $entityManager->remove($networkInterface);
        $entityManager->flush();

        if ('json' === $request->getRequestFormat()) {
            return $this->json();
        }

        $this->addFlash('success', $networkInterface->getName() . ' has been deleted.');

        return $this->redirectToRoute('network_interfaces');
    }*/
}
