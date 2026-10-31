<?php

namespace App\Service\Lab\Unl;

use App\Entity\ControlProtocolType;
use App\Entity\Device;
use App\Entity\Flavor;
use App\Entity\Hypervisor;
use App\Entity\OperatingSystem;
use App\Entity\User;
use App\Repository\ControlProtocolTypeRepository;
use App\Repository\DeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

use function Symfony\Component\String\u;

/**
 * Cree (ou reutilise) un "device template" RemoteLabz a partir des parametres
 * d'un noeud EVE-NG, lorsqu'aucune image existante ne correspond.
 *
 * Un device template est un `Device` marque `isTemplate=true` accompagne d'un
 * fichier YAML dans `config/templates/<id>-<CamelName>.yaml`. Les devices
 * importes referencent ce template via `Device.template`.
 *
 * La generation du YAML reprend exactement la structure produite par
 * DeviceController::newAction() / TemplateController::newAction() afin que le
 * template soit exploitable par l'editeur et le worker.
 */
class DeviceTemplateFactory
{
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;
    private DeviceRepository $deviceRepository;
    private ControlProtocolTypeRepository $controlProtocolTypeRepository;
    private string $projectDirectory;

    public function __construct(
        LoggerInterface $logger,
        EntityManagerInterface $entityManager,
        DeviceRepository $deviceRepository,
        ControlProtocolTypeRepository $controlProtocolTypeRepository,
        string $projectDirectory
    ) {
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->deviceRepository = $deviceRepository;
        $this->controlProtocolTypeRepository = $controlProtocolTypeRepository;
        $this->projectDirectory = $projectDirectory;
    }

    /**
     * Cree le device template si aucun template du meme nom et du meme OS
     * n'existe, sinon reutilise le plus recent (et regenere son YAML au besoin).
     *
     * @param int[] $controlProtocolIds
     *
     * @return array{template: string, device_id: int}
     */
    public function findOrCreate(
        string $name,
        string $deviceType,
        ?Hypervisor $hypervisor,
        OperatingSystem $operatingSystem,
        ?Flavor $flavor,
        int $cpu,
        string $icon,
        array $controlProtocolIds = [],
        ?User $author = null,
        string $networkInterfaceTemplate = 'eth'
    ): array {
        $template = $this->findExisting($name, $operatingSystem);
        if (null === $template) {
            $template = $this->create(
                $name,
                $deviceType,
                $hypervisor,
                $operatingSystem,
                $flavor,
                $cpu,
                $icon,
                $controlProtocolIds,
                $author,
                $networkInterfaceTemplate
            );
        }

        $deviceId = $template->getId();
        $this->writeYaml($template);

        return [
            'template' => $this->templateIdentifier($template),
            'device_id' => (int) $deviceId,
        ];
    }

    /**
     * Retrouve un device template existant par nom + OS (le plus recent).
     */
    public function findExisting(string $name, OperatingSystem $operatingSystem): ?Device
    {
        foreach ($this->deviceRepository->findByNameByTemplate($name, true) as $device) {
            if (null !== $device->getOperatingSystem()
                && $device->getOperatingSystem()->getId() === $operatingSystem->getId()) {
                return $device;
            }
        }

        return null;
    }

    private function create(
        string $name,
        string $deviceType,
        ?Hypervisor $hypervisor,
        OperatingSystem $operatingSystem,
        ?Flavor $flavor,
        int $cpu,
        string $icon,
        array $controlProtocolIds,
        ?User $author,
        string $networkInterfaceTemplate
    ): Device {
        $device = new Device();
        $device
            ->setName($name)
            ->setType($deviceType)
            ->setIcon($icon)
            ->setHypervisor($hypervisor)
            ->setOperatingSystem($operatingSystem)
            ->setFlavor($flavor)
            ->setNbCpu(max(1, $cpu))
            ->setNetworkInterfaceTemplate($networkInterfaceTemplate)
            ->setVirtuality(1)
            ->setIsTemplate(true)
            ->setAuthor($author)
        ;

        foreach ($controlProtocolIds as $controlProtocolId) {
            $controlProtocolType = $this->controlProtocolTypeRepository->find((int) $controlProtocolId);
            if ($controlProtocolType instanceof ControlProtocolType) {
                $device->addControlProtocolType($controlProtocolType);
            }
        }

        $this->entityManager->persist($device);
        $this->entityManager->flush();

        $this->logger->info('[DeviceTemplateFactory:create]::Created device template from EVE-NG import', [
            'device_id' => $device->getId(),
            'name' => $name,
            'operating_system' => $operatingSystem->getName(),
        ]);

        return $device;
    }

    /**
     * Identifiant de template attendu par `Device.template` : `<id>-<CamelName>`.
     */
    private function templateIdentifier(Device $device): string
    {
        return $device->getId() . '-' . u((string) $device->getName())->camel();
    }

    /**
     * (Re)genere le fichier YAML du template (structure identique a
     * DeviceController::newAction), afin que le device soit instanciable.
     */
    private function writeYaml(Device $device): void
    {
        $controlProtocolTypes = [];
        foreach ($device->getControlProtocolTypes() as $controlProtocolType) {
            $controlProtocolTypes[] = $controlProtocolType->getId();
        }
        if ([] === $controlProtocolTypes) {
            $controlProtocolTypes = '';
        }

        $isos = [];
        foreach ($device->getIsos() as $iso) {
            $isos[] = $iso->getId();
        }
        if ([] === $isos) {
            $isos = '';
        }

        $operatingSystem = $device->getOperatingSystem();
        $arch = null !== $operatingSystem ? $operatingSystem->getArch() : null;

        $deviceData = [
            'name' => $device->getName(),
            'type' => $device->getType(),
            'icon' => $device->getIcon(),
            'operatingSystem' => null !== $operatingSystem ? $operatingSystem->getId() : null,
            'flavor' => null !== $device->getFlavor() ? $device->getFlavor()->getId() : null,
            'controlProtocol' => $controlProtocolTypes,
            'hypervisor' => null !== $device->getHypervisor() ? $device->getHypervisor()->getId() : null,
            'brand' => $device->getBrand(),
            'model' => $device->getModel(),
            'description' => $device->getName(),
            'networkInterfaceTemplate' => $device->getNetworkInterfaceTemplate(),
            'cpu' => $device->getNbCpu(),
            'core' => $device->getNbCore(),
            'socket' => $device->getNbSocket(),
            'thread' => $device->getNbSocket(),
            'context' => 'remotelabz',
            'config_script' => 'embedded',
            'ethernet' => 1,
            'virtuality' => 1,
            'other_options' => $device->getOtherOptions(),
            'isos' => $isos,
            'cdrom_bus_type' => $device->getCdromBusType(),
            'bios_type' => $device->getBiosType(),
            'network_card_type' => $device->getNetworkCardType(),
            'minimum_network_interfaces' => $device->getMinimumNetworkInterfaces(),
        ];

        if (null !== $arch) {
            $deviceData['qemu_arch'] = $arch->getId();
        } else {
            $deviceData['qemu_arch'] = $device->getIsoArch();
        }

        $directory = $this->projectDirectory . '/config/templates';
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $file = sprintf('%s/%d-%s.yaml', $directory, $device->getId(), u((string) $device->getName())->camel());
        file_put_contents($file, Yaml::dump($deviceData, 2));

        $this->logger->debug('[DeviceTemplateFactory:writeYaml]::Wrote device template YAML', ['file' => $file]);
    }
}
