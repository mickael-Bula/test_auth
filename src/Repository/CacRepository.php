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
     * Retourne toutes les cotations du Cac, ainsi que le cours de clôture du Lvc
     * L'affichage présente les données les plus récentes
     *
     * @return mixed
     */
    public function getCacAndLvcData(): mixed
    {
        $results = $this->createQueryBuilder('c')
            ->select('c.createdAt', 'c.closing', 'c.opening', 'c.higher', 'c.lower', 'l.closing AS lvcClosing')
            ->join(Lvc::class, 'l', 'WITH', 'c.createdAt = l.createdAt')
            ->orderBy('c.createdAt', 'desc')
            ->getQuery()
            ->getResult();

        // Formate les dates après récupération des résultats
        foreach ($results as &$result) {
            $result['createdAt'] = $result['createdAt']->format('d/m/Y');
        }

        return $results;
    }
}
