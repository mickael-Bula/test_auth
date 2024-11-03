<?php

namespace App\Repository;

use App\Entity\Position;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Position>
 */
class PositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Position::class);
    }

    /**
     * @param $user
     * @return array
     */
    public function getUserPositions($user): array
    {
        $result = [];
        foreach (['isWaiting', 'isRunning', 'isClosed'] as $status) {
            $result[] = $this->findBy(["userPosition" => $user, $status => true]);
        }

        return $result;
    }

    /**
     * Récupère les positions en attente qui ont une buyLimit différente de celle de la position courante
     */
    public function getIsWaitingPositionsByBuyLimitID(Position $position): ?array
    {
        return $this
            ->createQueryBuilder('p')
            ->where('p.isWaiting = true')
            ->andWhere('p.buyLimit <> :buyLimit')
            ->setParameter('buyLimit', $position->getBuyLimit())
            ->orderBy('p.buyLimit', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
