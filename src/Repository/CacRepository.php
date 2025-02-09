<?php

namespace App\Repository;

use App\Entity\Cac;
use App\Entity\Lvc;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cac>
 */
class CacRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cac::class);
    }

    /**
     * Retourne toutes les cotations du Cac, ainsi que le cours de clôture du Lvc.
     * L'affichage présente les données les plus récentes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCacAndLvcData(): mixed
    {
        return $this->createQueryBuilder('c')
            ->select('c.createdAt', 'c.closing', 'c.opening', 'c.higher', 'c.lower', 'l.closing AS lvcClosing')
            ->join(Lvc::class, 'l', 'WITH', 'c.createdAt = l.createdAt')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère toutes les entités cac qui ont une date supérieure à celle de $lastCacUpdated, triées par ancienneté.
     *
     * @return Cac[]
     */
    public function getDataToUpdateFromUser(Cac $lastCacUpdated): array
    {
        return $this->createQueryBuilder('cac')
            ->where('cac.createdAt > :date')
            ->setParameter('date', $lastCacUpdated->getCreatedAt())
            ->orderBy('cac.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
