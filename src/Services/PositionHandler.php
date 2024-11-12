<?php

namespace App\Services;

use App\Entity\{Cac, LastHigh, Lvc, Position, User};
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class PositionHandler
{
    private EntityManagerInterface $entityManager;
    private Security $security;
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $entityManager, Security $security, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->logger = $logger;
    }

    /**
     * Récupère le User en BDD à partir de son id, en précisant à l'IDE que getId() se réfère à l'Entity User).
     */
    public function getCurrentUser(): User
    {
        /** @var User $user */
        $user = $this->security->getUser();

        return $this->entityManager->getRepository(User::class)->find($user->getId());
    }

    /**
     * Récupère la liste des entités cac à utiliser pour mettre à jour les positions de l'utilisateur courant
     */
    public function dataToCheck(): array
    {
        $cacRepository = $this->entityManager->getRepository(Cac::class);
        $user = $this->getCurrentUser();

        return $cacRepository->getDataToUpdateFromUser($user->getLastCacUpdated());
    }

    /**
     * Actualise le plus haut local et les positions d'une liste de données Cac.
     */
    public function updateCacData(array $cacData): void
    {
        $lvcRepository = $this->entityManager->getRepository(Lvc::class);

        foreach ($cacData as $cac) {
            $this->checkLastHigh($cac);

            // Mise à jour de la date représentant la dernière visite de l'utilisateur.
            $this->updateLastCac($cac);

            // Récupération du lvc contemporain au cac pour mise à jour des positions.
            $lvcData = $lvcRepository->findOneBy(["createdAt" => $cac->getCreatedAt()]);
            if ($lvcData) {
                $this->checkLvcData($lvcData);
            } else {
                $date = $cac->getCreatedAt() !== null ? $cac->getCreatedAt()->format("D/M/Y") : null;
                $this->logger->error(
                    sprintf("Pas de correspondance LVC pour le CAC fournit en date du %s", $date)
                );
            }
        }
    }

    /**
     * J'actualise la table LastHigh de l'utilisateur connecté si un nouveau plus haut a été réalisé.
     */
    public function checkLastHigh(Cac $cac): void
    {
        // je récupère le plus haut de l'utilisateur en session.
        $lastHighInDatabase = $this->getLastHigher();

        /* Si le résultat est 'null',
        je crée un 'last_high' en affectant par défaut le dernier plus haut du Cac et je crée les positions. */
        if (is_null($lastHighInDatabase)) {
            $lastHighInDatabase = $this->setHigher($cac);
        }
        // Si lastHigh a été dépassé, je l'actualise.
        if ($cac->getHigher() > $lastHighInDatabase->getHigher()) {
            $this->updateHigher($cac, $lastHighInDatabase);
        }
    }

    /**
     * Retourne le plus haut de l'utilisateur.
     */
    public function getLastHigher(): ?LastHigh
    {
        return $this->getCurrentUser()->getHigher();
    }

    /**
     * Méthode pour créer en BDD le nouveau plus haut de l'utilisateur courant.
     *
     * @param Cac $cac l'objet cac qui a fait le plus haut
     * @param array<Position> $positions
     * @return LastHigh
     */
    public function setHigher(Cac $cac, array $positions = []): LastHigh
    {
        // Je récupère l'utilisateur courant.
        $user = $this->getCurrentUser();

        // Je crée une nouvelle instance de LastHigh et je l'hydrate
        $lastHighEntity = $this->setBuyLimitToNewLastHigh($cac);

        // J'assigne ce plus haut à l'utilisateur courant et j'enregistre à nouveau en base.
        $user->setHigher($lastHighEntity);

        $this->entityManager->flush();

        // Je crée également les positions en rapport avec la nouvelle buyLimit.
        $this->setPositions($lastHighEntity, $positions);

        return $lastHighEntity;
    }

    /**
     * Méthode mettant à jour un plus haut existant en BDD.
     *
     * @param Cac $cac l'objet cac qui a fait le nouveau plus haut
     * @param LastHigh $lastHigh représente le plus haut à actualiser
     * @return void
     */
    public function updateHigher(Cac $cac, LastHigh $lastHigh): void
    {
        $positionsRepository = $this->entityManager->getRepository(Position::class);

        // J'actualise le plus haut.
        $newLastHigh = $this->setBuyLimitToNewLastHigh($cac, $lastHigh);

        // je mets à jour les positions en attente de l'utilisateur liées au lastHigh (via la buyLimit).
        $positions = $positionsRepository->findBy(
            [
                'userPosition' => $this->getCurrentUser(),
                'isWaiting' => true,
                'buyLimit' => $newLastHigh->getId()
            ]
        );
        $this->setPositions($newLastHigh, $positions);
    }

    /**
     * Affecte la buyLimit au nouveau plus haut.
     *
     * @param Cac $cac
     * @param LastHigh|null $lastHigh
     * @return LastHigh
     */
    public function setBuyLimitToNewLastHigh(Cac $cac, LastHigh $lastHigh = null): LastHigh
    {
        $lastHighRepository = $this->entityManager->getRepository(LastHigh::class);

        // Je récupère le plus haut de l'objet Cac transmis en paramètre.
        $lastHigher = $cac->getHigher();

        // Si un lastHigh est transmis, on le met à jour, sinon on en crée un nouveau.
        $lastHighEntity = $lastHigh ?? new LastHigh();

        $lastHighEntity->setHigher($lastHigher);
        $buyLimit = $lastHigher - ($lastHigher * Position::SPREAD);    // La buyLimit se situe 6 % sous higher.
        $lastHighEntity->setBuyLimit(round($buyLimit, 2));
        $lastHighEntity->setDailyCac($cac);

        // À partir de l'entité Cac, je récupère l'objet LVC contemporain.
        $lvcRepository = $this->entityManager->getRepository(Lvc::class);
        $lvc = $lvcRepository->findOneBy(["createdAt" => $cac->getCreatedAt()]);
        if (!$lvc) {
            $date = $cac->getCreatedAt()?->format("D/M/Y");
            $this->logger->error(sprintf("Pas de correspondance LVC pour le CAC fournit en date du %s", $date));
            throw new \RuntimeException("Aucune correspondance LVC trouvée pour le CAC.");
        }
        $lvcHigher = $lvc->getHigher();

        // J'hydrate l'instance LastHigh avec les données de l'objet Lvc récupéré.
        $lastHighEntity->setLvcHigher($lvcHigher);

        // lvcBuyLimit fixée au double du SPREAD en raison d'un levier x2
        $lvcBuyLimit = $lvcHigher - ($lvcHigher * (Position::SPREAD * 2));
        $lastHighEntity->setLvcBuyLimit(round($lvcBuyLimit, 2));
        $lastHighEntity->setDailyLvc($lvc);

        // Je persiste ici les données en base pour créer un id que je transmets ensuite à l'utilisateur.
        $lastHighRepository->add($lastHighEntity, true);

        return $lastHighEntity;
    }

    /**
     * Met à jour les positions en attente d'un utilisateur dont la buyLimit n'a pas été touchée.
     */
    public function setPositions(LastHigh $lastHigh, array $positions = []): void
    {
        // Si le tableau des positions est vide, on crée 3 nouvelles positions
        if (count($positions) === 0) {
            $positions = array_map(static fn() => new Position(), range(1, 3));
        }

        /* Si la taille du tableau n'est pas égal à 3, c'est qu'une position du cycle d'achat
        a été passée en isRunning : les positions isWaiting de la même buyLimit sont alors gelées. */
        if (count($positions) !== 3) {
            $message = 'Pas de mise à jour des positions. ';
            $message .= 'Au moins une position isRunning existe avec une buyLimit = %s';
            $this->logger->info(sprintf($message, $lastHigh->getBuyLimit()));

            return;
        }

        foreach ($positions as $key => $position) {
            $this->setPosition($lastHigh, $position, $key);
        }
        $this->entityManager->flush();
    }

    public function setPosition(LastHigh $lastHigh, Position $position, int $key): Position
    {
        // Je récupère l'utilisateur courant.
        $user = $this->getCurrentUser();

        // Je fixe les % d'écart entre les lignes pour le cac et pour le lvc (qui a un levier x2).
        $delta = [
            'cac' => [0, 2, 4],
            'lvc' => [0, 4, 8]
        ];

        $position->setBuyLimit($lastHigh);
        $buyLimit = $lastHigh->getBuyLimit();

        // Positions prises à 0, -2 et -4 %.
        $positionDeltaCac = $buyLimit - ($buyLimit * $delta['cac'][$key] / 100);
        $position->setBuyTarget(round($positionDeltaCac, 2));
        $position->setWaiting(true);
        $position->setBuyDate($lastHigh->getDailyCac()?->getCreatedAt());
        $position->setUserPosition($user);
        $lvcBuyLimit = $lastHigh->getLvcBuyLimit();

        // Positions prises à 0, -4 et -8 %.
        $positionDeltaLvc = $lvcBuyLimit - ($lvcBuyLimit * $delta['lvc'][$key] / 100);
        $position->setLvcBuyTarget(round($positionDeltaLvc, 2));
        $position->setQuantity((int)round(Position::LINE_VALUE / $positionDeltaLvc));

        // Revente d'une position à +20 %.
        $position->setLvcSellTarget(round($positionDeltaLvc * 1.2, 2));

        $this->entityManager->persist($position);

        return $position;
    }

    /**
     * Mise à jour des positions en attente et en cours à partir des données LVC récupérées.
     */
    public function checkLvcData(Lvc $lvc): void
    {
        $this->updateIsWaitingPositions($lvc);
        $this->updateIsRunningPositions($lvc);
    }

    /**
     * @param Lvc $lvc
     * @return void
     */
    public function updateIsWaitingPositions(Lvc $lvc): void
    {
        // Récupère les positions isWaiting de l'utilisateur.
        $positions = $this->getPositionsOfCurrentUser("isWaiting");

        // Pour chacune des positions en cours, je vérifie si lvc.lower < position.lvcBuyTarget.
        foreach ($positions as $position) {
            if ($lvc->getLower() <= $position->getLvcBuyTarget()) {
                // On passe le statut de la position à isRunning.
                $this->openPosition($lvc, $position);
                // Si la position mise à jour est la première de sa série...
                if ($this->checkIsFirst($position)) {
                    // ...on crée et on récupère le nouveau point haut en passant le cac contemporain du lvc courant.
                    $cac = $this->entityManager->getRepository(Cac::class)
                        ->findOneBy(
                            ['createdAt' => $lvc->getCreatedAt()]
                        );
                    if (!$cac) {
                        // $date = $lvc->getCreatedAt() !== null ? $lvc->getCreatedAt()->format("D/M/Y") : null;
                        $date = $lvc->getCreatedAt()?->format("D/M/Y");
                        $message = "Impossible de récupérer le CAC correspondant au LVC en date du %s";
                        $this->logger->error(sprintf($message, $date));
                        throw new \RuntimeException("Impossible de récupérer le CAC correspondant au LVC.");
                    }
                    // On récupère toutes les positions en attente qui ont un point haut différent...
                    $isWaitingPositions = $this->getIsWaitingPositions($position);
                    // ...pour vérifier celles qui sont toujours au nombre de 3 pour une même buyLimit (non isRunning).
                    $isWaitingPositionsChecked = $this->checkIsWaitingPositions($isWaitingPositions);
                    // Si elles existent, on les met à jour, sinon on crée trois nouvelles positions.
                    $this->setHigher($cac, $isWaitingPositionsChecked);
                }
            }
        }
    }

    /**
     * @param Lvc $lvc
     * @return void
     */
    public function updateIsRunningPositions(Lvc $lvc): void
    {
        // TODO : il reste à traiter le solde des positions clôturées pour l'afficher sur le dashboard
        // Récupère les positions isRunning de l'utilisateur.
        $positions = $this->getPositionsOfCurrentUser("isRunning");

        // Pour chacune des positions en cours, je vérifie si lvc.higher > position.sellTarget.
        foreach ($positions as $position) {
            if ($lvc->getHigher() > $position->getLvcSellTarget()) {
                // On passe le statut de la position à isClosed.
                $this->closePosition($lvc, $position);
            }
        }
    }

    /**
     * Retourne les positions en attente liées à l'utilisateur identifié.
     * @param $status L'état de la position (isWaiting, isRunning ou isClosed)
     * @return array<Position>
     */
    private function getPositionsOfCurrentUser(string $status): array
    {
        return $this->entityManager->getRepository(Position::class)
            ->findBy(
                [
                    'userPosition' => $this->getCurrentUser()->getId(),
                    $status => true,
                ]
            );
    }

    /**
     * Change le statut d'une position dont la limite d'achat est atteinte.
     * @param Lvc $lvc
     * @param Position $position
     * @return void
     */
    public function openPosition(Lvc $lvc, Position $position): void
    {
        $position->setWaiting(false);
        $position->setRunning(true);
        $position->setBuyDate($lvc->getCreatedAt());

        $this->entityManager->flush();
    }

    /**
     * Clôture une position dont l'objectif de vente a été atteint
     * et supprime le reliquat de position en attente ayant la même buyLimit.
     */
    public function closePosition(Lvc $lvc, Position $position): void
    {
        $position->setRunning(false);
        $position->setClosed(true);
        $position->setSellDate($lvc->getCreatedAt());

        if (null !== $position->getBuyLimit()) {
            $positions = $this->getIsWaitingPositionsByLashHighId(
                $this->getCurrentUser()->getId(),
                $position->getBuyLimit()->getId()
            );
            $this->removeIsWaitingPositions($positions);

            $this->entityManager->flush();
        }
    }

    /**
     * Vérifie si une seule position en cours existe relativement à sa buyLimit.
     */
    public function checkIsFirst(Position $position): bool
    {
        $positions = $this->entityManager->getRepository(Position::class)
            ->findBy(
                [
                    "isRunning" => true,
                    "buyLimit" => $position->getBuyLimit()
                ]
            );

        return count($positions) === 1;
    }

    /**
     * Retourne les positions 'isWaiting' dont la buyLimit_id est différente de celle de la position courante.
     */
    public function getIsWaitingPositions(Position $position): ?array
    {
        return $this->entityManager->getRepository(Position::class)->getIsWaitingPositionsByBuyLimitID($position);
    }

    /**
     * Récupère les positions d'une même buyLimit lorsqu'elles sont au nombre de trois.
     */
    public function checkIsWaitingPositions(array $positions): ?array
    {
        // On trie les positions en fonction de la propriété buyLimit.
        $results = array_reduce($positions, static function ($result, $position) {
            /** @var Position $position */
            // Je récupère l'id de la propriété buyLimit. S'il n'existe pas dans le tableau $result, je l'ajoute.
            // $buyLimit = $position->getBuyLimit() ? $position->getBuyLimit()->getId() : null;
            $buyLimit = $position->getBuyLimit()?->getId();
            if (!isset($result[$buyLimit])) {
                $result[$buyLimit] = [];
            }
            // J'ajoute la position courante en valeur de la clé correspondant à sa buyLimit.
            $result[$buyLimit][] = $position;

            return $result;
        }, []);

        // Pour chacun des résultats, si on trouve 3 positions, on les ajoute à la liste des positions à traiter.
        return array_filter($results, static fn($item) => count($item) === 3);
    }

    /**
     * Récupère les positions en attente liées à un lastHigh de l'utilisateur connecté.
     */
    public function getIsWaitingPositionsByLashHighId(int $userId, int $lastHighId): array
    {
        return $this->entityManager->getRepository(Position::class)
            ->findBy(
                [
                    "User" => $userId,
                    "isWaiting" => true,
                    "buyLimit" => $lastHighId
                ]
            );
    }

    /**
     * Suppression d'une liste de positions.
     * @param array<Position> $positions
     * @return void
     */
    public function removeIsWaitingPositions(array $positions): void
    {
        foreach ($positions as $position) {
            $this->entityManager->remove($position);
        }
        $this->entityManager->flush();
    }

    /**
     * Enregistre en base la date de la dernière visite de l'utilisateur courant.
     * Permet d'obtenir la liste des données à vérifier pour mettre à jour les positions de l'utilisateur.
     */
    public function updateLastCac(Cac $cac): void
    {
        $user = $this->getCurrentUser();
        $user->setLastCacUpdated($cac);
        $this->entityManager->flush();
    }
}