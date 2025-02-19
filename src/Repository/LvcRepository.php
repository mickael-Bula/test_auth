<?php

namespace App\Repository;

use App\Entity\Lvc;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
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
    public function getLvcClosingAndTotalQuantity(): float|int|false|null
    {
        try {
            $sql = 'SELECT lvc.closing * '
                .'(SELECT SUM(quantity) FROM position WHERE position.is_running = true) AS total_quantity '
                .'FROM lvc WHERE id = (SELECT MAX(id) FROM lvc)';

            $result = $this
                ->getEntityManager()
                ->getConnection()
                ->executeQuery($sql, ['is_running' => true])
                ->fetchOne();
        } catch (Exception $e) {
            // TODO : Log de l'erreur
            echo $e->getMessage();

            return false;
        }

        return round($result, 2);
    }
}
