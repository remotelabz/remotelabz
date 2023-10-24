<?php

namespace App\Controller;

use App\Entity\NetworkSettings;
use App\Entity\NetworkInterface;
use App\Form\NetworkInterfaceType;
use FOS\RestBundle\Context\Context;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\NetworkInterfaceRepository;
use App\Repository\DeviceRepository;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpFoundation\Response;

class NetworkInterfaceController extends Controller
{
    public $networkInterfaceRepository;

    public function __construct(NetworkInterfaceRepository $networkInterfaceRepository, DeviceRepository $deviceRepository)
    {
        $this->networkInterfaceRepository = $networkInterfaceRepository;
        $this->deviceRepository = $deviceRepository;
    }

    /**
     * @Route("/admin/network-interfaces", name="network_interfaces")
     * 
     * @Rest\Get("/api/network-interfaces", name="api_get_network_interfaces")
     */
    public function indexAction(Request $request)
    {
        if ('json' === $request->getRequestFormat()) {
            $search = $request->query->get('search', '');
            $template = $request->query->get('template', true);

            $criteria = Criteria::create()
                ->where(Criteria::expr()->contains('name', $search))
                ->andWhere(Criteria::expr()->eq('isTemplate', $template))
                ->orderBy([
                    'id' => Criteria::DESC
                ]);

            $networkInterfaces = $this->networkInterfaceRepository->matching($criteria);

            return $this->json($networkInterfaces->getValues(), 200, [], ['api_get_network_interface']);
        }

        $networkInterface = new NetworkInterface();
        $networkInterfaceForm = $this->createForm(NetworkInterfaceType::class, $networkInterface);
        $networkInterfaceForm->handleRequest($request);

        if ($networkInterfaceForm->isSubmitted() && $networkInterfaceForm->isValid()) {
            $networkInterface = $networkInterfaceForm->getData();

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($networkInterface);
            $entityManager->flush();

            $this->addFlash('success', 'Interface has been created.');
        }

        return $this->render('network_interface/index.html.twig', [
            'networkInterfaceForm' => $networkInterfaceForm->createView(),
        ]);
    }


    /**
     * @Rest\Get("/api/network-interfaces/{id<\d+>}", name="api_get_network_interface")
     */
    public function showAction(Request $request, int $id)
    {
        if (!$networkInterface = $this->networkInterfaceRepository->find($id))
            throw new NotFoundHttpException("Network interface " . $id . " does not exist.");

        return $this->json($networkInterface, 200, [], [$request->get('_route')]);
    }

    /**
     * @Route("/admin/network-interfaces/new", name="new_network_interface", methods={"GET", "POST"})
     * 
     * @Rest\Post("/api/network-interfaces", name="api_new_network_interface")
     */
    public function newAction(Request $request)
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
            $networkInterface = $networkInterfaceForm->getData();
            $networkSettings = new NetworkSettings();
            $networkSettings
                ->setName($networkInterface->getName() . '_settings');
            $networkInterface->setSettings($networkSettings);

            $entityManager = $this->getDoctrine()->getManager();
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
    }

     /**
     * @Rest\Put("/api/labs/{labId<\d+>}/nodes/{deviceId<\d+>}/interfaces", name="api_update_device_interfaces")
     */
    public function updateNetworkInterface(Request $request, int $labId, int $deviceId)
    {
        $device = $this->deviceRepository->find($deviceId);
        $data = json_decode($request->getContent(), true);
        $i=count($device->getNetworkInterfaces());
        $networkInterface = new NetworkInterface();
        //$networkInterface = $this->networkInterfaceRepository->findByDeviceAndName($deviceId, "eth". $data["interface id"]);
        //$networkInterface->setDevice($device);
        $networkInterface->setName($device->getName()."_net".$data["interface id"]);
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
        $networkInterface->setConnectorType($data["connector"]);
        $entityManager = $this->getDoctrine()->getManager();
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

    /**
     * @Rest\Put("/api/labs/{labId<\d+>}/interfaces/{vlan<\d+>}", name="api_remove_connection")
     */
    public function removeConnection(int $labId, int $vlan)
    {
        $networkInterfaces = $this->networkInterfaceRepository->findByLabAndVlan($labId, $vlan);
        
        $entityManager = $this->getDoctrine()->getManager();
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
    
    /**
     * @Rest\Get("/api/labs/{labId<\d+>}/vlans", name="api_get_vlan")
     */
    public function getVlan(Request $request, int $labId)
    {
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

    /**
     * @Rest\Get("/api/labs/{labId<\d+>}/connections", name="api_get_connection")
     */
    public function getConnection(Request $request, int $labId)
    {
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

    /**
     * @Rest\Get("/api/labs/{labId<\d+>}/topology", name="api_get_topology")
     */
    public function getTopology(Request $request, int $labId)
    {
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
            
            array_push($data, [
                "type"=>"ethernet",
                "source"=> "node".explode(",", $line["devices"])[0],
                "source_type"=> "node",
                "source_label"=> explode(",", $line["names"])[0],
                "destination"=> "node".explode(",", $line["devices"])[1],
                "destination_type"=> "node",
                "destination_label"=> explode(",", $line["names"])[1],
                "network_id"=> $line["vlan"], 
                "connector" => $connector,  
            ]);
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

    /**
     * @Route("/admin/network-interfaces/{id<\d+>}/edit", name="edit_network_interface", methods={"GET", "POST"})
     * 
     * @Rest\Put("/api/network-interfaces/{id<\d+>}", name="api_edit_network_interface")
     */
    public function editAction(Request $request, int $id)
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
            $networkInterface = $networkInterfaceForm->getData();
            $networkSettings = $networkInterface->getSettings();
            $networkSettings
                ->setName($networkInterface->getName() . '_settings');

            $entityManager = $this->getDoctrine()->getManager();
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
    }

    /**
     * @Route("/admin/network-interfaces/{id<\d+>}/delete", name="delete_network_interface", methods="GET")
     * 
     * @Rest\Delete("/api/network-interfaces/{id<\d+>}", name="api_delete_network_interface")
     */
    public function deleteAction(Request $request, int $id)
    {
        if (!$networkInterface = $this->networkInterfaceRepository->find($id)) {
            throw new NotFoundHttpException("Network interface " . $id . " does not exist.");
        }

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($networkInterface);
        $entityManager->flush();

        if ('json' === $request->getRequestFormat()) {
            return $this->json();
        }

        $this->addFlash('success', $networkInterface->getName() . ' has been deleted.');

        return $this->redirectToRoute('network_interfaces');
    }
}
