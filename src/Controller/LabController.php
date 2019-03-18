<?php

namespace App\Controller;

use App\Entity\Lab;
use App\Form\LabType;

use App\Service\FileUploader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use GuzzleHttp\Client;

class LabController extends AppController
{
    /**
     * @Route("/admin/labs", name="labs")
     */
    public function indexAction(Request $request)
    {
        $repository = $this->getDoctrine()->getRepository('App:Lab');

        $search = $request->query->get('search', '');
        
        if ($search !== '') {
            $data = $repository->findByNameLike($search);
        } else {
            $data = $repository->findAll();
        }

        if ($this->getRequestedFormat($request) === JsonRequest::class) {
            return $this->json($data);
        }
        
        return $this->render('lab/index.html.twig', [
            'labs' => $data,
            'search' => $search
        ]);
    }

    /**
     * @Route("/admin/labs/{id<\d+>}.{_format}",
     *  defaults={"_format": "html"},
     *  requirements={"_format": "html|json"},
     *  name="show_lab",
     *  methods="GET")
     */
    public function showAction(Request $request, $id)
    {
        $repository = $this->getDoctrine()->getRepository('App:Lab');

        $data = $repository->find($id);

        if (null === $data) {
            throw new NotFoundHttpException();
        }

        if ($this->getRequestedFormat($request) === JsonRequest::class) {
            return $this->json($data);
        }
        
        return $this->render('lab/view.html.twig', [
            'lab' => $data
        ]);
    }

    /**
     * @Route("/admin/labs/new", name="new_lab")
     */
    public function newAction(Request $request, FileUploader $fileUploader)
    {
        $lab = new Lab();
        $labForm = $this->createForm(LabType::class, $lab);
        $labForm->handleRequest($request);
        
        if ($labForm->isSubmitted() && $labForm->isValid()) {
            $lab = $labForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($lab);
            $entityManager->flush();
            
            $this->addFlash('success', 'Lab has been created.');

            return $this->redirectToRoute('labs');
        }
        
        return $this->render('lab/new.html.twig', [
            'labForm' => $labForm->createView(),
        ]);
    }

    /**
     * @Route("/admin/labs/{id<\d+>}/edit", name="edit_lab", methods={"GET", "POST"})
     */
    public function editAction(Request $request, $id, FileUploader $fileUploader)
    {
        $repository = $this->getDoctrine()->getRepository('App:Lab');

        $lab = $repository->find($id);

        if (null === $lab) {
            throw new NotFoundHttpException();
        }

        $labForm = $this->createForm(LabType::class, $lab);
        $labForm->handleRequest($request);
        
        if ($labForm->isSubmitted() && $labForm->isValid()) {
            $lab = $labForm->getData();
            
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($lab);
            $entityManager->flush();
            
            $this->addFlash('success', 'Lab has been edited.');

            return $this->redirectToRoute('show_lab', [
                'id' => $id
            ]);
        }
        
        return $this->render('lab/new.html.twig', [
            'labForm' => $labForm->createView(),
            'id' => $id,
            'name' => $lab->getName()
        ]);
    }
        
    /**
     * @Route("/admin/labs/{id<\d+>}", name="delete_lab", methods="DELETE")
     */
    public function deleteAction(Request $request, $id)
    {
        $repository = $this->getDoctrine()->getRepository('App:Lab');
            
        $data = null;
        $status = 200;
            
        $lab = $repository->find($id);
            
        if ($lab == null) {
            $status = 404;
        } else {
            $em = $this->getDoctrine()->getManager();
            $em->remove($lab);
            $em->flush();
                
            $data = [
                'message' => 'Lab has been deleted.'
            ];
        }
            
        if ($this->getRequestedFormat($request) === JsonRequest::class) {
            return $this->json($data, $status);
        }

        return $this->redirectToRoute('labs');
    }

    /**
     * @Route("/websockify/test", name="test_websockify")
     */
    public function testWebsockify(Request $request)
    {
        return $this->render('lab/vm_view.html.twig', [
            'host' => 'test',
            'port' => 'test',
            'path' => 'test'
        ]);

        $client = new Client();
        $response = $client->request('POST', 'http://192.168.1.200:8080/lab', [
            'body' => '<lab>
            <name>Lab_name</name>
            <id>1</id>
            <tp_managed>1</tp_managed>
            <tp_type>supervised</tp_type>
            <tp_access>vpn</tp_access>
            <!-- personne qui a lancé le lab -->
            <user>
              <login>root@localhost</login>
            </user>
            <nodes>
              <device type="virtuel" property="X" id="6" script="" image="https://people.debian.org/~aurel32/qemu/amd64/debian_squeeze_amd64_standard.qcow2" order="1" hypervisor="qemu">
                <name>VM_1</name>
                <nom_brute>VM_1</nom_brute>
                <interface id="19" nom_physique="eth0" nom_virtuel="tap12" type="1" mac_address="00:AA:BB:CC:DD:EE" />
                <interface_control id="14" nom_physique="eth0_phy_VM1" nom_virtuel="eth0_ctrl_VM1" ipv4="" Masque="255.255.255.0" IPv6="2001:660:4601:7008::124" Prefix="" DNSv4="8.8.8.8" Gatewayv4="0.0.0.0" protocol="vnc" port="7220"/>
                <!-- if vpn access -->
                <direct_access>
                  <IPv4>1.2.3.4/24</IPv4>
                  <IPv6></IPv6>
                </direct_access>
                <system memory="512" disk="40"></system>
              </device>
              <device type="virtuel" property="X" id="9" script="" image="/usr/local/Virtualize/kvm-image/images/debian-testing20160512.img" relativ_path="/usr/local/Virtualize/kvm-image/images/img-rel" order="2">
                <name>VM_2</name>
                <interface id="20" nom_physique="eth0" nom_virtuel="tap13"/>
                <interface_control id="15" nom_physique="eth0_phy_ctrl_VM2" nom_virtuel="eth0_ctrl_VM2" ipv4="194.57.105.124" Masque="255.255.255.0" IPv6="2001:660:4601:7008::124" Prefix="" DNSv4="8.8.8.8" Gatewayv4="0.0.0.0" protocol="vnc" port="7221"/>
              </device>
              <device type="switch" property="switch" id="10" script="" image="Sans" relativ_path="Sans" order="3">
                <name>OVS1</name>
                <vpn>
                  <ipv4>1.2.3.4/24</ipv4>
                </vpn>
                <interface id="16" nom_physique="port1" nom_virtuel="port1"/>
                <interface id="21" nom_physique="port2" nom_virtuel="port2"/>
                <interface id="24" nom_physique="port3" nom_virtuel="port3"/>
              </device>
            </nodes>
            <networks>
              <network type="OVS" device_id="10">
                <port id="1" interface_id1="19" vlan1="1" interface_id2="16" vlan2="1"/>
                <port id="2" interface_id1="20" vlan1="1" interface_id2="21" vlan2="1"/>
              </network>
            </networks>
            <init>
              <network_lab>1.2.3.0/24</network_lab>
              <network_user>1.2.3.16/26</network_user>
              <serveur>
                <IPv4>194.57.105.124</IPv4>
                <IPv6>0</IPv6>
                <index_interface>12</index_interface>
                <index_interface_control>1</index_interface_control>
              </serveur>
            </init>
          </lab>',
            'headers' => [
                'Content-Type' => 'application/xml'
            ]
        ]);

        return new Response($response->getBody());
    }

    /**
     * @Route("/websockify/test/stop", name="test_stop_websockify")
     */
    public function testWebsockifyStop(Request $request)
    {
        $client = new Client();
        $response = $client->request('POST', 'http://192.168.1.200:8080/lab/stop', [
            'body' => '<lab>
            <name>Lab_name</name>
            <id>1</id>
            <tp_managed>1</tp_managed>
            <tp_type>supervised</tp_type>
            <tp_access>vpn</tp_access>
            <!-- personne qui a lancé le lab -->
            <user>
              <login>root@localhost</login>
            </user>
            <nodes>
              <device type="virtuel" property="X" id="6" script="" image="https://people.debian.org/~aurel32/qemu/amd64/debian_squeeze_amd64_standard.qcow2" order="1" hypervisor="qemu">
                <name>VM_1</name>
                <nom_brute>VM_1</nom_brute>
                <interface id="19" nom_physique="eth0" nom_virtuel="tap12" type="1" mac_address="00:AA:BB:CC:DD:EE" />
                <interface_control id="14" nom_physique="eth0_phy_VM1" nom_virtuel="eth0_ctrl_VM1" ipv4="" Masque="255.255.255.0" IPv6="2001:660:4601:7008::124" Prefix="" DNSv4="8.8.8.8" Gatewayv4="0.0.0.0" protocol="vnc" port="7220"/>
                <!-- if vpn access -->
                <direct_access>
                  <IPv4>1.2.3.4/24</IPv4>
                  <IPv6></IPv6>
                </direct_access>
                <system memory="512" disk="40"></system>
              </device>
              <device type="virtuel" property="X" id="9" script="" image="/usr/local/Virtualize/kvm-image/images/debian-testing20160512.img" relativ_path="/usr/local/Virtualize/kvm-image/images/img-rel" order="2">
                <name>VM_2</name>
                <interface id="20" nom_physique="eth0" nom_virtuel="tap13"/>
                <interface_control id="15" nom_physique="eth0_phy_ctrl_VM2" nom_virtuel="eth0_ctrl_VM2" ipv4="194.57.105.124" Masque="255.255.255.0" IPv6="2001:660:4601:7008::124" Prefix="" DNSv4="8.8.8.8" Gatewayv4="0.0.0.0" protocol="vnc" port="7221"/>
              </device>
              <device type="switch" property="switch" id="10" script="" image="Sans" relativ_path="Sans" order="3">
                <name>OVS1</name>
                <vpn>
                  <ipv4>1.2.3.4/24</ipv4>
                </vpn>
                <interface id="16" nom_physique="port1" nom_virtuel="port1"/>
                <interface id="21" nom_physique="port2" nom_virtuel="port2"/>
                <interface id="24" nom_physique="port3" nom_virtuel="port3"/>
              </device>
            </nodes>
            <networks>
              <network type="OVS" device_id="10">
                <port id="1" interface_id1="19" vlan1="1" interface_id2="16" vlan2="1"/>
                <port id="2" interface_id1="20" vlan1="1" interface_id2="21" vlan2="1"/>
              </network>
            </networks>
            <init>
              <network_lab>1.2.3.0/24</network_lab>
              <network_user>1.2.3.16/26</network_user>
              <serveur>
                <IPv4>194.57.105.124</IPv4>
                <IPv6>0</IPv6>
                <index_interface>12</index_interface>
                <index_interface_control>1</index_interface_control>
              </serveur>
            </init>
          </lab>',
            'headers' => [
                'Content-Type' => 'application/xml'
            ]
        ]);

        return new Response($response->getBody());
    }
}
