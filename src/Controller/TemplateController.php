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
/*use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;*/


class TemplateController extends Controller
{
    private $workerServer;

    private $workerPort;

    private $workerAddress;

    /** @var LoggerInterface $logger */
    private $logger;

    /** @var TextObjectRepository $textobjectRepository */
    private $textobjectRepository;

    public function __construct(
        LoggerInterface $logger,
        operatingSystemRepository $operatingSystemRepository,
        HypervisorRepository $hypervisorRepository,
        ControlProtocolTypeRepository $controlProtocolTypeRepository,
        FlavorRepository $flavorRepository
        //SerializerBuilder $serializerBuilder
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
        //$this->serializerBuilder = $serializerBuilder;
    }

    /**
     * @Route("/templates", name="templates")
     * 
     * @Rest\Get("/api/list/templates", name="api_get_templates")
     * 
     */
    public function indexAction(Request $request)
    {
        /*$encoders = [new YamlEncoder(), new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];

        $serializer = new Serializer($normalizers, $encoders);*/
        //$serializer = SerializerBuilder::create()->build();

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
            
            foreach ( $node_templates as $templ => $desc ) {
                $found = 0 ;
                if ($templ == "linux" ) {
                $found = 1 ;
                }
                if ( $found == 0 )  {
                    //$node_templates[$templ] = $desc.'.missing'  ;
                    $node_templates[$templ] = $desc.TEMPLATE_DISABLED  ;
                }
                    
            }

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
     * @Route("/templates/{template<\w+>}", name="show_template", methods="GET")
     * 
     * @Rest\Get("/api/list/templates/{template<\w+>}", name="api_get_template")
     */
    public function showAction(
        string $template,
        Request $request)
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');

        $p = Yaml::parse(file_get_contents('/opt/remotelabz/config/templates/'.$template.'.yaml'));
        $p['template'] = $template;

        if (!isset($p['type']) || !isset($p['template'])) {

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
            'value' => $p['name']
        );

        // Icon
        $data['options']['icon'] = Array(
            'name' => 'Icon',
            'type' => 'list',
            'value' => $p['icon'],
            'list' => $this->listNodeIcons()
        );

        $data['options']['delay'] = Array(
            'name' => 'Delay (s)',
            'type' => 'input',
            'value' => 0
        );

        if ($p['type'] = 'remotelabz') {
            $data['options']['brand'] = Array(
                'name' => 'Brand',
                'type' => 'input',
                'value' => ''
            );

            $data['options']['type'] = Array(
                'name' => 'Type',
                'type' => 'list',
                'value' => 'container',
                'list' => Array('vm'=>'vm', 'container'=>'container')
            );

            $data['options']['flavor'] = Array(
                'name' => 'Ram',
                'type' => 'list',
                'value' => '',
                'list' => $this->listFlavors()
            );

            $data['options']['core'] = Array(
                'name' => 'Number of cores',
                'type' => 'input',
                'value' => '1',
            );

            $data['options']['socket'] = Array(
                'name' => 'Number of sockets',
                'type' => 'input',
                'value' => '1',
            );

            $data['options']['thread'] = Array(
                'name' => 'Number of threads',
                'type' => 'input',
                'value' => '1',
            );

            $data['options']['operatingSystem'] = Array(
                'name' => 'Operating System',
                'type' => 'list',
                'value' => '',
                'list' => $this->listOperatingSystems()
            );

            
            $data['options']['hypervisor'] = Array(
                'name' => 'Hypervisor',
                'type' => 'list',
                'value' => '',
                'list' => $this->listHypervisors()
            );

            $data['options']['controlProtocol'] = Array(
                'name' => 'Control Protocol',
                'type' => 'list',
                'value' => '',
                'list' => $this->listControlProtocolTypes()
            );
        
        }
        
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
                $flavorList[$flavor->getId()] = $flavor->getMemory() ;
            }
        return $flavorList;
    }
}
