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
use App\Repository\UserRepository;
use App\Repository\DeviceRepository;
use App\Repository\ActivityRepository;
use App\Repository\LabInstanceRepository;
use App\Repository\HypervisorRepository;
use App\Repository\ControlProtocolTypeRepository;
use App\Repository\FlavorRepository;
use App\Repository\IsoRepository;
use App\Repository\OperatingSystemRepository;
use FOS\RestBundle\Context\Context;
use Remotelabz\Message\Message\InstanceActionMessage;
use JMS\Serializer\SerializerInterface;
use App\Exception\NotInstancedException;
use JMS\Serializer\SerializationContext;
use Doctrine\Common\Collections\Criteria;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\RequestException;
use App\Exception\AlreadyInstancedException;
use Symfony\Component\HttpFoundation\Request;
use App\Service\Lab\LabImporter;
use App\Service\LabBannerFileUploader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Post;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Delete;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Route as RestRoute;
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
use Symfony\Component\Security\Http\Attribute\Security;

class TemplateController extends Controller
{
    /** @var LoggerInterface $logger */
    private $logger;

    public function __construct(
        LoggerInterface $logger,
        operatingSystemRepository $operatingSystemRepository,
        HypervisorRepository $hypervisorRepository,
        ControlProtocolTypeRepository $controlProtocolTypeRepository,
        FlavorRepository $flavorRepository,
        DeviceRepository $deviceRepository,
        IsoRepository $isoRepository
        )
    {
        $this->logger = $logger;
        $this->operatingSystemRepository = $operatingSystemRepository;
        $this->hypervisorRepository = $hypervisorRepository;
        $this->controlProtocolTypeRepository = $controlProtocolTypeRepository;
        $this->flavorRepository = $flavorRepository;
        $this->deviceRepository = $deviceRepository;
        $this->isoRepository = $isoRepository;
    }
    
	#[Post('/api/list/templates', name: 'api_get_templates')]
	#[Security("is_granted('ROLE_TEACHER') or is_granted('ROLE_ADMINISTRATOR')", message: "Access denied.")]
    public function indexAction(Request $request)
    {

        $data = json_decode($request->getContent(), true);
        $this->logger->debug("[TemplateController:indexAction]::request data :",$data);

        $this->cleanOrphanedTemplateFiles();
        
        $templates = $this->deviceRepository->findByTemplate(true);
        foreach ($templates as $template) {
            //This device is used by any device lab
            if (count($template->getLabsUsingThisTemplate())==0) {
                if (!is_file($this->getParameter('kernel.project_dir').'/config/templates/'.$template->getId().'-'.u($template->getName())->camel().'.yaml')) {
                    $this->newAction($template);
                 }
            }
        }
        $node_templates = Array();
        $node_config = Array();
        foreach ( scandir($this->getParameter('kernel.project_dir').'/config/templates/') as $element ) {
                if (is_file($this->getParameter('kernel.project_dir').'/config/templates/'.$element) 
                    && preg_match('/^.+\.yaml$/', $element) 
                    && $element != 'docker.yaml') {
                        $cur_name = preg_replace('/.yaml/','',$element ) ;
                        $cur_templ = Yaml::parse(file_get_contents($this->getParameter('kernel.project_dir').'/config/templates/'.$element));
                        $this->logger->debug("[TemplateController:indexAction]::template file :",$cur_templ);
                        if (isset($cur_templ["virtuality"]) && $cur_templ['virtuality'] == $data["virtuality"]){
                            if ( isset($cur_templ['description']) ) {
                                $node_templates[$cur_name] =  $cur_templ['description'] ;
                            }
                            if ( isset($cur_templ['config_script']) ) {
                                    $node_config[$cur_name] =  $cur_templ['config_script'] ;
                            }
                            $this->logger->debug("[TemplateController:indexAction]:: Template in the yaml file :".$cur_templ['description']);
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
        $this->logger->debug("[TemplateController:indexAction]:: Response json :".$response);

        return $response;
    }

    
	#[Post('/api/list/templates/{id<\d+>}', name: 'api_get_template')]
	#[Security("is_granted('ROLE_USER') or is_granted('ROLE_GUEST')", message: "Access denied.")]
    public function showAction(
        int $id,
        Request $request)
    {
        $data = json_decode($request->getContent(), true);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $this->logger->debug("[TemplateController:showAction]::Device id request : ".$id);

        $device = $this->deviceRepository->find($id);

        if (!$device) {
            throw new NotFoundHttpException("Device Template " . $id . " does not exist.");
        }
        
        $deviceName = u($device->getName())->camel();
 
        if (!is_file($this->getParameter('kernel.project_dir').'/config/templates/'.$id.'-'.$deviceName.'.yaml')) {
        //if (!is_file($this->getParameter('kernel.project_dir').'/config/templates/'.$deviceName.'.yaml')) {
            $this->logger->debug("[TemplateController:showAction]::Template file doesn't exist for device id ".$id."and name ".$deviceName);
            $this->newAction($device);
        }
        $p = Yaml::parse(file_get_contents($this->getParameter('kernel.project_dir').'/config/templates/'.$id.'-'.$deviceName.'.yaml'));
        //$p = Yaml::parse(file_get_contents($this->getParameter('kernel.project_dir').'/config/templates/'.$deviceName.'.yaml'));
        $p['template'] = $id."-".$deviceName;
        
        $this->logger->debug("[TemplateController:showAction]::File contents",$p);

        if (!isset($p['context']) || !isset($p['template'])) {

            $response->setContent(json_encode([
                'code' => 400,
                'status'=>'fail',
                'message' => 'Requested template is not valid (60033).'
                ])
            );
            return $response;
        }
       
        if (!isset($p['virtuality']) || ($p['virtuality'] != $data['virtuality'])) {
            $response->setContent(json_encode([
                'code' => 403,
                'status' => 'forbidden',
                'message' => 'Template virtuality is not equal to lab virtuality.'
            ]));
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

            $data['options']['networkInterfaceTemplate'] = Array(
                'name' => 'Network interface template',
                'type' => 'input',
                'value' => $p['networkInterfaceTemplate'] ?? 'eth'
            );

            $data['options']['flavor'] = Array(
                'name' => 'Ram',
                'type' => 'list',
                'multiple'=> false,
                'value' => $p['flavor'] ?? '',
                'list' => $this->listFlavors()
            );

            $data['options']['bios_type'] = Array(
                'name' => 'Bios Type',
                'type' => 'list',
                'multiple'=> false,
                'value' => $p['bios_type'] ?? '',
                'list' => ["BIOS","UEFI"]
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
                'list' => $this->listOperatingSystems($p['virtuality'])
            );

            $data['options']['controlProtocol'] = Array(
                'name' => 'Control Protocol',
                'type' => 'list',
                'multiple'=> true,
                'value' => $p['controlProtocol'] ?? '',
                'list' => $this->listControlProtocolTypes()
            );

            $data['options']['template'] = Array(
                'name' => 'Template',
                'type' => 'boolean',
                'value' => $p['template'] ?? ''
            );

            if (!empty($p['isos'])) {
                $data['options']['ISO'] = Array(
                    'name' => 'ISO',
                    'type' => 'list',
                    'multiple'=> true,
                    'value' => $p['isos'],
                    'list' => $this->listIsos($p['isos'])
                );
            }
        
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
        foreach (scandir($this->getParameter('kernel.project_dir').'/assets/images/icons') as $filename) {
            if (is_file($this->getParameter('kernel.project_dir').'/assets/images/icons/'.$filename) && preg_match('/^.+\.[png$\|jpg$]/', $filename)) {
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
        foreach (scandir($this->getParameter('kernel.project_dir').'/assets/js/components/Editor2/configs') as $filename) {
            if (is_file($this->getParameter('kernel.project_dir').'/assets/js/components/Editor2/configs/'.$filename) && preg_match('/^.+\.php$/', $filename)) {
                $patterns[0] = '/^(.+)\.php$/';  // remove extension
                $replacements[0] = '$1';
                $name = preg_replace($patterns, $replacements, $filename);
                $results[$filename] = $name;
            }
        }
        return $results;
    }


    public function listIsos($Isos) {
        $IsosList= [];
            foreach($Isos as $iso){
                $iso_image=$this->isoRepository->find($iso);
                $IsosList[$iso_image->getId()] = $iso_image->getName();
            }
        return $IsosList;
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

    public function listOperatingSystems($virtuality) {

        $operatingSystemList= [];
    $operatingSystems = $this->operatingSystemRepository->findByVirtuality($virtuality);
    foreach($operatingSystems as $operatingSystem){
        // Ajouter le nom de l'hyperviseur entre parenthèses
        $operatingSystemList[$operatingSystem->getId()] = $operatingSystem->getName() . ' (' . $operatingSystem->getHypervisor()->getName() . ')';
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
        
        $isos=[];
        foreach($template->getIsos() as $iso) {
            $id=$iso->getId();
            $this->logger->debug("[TemplateController:newAction]::Add iso id ".$id);
            array_push($isos, $id);
        }
        if ($isos == []) {
            $isos = '';
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
        "bios_type" => $template->getBiosType(),
        "description" => $template->getName(),
        "networkInterfaceTemplate" => $template->getNetworkInterfaceTemplate(),
        "cpu" => $template->getNbCpu(),
        "core" => $template->getNbCore(),
        "socket" => $template->getNbSocket(),
        "thread" => $template->getNbSocket(),
        "context" => "remotelabz",
        "config_script" => "embedded",
        "ethernet" => 1,
        "virtuality" => $template->getVirtuality(),
        "isos" => $isos
    ];

    $yamlContent = Yaml::dump($templateData,2);

    //Modify this line to delete the id in the beginning of the file name.
    // TODO : try to know if the id is needed in the file name
    $this->logger->debug("[TemplateController:newAction]::template created");
    file_put_contents($this->getParameter('kernel.project_dir')."/config/templates/".$template->getId()."-". u($template->getName())->camel() . ".yaml", $yamlContent);
    //file_put_contents($this->getParameter('kernel.project_dir')."/config/templates/".u($template->getName())->camel() . ".yaml", $yamlContent);
    }

    /**
     * Clean orphaned YAML template files
     * Removes YAML files where the device ID no longer exists in database
     * 
     * @return array Statistics about cleaned files
     */
    public function cleanOrphanedTemplateFiles(): array
    {
        $templateDir = $this->getParameter('kernel.project_dir').'/config/templates/';
        $stats = [
            'scanned' => 0,
            'deleted' => 0,
            'errors' => [],
            'kept' => 0
        ];
        
        if (!is_dir($templateDir)) {
            $this->logger->warning("[TemplateController::cleanOrphanedTemplateFiles] Template directory does not exist: " . $templateDir);
            return $stats;
        }
        
        foreach (scandir($templateDir) as $filename) {
            // Skip . and .. and non-yaml files
            if ($filename === '.' || $filename === '..' || !preg_match('/^(\d+)-.+\.yaml$/', $filename, $matches)) {
                continue;
            }
            
            $stats['scanned']++;
            $deviceId = (int)$matches[1];
            
            $this->logger->debug("[TemplateController::cleanOrphanedTemplateFiles] Checking file: {$filename} (device ID: {$deviceId})");
            
            // Check if device exists in database
            $device = $this->deviceRepository->find($deviceId);
            
            if (!$device) {
                // Device doesn't exist - delete the file
                $filepath = $templateDir . $filename;
                
                try {
                    if (unlink($filepath)) {
                        $this->logger->info("[TemplateController::cleanOrphanedTemplateFiles] Deleted orphaned template file: {$filename}");
                        $stats['deleted']++;
                    } else {
                        $error = "Failed to delete file: {$filename}";
                        $this->logger->error("[TemplateController::cleanOrphanedTemplateFiles] " . $error);
                        $stats['errors'][] = $error;
                    }
                } catch (\Exception $e) {
                    $error = "Exception while deleting {$filename}: " . $e->getMessage();
                    $this->logger->error("[TemplateController::cleanOrphanedTemplateFiles] " . $error);
                    $stats['errors'][] = $error;
                }
            } else {
                $stats['kept']++;
                $this->logger->debug("[TemplateController::cleanOrphanedTemplateFiles] Keeping file: {$filename} (device exists)");
            }
        }
        
        $this->logger->info("[TemplateController::cleanOrphanedTemplateFiles] Cleanup complete", $stats);
        
        return $stats;
    }


}
