<?php

namespace App\Service\EveNg;

use App\Entity\EveNgImageMapping;
use App\Entity\OperatingSystem;
use App\Entity\User;
use App\Repository\EveNgImageMappingRepository;
use App\Repository\OperatingSystemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * CRUD du glossaire partage EVE-NG <-> RemoteLabz (table eve_ng_image_mapping).
 *
 * Le glossaire memorise la decision prise par un admin pour chaque image
 * EVE-NG rencontree dans un .unl, afin de reutiliser automatiquement ce choix
 * aux imports suivants (idempotence).
 */
class ImageCatalog
{
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;
    private EveNgImageMappingRepository $mappingRepository;
    private OperatingSystemRepository $operatingSystemRepository;

    public function __construct(
        LoggerInterface $logger,
        EntityManagerInterface $entityManager,
        EveNgImageMappingRepository $mappingRepository,
        OperatingSystemRepository $operatingSystemRepository
    ) {
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->mappingRepository = $mappingRepository;
        $this->operatingSystemRepository = $operatingSystemRepository;
    }

    /**
     * Retourne toutes les correspondances memorisees, indexees par image EVE-NG.
     *
     * @return array<string, EveNgImageMapping>
     */
    public function all(): array
    {
        $mappings = [];

        foreach ($this->mappingRepository->findAll() as $mapping) {
            $mappings[$mapping->getEveImage()] = $mapping;
        }

        return $mappings;
    }

    /**
     * Retourne les correspondances memorisees pour un lot d'images EVE-NG.
     *
     * @param string[] $eveImages
     *
     * @return array<string, EveNgImageMapping>
     */
    public function findForImages(array $eveImages): array
    {
        $mappings = [];

        foreach ($this->mappingRepository->findByEveImages($eveImages) as $mapping) {
            $mappings[$mapping->getEveImage()] = $mapping;
        }

        return $mappings;
    }

    public function findForImage(string $eveImage): ?EveNgImageMapping
    {
        return $this->mappingRepository->findOneByEveImage($eveImage);
    }

    /**
     * Enregistre (ou met a jour) la correspondance d'une image EVE-NG vers un OS.
     */
    public function remember(string $eveImage, OperatingSystem $operatingSystem, ?User $user = null): EveNgImageMapping
    {
        $mapping = $this->mappingRepository->findOneByEveImage($eveImage);

        if (null === $mapping) {
            $mapping = new EveNgImageMapping();
            $mapping->setEveImage($eveImage);
        }

        $mapping->setOperatingSystem($operatingSystem);
        if (null !== $user) {
            $mapping->setCreatedBy($user);
        }

        $this->entityManager->persist($mapping);
        $this->entityManager->flush();

        $this->logger->info('[ImageCatalog:remember]::Learnt EVE-NG image mapping', [
            'eve_image' => $eveImage,
            'os_id' => $operatingSystem->getId(),
        ]);

        return $mapping;
    }

    /**
     * Applique un lot de decisions { eve_image: os_id } au glossaire.
     *
     * @param array<string, int> $decisions
     */
    public function rememberMany(array $decisions, ?User $user = null): int
    {
        $count = 0;

        foreach ($decisions as $eveImage => $osId) {
            if (null === $osId || null === $operatingSystem = $this->operatingSystemRepository->find($osId)) {
                continue;
            }

            $this->remember($eveImage, $operatingSystem, $user);
            ++$count;
        }

        return $count;
    }

    public function forget(string $eveImage): bool
    {
        $mapping = $this->mappingRepository->findOneByEveImage($eveImage);

        if (null === $mapping) {
            return false;
        }

        $this->entityManager->remove($mapping);
        $this->entityManager->flush();

        return true;
    }
}
