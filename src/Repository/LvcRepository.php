<?php

namespace App\Repository;

use App\Entity\Lvc;
use App\Entity\Position;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lvc>
 */
class LvcRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lvc::class);
    }

    /**
     * Calcule la valorisation des LVC en cours.
     */
    public function getLvcClosingAndTotalQuantity(): float|int
    {
        $qb = $this->createQueryBuilder('l');

        // Sous-requête pour récupérer le dernier ID
        $subQbMaxId = $this->createQueryBuilder('l');
        $subQbMaxId->select('MAX(lvc2.id)')
            ->from(Lvc::class, 'lvc2');

        // Requête principale
        $lvcClosing = $qb->select('lvc.closing')
            ->from(Lvc::class, 'lvc')
            ->where($qb->expr()->eq('lvc.id', $subQbMaxId->getDQL()))
            ->getQuery()
            ->getSingleScalarResult();

        // Sous-requête pour la quantité totale
        $subQbTotalQuantity = $this->createQueryBuilder('p');
        $subQbTotalQuantity->select('SUM(p.quantity)')
            ->from(Position::class, 'p')
            ->where('p.is_running = true');

        $totalQuantity = $subQbTotalQuantity->getQuery()->getSingleScalarResult();

        return round($lvcClosing * $totalQuantity, 2);
    }
}
