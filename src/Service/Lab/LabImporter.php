<?php

namespace App\Service\Lab;

use App\Entity\Device;
use App\Entity\EditorData;
use App\Entity\Lab;
use App\Entity\NetworkInterface;
use App\Entity\NetworkSettings;
use App\Entity\OperatingSystem;
use App\Repository\FlavorRepository;
use App\Repository\LabRepository;
use App\Repository\OperatingSystemRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class LabImporter
{
    protected $logger;
    protected $serializer;
    protected $tokenStorage;
    protected $entityManager;
    protected $labRepository;
    protected $flavorRepository;
    protected $operatingSystemRepository;

    public function __construct(
        LoggerInterface $logger,
        LabRepository $labRepository,
        SerializerInterface $serializer,
        FlavorRepository $flavorRepository,
        TokenStorageInterface $tokenStorage,
        EntityManagerInterface $entityManager,
        OperatingSystemRepository $operatingSystemRepository
    ) {
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->tokenStorage = $tokenStorage;
        $this->entityManager = $entityManager;
        $this->labRepository = $labRepository;
        $this->flavorRepository = $flavorRepository;
        $this->operatingSystemRepository = $operatingSystemRepository;
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

        $lab
            ->setName($labJson['name'])
            ->setAuthor($this->tokenStorage->getToken()->getUser())
            ->setDescription($labJson['description'])
            ->setShortDescription($labJson['shortDescription'])
            ->setIsInternetAuthorized(true)
        ;

        foreach ($labJson['devices'] as $deviceJson) {
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

            // creating device
            $device = new Device();

            $device
                ->setName($deviceJson['name'])
                ->setBrand($deviceJson['brand'])
                ->setModel($deviceJson['model'])
                ->setType($deviceJson['type'])
                ->setVirtuality($deviceJson['virtuality'])
                ->setHypervisor($deviceJson['hypervisor'])
                ->setVnc($deviceJson['vnc'])
                ->setOperatingSystem($operatingSystem)
                ->setFlavor($flavor)
                ->setEditorData($editorData)
                ->setIsTemplate(false);
            ;

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
                    ->setSettings($networkSettings)
                    ->setIsTemplate(false)
                ;

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

        $createdLab = $this->labRepository->findOneBy(['uuid' => $lab->getUuid()]);

        return $createdLab->getId();
    }
}