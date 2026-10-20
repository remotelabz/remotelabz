<?php

namespace App\Repository;

use App\Entity\EveNgImageMapping;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EveNgImageMapping>
 *
 * @method EveNgImageMapping|null find($id, $lockMode = null, $lockVersion = null)
 * @method EveNgImageMapping|null findOneBy(array $criteria, array $orderBy = null)
 * @method EveNgImageMapping[]    findAll()
 * @method EveNgImageMapping[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EveNgImageMappingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EveNgImageMapping::class);
    }

    /**
     * @return EveNgImageMapping[]
     */
    public function findByEveImages(array $eveImages): array
    {
        if (empty($eveImages)) {
            return [];
        }

        return $this->createQueryBuilder('m')
            ->andWhere('m.eveImage IN (:images)')
            ->setParameter('images', $eveImages)
            ->getQuery()
            ->getResult();
    }

    public function findOneByEveImage(string $eveImage): ?EveNgImageMapping
    {
        return $this->findOneBy(['eveImage' => $eveImage]);
    }
}
