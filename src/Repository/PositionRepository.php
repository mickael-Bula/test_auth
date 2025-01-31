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
     * @return Position[][]
     */
    public function getUserPositions(int $userId): array
    {
        $result = [];
        foreach (['isWaiting', 'isRunning', 'isClosed'] as $status) {
            $result[] = $this->findBy(['userPosition' => $userId, $status => true]);
        }

        return $result;
    }

    /**
     * Récupère les positions en attente qui ont une buyLimit différente de celle de la position courante.
     *
     * @return Position[]|null
     */
    public function getIsWaitingPositionsByBuyLimitID(Position $position): ?array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isWaiting = true')
            ->andWhere('p.buyLimit <> :buyLimit')
            ->setParameter('buyLimit', $position->getBuyLimit())
            ->orderBy('p.buyLimit', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le PRU et le formate avec deux décimales.
     */
    public function getPriceEarningRatio(int $userId, string $status): float
    {
        // Vérifie la présence du champ pour éviter les injections ou les erreurs
        if (!in_array($status, ['isRunning', 'isWaiting'], true)) {
            throw new \InvalidArgumentException('Champ `status` invalide : '.$status);
        }

        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.quantity) AS total', 'SUM(p.lvcBuyTarget) / COUNT(p.quantity) AS pru')
            ->where('p.userPosition = :user')
            ->andWhere('p.'.$status.' = true')
            ->setParameter('user', $userId)
            ->getQuery()
            ->getSingleResult();

        // Formatage hors de la requête pour améliorer la portabilité.
        $result['pru'] = number_format((float) $result['pru'], 2);

        return $result;
    }
}
