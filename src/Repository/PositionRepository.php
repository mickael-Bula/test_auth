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
}
