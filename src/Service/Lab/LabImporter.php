<?php

namespace App\Service\Lab;

use App\Entity\Device;
use App\Entity\EditorData;
use App\Entity\TextObject;
use App\Entity\Picture;
use App\Entity\Lab;
use App\Entity\NetworkInterface;
use App\Entity\NetworkSettings;
use App\Entity\OperatingSystem;
use App\Repository\FlavorRepository;
use App\Repository\HypervisorRepository;
use App\Repository\LabRepository;
use App\Repository\OperatingSystemRepository;
use App\Repository\ControlProtocolTypeRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Filesystem\Filesystem;
use function Symfony\Component\String\u;
use App\Service\Lab\BannerManager;

class LabImporter
{
    protected $logger;
    protected $serializer;
    protected $tokenStorage;
    protected $entityManager;
    protected $labRepository;
    protected $flavorRepository;
    protected $operatingSystemRepository;
    protected $bannerManager;
    private $rootDirectory;
    private $publicImageDirectory;
    private $bannerDirectory;

    public function __construct(
        LoggerInterface $logger,
        LabRepository $labRepository,
        SerializerInterface $serializer,
        FlavorRepository $flavorRepository,
        TokenStorageInterface $tokenStorage,
        EntityManagerInterface $entityManager,
        OperatingSystemRepository $operatingSystemRepository,
        HypervisorRepository $hypervisorRepository,
        ControlProtocolTypeRepository $controlProtocolTypeRepository,
        BannerManager $bannerManager,
        string $rootDirectory,
        string $publicImageDirectory,
        string $bannerDirectory
    ) {
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->tokenStorage = $tokenStorage;
        $this->entityManager = $entityManager;
        $this->labRepository = $labRepository;
        $this->flavorRepository = $flavorRepository;
        $this->operatingSystemRepository = $operatingSystemRepository;
        $this->hypervisorRepository = $hypervisorRepository;
        $this->controlProtocolTypeRepository = $controlProtocolTypeRepository;
        $this->bannerManager = $bannerManager;
        $this->rootDirectory = $rootDirectory;
        $this->publicImageDirectory = $publicImageDirectory;
        $this->bannerDirectory = $bannerDirectory;
    }

    /**
     * Returns a JSON representation of the lab.
     * 
     * @param \App\Entity\Lab $lab 
     */
    public function export(Lab $lab)
    {
        $context = SerializationContext::create();
        $context->setGroups(['export_lab']);
        return $this->serializer->serialize($lab, 'json', $context);
    }

    /**
     * Creates a new lab using a JSON representation
     * 
     * @param string $json JSON data of the lab
     */
    public function import(string $json): int
    {
        $labJson = json_decode($json, true, 4096, JSON_OBJECT_AS_ARRAY);

        if (!is_array($labJson)) {
            // invalid json
            $this->logger->error("Invalid JSON was provided!", ["string" => $json]);

            throw new InvalidArgumentException("Invalid JSON was provided!");
        }

        $lab = new Lab();
        if (array_key_exists("description",$labJson)) {
            $this->logger->debug("Lab description found");
            $lab->setDescription($labJson['description']);
        }
        else {
            $this->logger->debug("No lab description found");
            $lab->setDescription("");        
        }

        if (array_key_exists("shortDescription",$labJson)) {
            $this->logger->debug("Lab short description found");
            $lab->setShortDescription($labJson['shortDescription']);
        }
        else {
            $this->logger->debug("No lab short description found");
            $lab->setShortDescription("");
        }
        if (array_key_exists("textobjects",$labJson)) {
            $this->logger->debug("Lab textobjects found");
            foreach ($labJson['textobjects'] as $textobject) {
                $newTextObject = new TextObject();
                $newTextObject->setName($textobject['name']);
                $newTextObject->setType($textobject['type']);
                $newTextObject->setData($textobject['data']);
                $newTextObject->setLab($lab);
                $this->entityManager->persist($newTextObject);
                $lab->addTextobject($newTextObject);
            }
        }
        if (array_key_exists("pictures",$labJson)) {
            $this->logger->debug("Lab pictures found");
            foreach ($labJson['pictures'] as $picture) {
                $newPicture = new Picture();
                $newPicture->setName($picture['name']);
                $newPicture->setHeight($picture['height']);
                $newPicture->setWidth($picture['width']);
                $newPicture->setMap($picture['map']);
                $newPicture->setType($picture['type']);
                $newPicture->setLab($lab);
                
                $type = explode("image/",$picture['type'])[1];
                $fileName = $this->rootDirectory.'/assets/js/components/Editor2/images/pictures/lab'.$labJson['id'].'-'.$picture['name'].'.'.$type;
                $fp = fopen($fileName, 'r');
                $size = filesize($fileName);
                if ($fp !== False) {
                    $data = fread($fp, $size);
                    $newPicture->setData($data);
                }
                $this->entityManager->persist($newPicture);
                $lab->addPicture($newPicture);
            }
        }
        $lab
            ->setName($labJson['name'])
            ->setAuthor($this->tokenStorage->getToken()->getUser())
            ->setIsTemplate(false)
            ->setVirtuality($labJson['virtuality'])
            //->setDescription($labJson['description'])
            //->setShortDescription($labJson['shortDescription'])
            ->setIsInternetAuthorized(true)
        ;

        if ($labJson["hasTimer"] && array_key_exists("timer",$labJson)) {
            $lab
                ->setHasTimer($labJson["hasTimer"])
                ->setTimer($labJson["timer"]);
        }


        foreach ($labJson['devices'] as $deviceJson) {
            //$this->logger->debug('Lab Json', $deviceJson);
            // find similar operating system
            $operatingSystemJson = $deviceJson['operatingSystem'];
            $criteria = Criteria::create()
                ->where(Criteria::expr()->eq('imageUrl', $operatingSystemJson['image']))
                ->orWhere(Criteria::expr()->eq('imageFilename', $operatingSystemJson['image']))
            ;

            $results = $this->operatingSystemRepository->matching($criteria);

            if (count($results) > 0) {
                $operatingSystem = $results[0];

                $this->logger->info('Found similar operating system. Using this instead of creating a new one.', ['ID' => $operatingSystem->getId()]);
            } else {
                $this->logger->info('No similar operating system found. Creating a new one.');

                $operatingSystem = new OperatingSystem();
                if (str_contains($operatingSystemJson['image'], '/')) {
                    $operatingSystem->setImageUrl($operatingSystemJson['image']);
                }
                else {
                    $operatingSystem->setImageFilename($operatingSystemJson['image']);
                }
                $operatingSystem
                    ->setName($operatingSystemJson['name'])
                    ->setImage($operatingSystemJson['image'])
                ;
                

                $this->entityManager->persist($operatingSystem);
            }

            // find similar flavor
            $flavorJson = $deviceJson['flavor'];
            $results = $this->flavorRepository->findBy([
                'memory' => $flavorJson['memory'],
                'disk' => $flavorJson['disk']
            ]);

            if (count($results) > 0) {
                $flavor = $results[0];

                $this->logger->info('Found similar flavor. Using this instead of creating a new one.', ['ID' => $flavor->getId()]);
            } else {
                $this->logger->info('No similar flavor found. Creating a new one.');

                $flavor = new OperatingSystem();
                $flavor
                    ->setName($flavorJson['name'])
                    ->setImage($flavorJson['image'])
                ;

                $this->entityManager->persist($flavor);
            }

            $editorDataJson = $deviceJson['editorData'];
            $editorData = new EditorData();
            $editorData
                ->setX($editorDataJson['x'])
                ->setY($editorDataJson['y'])
            ;

            $this->entityManager->persist($editorData);

            if (!$hypervisor = $this->hypervisorRepository->find($deviceJson['hypervisor']['id'])) {
                $hypervisor = NULL;
            }

            // creating device
            $device = new Device();

            $device
                ->setName($deviceJson['name'])
                ->setAuthor($this->tokenStorage->getToken()->getUser())
                ->setBrand($deviceJson['brand'])
                ->setModel($deviceJson['model'])
                ->setType($deviceJson['type'])
                ->setIcon($deviceJson['icon'])
                ->setVirtuality($deviceJson['virtuality'])
                ->setHypervisor($hypervisor)
                ->setNbCpu($deviceJson['nbCpu'])
                //->setVnc($deviceJson['vnc'])
                ->setOperatingSystem($operatingSystem)
                ->setFlavor($flavor)
                ->setEditorData($editorData)
                ->setIsTemplate(false)
                ->setNetworkInterfaceTemplate($deviceJson['networkInterfaceTemplate'])
            ;
            if (array_key_exists("template",$deviceJson)) {
                $device->setTemplate($deviceJson['template']);
            }

            
            foreach($deviceJson["controlProtocolTypes"] as $controlProtocolTypeJson){
                $controlProtocolType = $this->controlProtocolTypeRepository->find($controlProtocolTypeJson["id"]);
                $device->addControlProtocolType($controlProtocolType);
            }

            if(isset($deviceJson['nbSocket'])) {
                $device->setNbSocket($deviceJson['nbSocket']);
            }

            if(isset($deviceJson['nbCore'])) {
                $device->setNbCore($deviceJson['nbCore']);
            }

            if(isset($deviceJson['nbThread'])) {
                $device->setNbThread($deviceJson['nbThread']);
            }

            foreach ($deviceJson['networkInterfaces'] as $networkInterfaceJson) {
                $networkInterface = new NetworkInterface();

                $networkSettings = new NetworkSettings();
                $networkSettings
                    ->setName($networkInterfaceJson['name'] . '_settings');
                
                $this->entityManager->persist($networkSettings);

                $networkInterface
                    ->setName($networkInterfaceJson['name'])
                    ->setType($networkInterfaceJson['type'])
                    ->setVlan($networkInterfaceJson['vlan'])
                    ->setConnection($networkInterfaceJson['connection'])
                    ->setConnectorType($networkInterfaceJson['connectorType'])
                    ->setSettings($networkSettings)
                    ->setIsTemplate(false)
                ;

                if(isset($networkInterfaceJson['connectorLabel'])) {
                    $networkInterface->setConnectorLabel($networkInterfaceJson['connectorLabel']);
                }
                
                $device->addNetworkInterface($networkInterface);
                $this->entityManager->persist($networkInterface);
            }

            $lab->addDevice($device);
            $this->entityManager->persist($device);

            $editorData->setDevice($device);
            $this->entityManager->persist($editorData);
        }

        $this->entityManager->persist($lab);
        $this->entityManager->flush();
        if (array_key_exists("id",$labJson)) {
            $this->bannerManager->copyBanner($labJson['id'], $lab->getId());
        }
        else {
            $filesystem = new Filesystem();
            try {
                $src=$this->publicImageDirectory.'/logo/nopic.jpg';
                $dst=$this->bannerDirectory.'/'.$lab->getId().'/nopic.jpg';
            $filesystem->copy($src,$dst);
            $this->logger->debug("Copy from ".$src." to ".$dst);
            $lab->setBanner('nopic.jpg');
            $this->entityManager->flush();
            }
            catch (IOExceptionInterface $exception) {
                $this->logger->error("An error occurred while creating your directory at ".$exception->getPath());
            }
        }
        foreach($lab->getPictures() as $picture) {
            $type = explode("image/",$picture->getType())[1];
            file_put_contents($this->rootDirectory.'/assets/js/components/Editor2/images/pictures/lab'.$lab->getId().'-'.$picture->getName().'.'.$type, $picture->getData());
        }

        $createdLab = $this->labRepository->findOneBy(['uuid' => $lab->getUuid()]);

        return $createdLab->getId();
    }
}