<?php

namespace App\Controller;

use IPTools;
use App\Entity\TextObject;
use App\Entity\Lab;
use App\Entity\Device;
use App\Entity\Network;
use App\Entity\Activity;
use App\Entity\LabInstance;
use App\Entity\DeviceInstance;
use App\Entity\NetworkInterfaceInstance;
use App\Entity\NetworkInterface;
use App\Entity\NetworkSettings;
use GuzzleHttp\Psr7;
use App\Form\LabType;
use GuzzleHttp\Client;
use App\Form\DeviceType;
use Psr\Log\LoggerInterface;
use App\Repository\TextObjectRepository;
use App\Repository\LabRepository;
use App\Exception\WorkerException;
use App\Repository\UserRepository;
use FOS\RestBundle\Context\Context;
use App\Repository\DeviceRepository;
use Remotelabz\Message\Message\InstanceActionMessage;
use App\Repository\ActivityRepository;
use JMS\Serializer\SerializerInterface;
use App\Exception\NotInstancedException;
use JMS\Serializer\SerializationContext;
use App\Repository\LabInstanceRepository;
use App\Repository\HypervisorRepository;
use App\Repository\ControlProtocolTypeRepository;
use App\Repository\FlavorRepository;
use Doctrine\Common\Collections\Criteria;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\RequestException;
use App\Exception\AlreadyInstancedException;
use App\Repository\OperatingSystemRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Service\Lab\LabImporter;
use App\Service\LabBannerFileUploader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\ORMException;
use Exception;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Doctrine\Persistence\ManagerRegistry;
use JMS\Serializer\SerializerBuilder;
use Symfony\Component\Yaml\Yaml;
use function Symfony\Component\String\u;


class TemplateController extends Controller
{
    private $workerServer;

    private $workerPort;

    private $workerAddress;

    /** @var LoggerInterface $logger */
    private $logger;

    public function __construct(
        LoggerInterface $logger,
        operatingSystemRepository $operatingSystemRepository,
        HypervisorRepository $hypervisorRepository,
        ControlProtocolTypeRepository $controlProtocolTypeRepository,
        FlavorRepository $flavorRepository,
        DeviceRepository $deviceRepository
        )
    {
        $this->workerServer = (string) getenv('WORKER_SERVER');
        $this->workerPort = (int) getenv('WORKER_PORT');
        $this->workerAddress = $this->workerServer . ":" . $this->workerPort;
        $this->logger = $logger;
        $this->operatingSystemRepository = $operatingSystemRepository;
        $this->hypervisorRepository = $hypervisorRepository;
        $this->controlProtocolTypeRepository = $controlProtocolTypeRepository;
        $this->flavorRepository = $flavorRepository;
        $this->deviceRepository = $deviceRepository;
    }

    /**
     * @Route("/templates", name="templates")
     * 
     * @Rest\Get("/api/list/templates", name="api_get_templates")
     * 
     */
    public function indexAction(Request $request)
    {

        $templates = $this->deviceRepository->findByTemplate(true);
        foreach ($templates as $template) {
            if (!is_file('/opt/remotelabz/config/templates/'.$template->getId().'-'.u($template->getName())->camel().'.yaml')) {
               $this->newAction($template);
            }
        }
        $node_templates = Array();
        $node_config = Array();
        foreach ( scandir('/opt/remotelabz/config/templates/') as $element ) {
                if (is_file('/opt/remotelabz/config/templates/'.$element) && preg_match('/^.+\.yaml$/', $element) && $element != 'docker.yaml') {
                        $cur_name = preg_replace('/.yaml/','',$element ) ;
                        $cur_templ = Yaml::parse(file_get_contents('/opt/remotelabz/config/templates/'.$element));
                        if ( isset($cur_templ['description']) ) {
                                $node_templates[$cur_name] =  $cur_templ['description'] ;
                        }
                        if ( isset($cur_templ['config_script']) ) {
                                $node_config[$cur_name] =  $cur_templ['config_script'] ;
                        }
                }
        }
        
                if (isset($custom_templates)) {
                        $node_templates = array_merge ( $node_templates , $custom_templates );
                }
            natcasesort(  $node_templates ) ;
            
        $response = new Response();
        $response->setContent(json_encode([
            'code'=> 200,
            'status'=>'success',
            'message' => 'Successfully listed node templates (60003).',
            'data' => $node_templates]));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * @Route("/templates/{id<\d+>}", name="show_template", methods="GET")
     * 
     * @Rest\Get("/api/list/templates/{id<\d+>}", name="api_get_template")
     */
    public function showAction(
        int $id,
        Request $request)
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $this->logger->debug("Device id request : ".$id);

        $device = $this->deviceRepository->find($id);
        $deviceName = u($device->getName())->camel();
 
        if (!is_file('/opt/remotelabz/config/templates/'.$id.'-'.$deviceName.'.yaml')) {
            $this->newAction($device);
         }
        $p = Yaml::parse(file_get_contents('/opt/remotelabz/config/templates/'.$id.'-'.$deviceName.'.yaml'));
        $p['template'] = $deviceName;

        if (!isset($p['context']) || !isset($p['template'])) {

            $response->setContent(json_encode([
                'code' => 400,
                'status'=>'fail',
                'message' => 'Requested template is not valid (60033).',
                'data' =>$data])
            );
            return $response;
        }

        $data = Array();

        $data['options']['name'] = Array(
            'name' => 'Name',
            'type' => 'input',
            'value' => $p['name'] ?? ''
        );

        // Icon
        $data['options']['icon'] = Array(
            'name' => 'Icon',
            'type' => 'list',
            'multiple'=> false,
            'value' => $p['icon'] ?? '',
            'list' => $this->listNodeIcons()
        );
        /*$data['options']['config'] = Array(
			'name' => 'Startup configuration',
			'type' => 'list',
            'multiple'=> false,
			'value' => '0',	// None
			'list' => $this->listNodeConfigTemplates()
		);*/
		//$data['options']['config']['list'][0] = 'None';	// None
		//$data['options']['config']['list'][1] = 'Exported';	// Exported

        $data['options']['delay'] = Array(
            'name' => 'Delay (s)',
            'type' => 'input',
            'value' => 0
        );

        //if ($p['type'] = 'remotelabz') {
            $data['options']['brand'] = Array(
                'name' => 'Brand',
                'type' => 'input',
                'value' => $p['brand'] ?? ''
            );

            $data['options']['model'] = Array(
                'name' => 'Model',
                'type' => 'input',
                'value' => $p['model'] ?? ''
            );

            $data['options']['type'] = Array(
                'name' => 'Type',
                'type' => 'list',
                'multiple'=> false,
                'value' => $p['type'] ?? 'container',
                'list' => Array('vm'=>'vm', 'container'=>'container', 'switch' => 'switch')
            );

            $data['options']['flavor'] = Array(
                'name' => 'Ram',
                'type' => 'list',
                'multiple'=> false,
                'value' => $p['flavor'] ?? '',
                'list' => $this->listFlavors()
            );

            $data['options']['cpu'] = Array(
                'name' => 'Number of cpu',
                'type' => 'input',
                'value' => $p['cpu'] ?? '1',
            );

            $data['options']['core'] = Array(
                'name' => 'Number of cores',
                'type' => 'input',
                'value' => $p['core'] ?? '',
            );

            $data['options']['socket'] = Array(
                'name' => 'Number of sockets',
                'type' => 'input',
                'value' => $p['socket'] ?? '',
            );

            $data['options']['thread'] = Array(
                'name' => 'Number of threads',
                'type' => 'input',
                'value' => $p['thread'] ?? '',
            );

            $data['options']['operatingSystem'] = Array(
                'name' => 'Operating System',
                'type' => 'list',
                'multiple'=> false,
                'value' => $p['operatingSystem'] ?? '',
                'list' => $this->listOperatingSystems()
            );

            
            $data['options']['hypervisor'] = Array(
                'name' => 'Hypervisor',
                'type' => 'list',
                'multiple'=> false,
                'value' => $p['hypervisor'] ?? '',
                'list' => $this->listHypervisors()
            );

            $data['options']['controlProtocol'] = Array(
                'name' => 'Control Protocol',
                'type' => 'list',
                'multiple'=> true,
                'value' => $p['controlProtocol'] ?? '',
                'list' => $this->listControlProtocolTypes()
            );
        
        //}
        
        $response->setContent(json_encode([
            'code' => 200,
            'status'=>'success',
            'message' => 'Successfully listed node template (60032).',
            'data' =>$data]));
        
        return $response;
    }

    public function listNodeIcons() {
        $results = Array();
        foreach (scandir('/opt/remotelabz/assets/images/icons') as $filename) {
            if (is_file('/opt/remotelabz/assets/images/icons/'.$filename) && preg_match('/^.+\.[png$\|jpg$]/', $filename)) {
                $patterns[0] = '/^(.+)\.\(png$\|jpg$\)/';  // remove extension
                $replacements[0] = '$1';
                $name = preg_replace($patterns, $replacements, $filename);
                $results[$filename] = $name;
            }
        }
        return $results;
    }

    function listNodeConfigTemplates() {
        $results = Array();
        foreach (scandir('/opt/remotelabz/assets/js/components/Editor2/configs') as $filename) {
            if (is_file('/opt/remotelabz/assets/js/components/Editor2/configs/'.$filename) && preg_match('/^.+\.php$/', $filename)) {
                $patterns[0] = '/^(.+)\.php$/';  // remove extension
                $replacements[0] = '$1';
                $name = preg_replace($patterns, $replacements, $filename);
                $results[$filename] = $name;
            }
        }
        return $results;
    }

    public function listControlProtocolTypes() {

        $controlProtocolTypeList= [];
            $controlProtocolTypes = $this->controlProtocolTypeRepository->findAll();
            foreach($controlProtocolTypes as $controlProtocolType){
                $controlProtocolTypeList[$controlProtocolType->getId()] = $controlProtocolType->getName();
            }
        return $controlProtocolTypeList;
    }

    public function listHypervisors() {

        $hypervisorList= [];
            $hypervisors = $this->hypervisorRepository->findAll();
            foreach($hypervisors as $hypervisor){
                $hypervisorList[$hypervisor->getId()] = $hypervisor->getName();
            }
        return $hypervisorList;
    }

    public function listOperatingSystems() {

        $operatingSystemList= [];
            $operatingSystems = $this->operatingSystemRepository->findAll();
            foreach($operatingSystems as $operatingSystem){
                $operatingSystemList[$operatingSystem->getId()] = $operatingSystem->getName();
            }
        return $operatingSystemList;
    }
    

    public function listFlavors() {

        $flavorList= [];
            $flavors = $this->flavorRepository->findAll();
            foreach($flavors as $flavor){
                $flavorList[$flavor->getId()] = $flavor->getName() ;
            }
        return $flavorList;
    }

    public function newAction($template) {
        $controlProtocolTypes= [];
        foreach($template->getControlProtocolTypes() as $controlProtocolType) {
            array_push($controlProtocolTypes, $controlProtocolType->getId());
        }
        if ($controlProtocolTypes == []) {
            $controlProtocolTypes = '';
        }
        $icon = "Server_Linux.png";
        if($template->getIcon() != null) {
            $icon = $template->getIcon();
        }

    $templateData = [
        "name" => $template->getName(),
        "type" => $template->getType(),
        "icon" => $icon,
        "operatingSystem" => $template->getOperatingSystem()->getId(),
        "flavor" => $template->getFlavor()->getId(),
        "controlProtocol" => $controlProtocolTypes,
        "hypervisor" => $template->getHypervisor()->getId(),
        "brand" => $template->getBrand(),
        "model" => $template->getModel(),
        "description" => $template->getName(),
        "cpu" => $template->getNbCpu(),
        "core" => $template->getNbCore(),
        "socket" => $template->getNbSocket(),
        "thread" => $template->getNbSocket(),
        "context" => "remotelabz",
        "config_script" => "embedded",
        "ethernet" => 1,
    ];

    $yamlContent = Yaml::dump($templateData,2);

    file_put_contents("/opt/remotelabz/config/templates/".$template->getId()."-". u($template->getName())->camel() . ".yaml", $yamlContent);
    }
}
