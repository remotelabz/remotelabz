<?php

namespace App\Repository;

use App\Entity\ChatMessage;
use App\Entity\Lab;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatMessage>
 */
class ChatMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatMessage::class);
    }

    /**
     * @return ChatMessage[]
     */
    public function findLatest(Lab $lab, ?int $beforeId = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.lab = :lab')
            ->setParameter('lab', $lab)
            ->orderBy('m.id', 'DESC')
            ->setMaxResults($limit);

        if (null !== $beforeId) {
            $qb->andWhere('m.id < :beforeId')->setParameter('beforeId', $beforeId);
        }

        $messages = $qb->getQuery()->getResult();

        return array_reverse($messages);
    }
}
