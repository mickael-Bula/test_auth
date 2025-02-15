<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Cac;
use App\Entity\LastHigh;
use App\Entity\Lvc;
use App\Entity\Position;
use App\Entity\User;
use App\Repository\CacRepository;
use App\Repository\LastHighRepository;
use App\Repository\LvcRepository;
use App\Repository\PositionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class PositionHandler
{
    private EntityManagerInterface $entityManager;
    private Security $security;
    private LoggerInterface $logger;

    private LvcRepository $lvcRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        Security $security,
        LoggerInterface $logger,
        LvcRepository $lvcRepository,
    ) {
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->logger = $logger;
        $this->lvcRepository = $lvcRepository;
    }

    /**
     * Récupère le User en BDD à partir de son id, en précisant à l'IDE que getId() se réfère à l'Entity User.
     */
    public function getCurrentUser(): User
    {
        /** @var User $user */
        $user = $this->security->getUser();

        return $this->entityManager->getRepository(User::class)->find($user->getId());
    }

    /**
     * Récupère la liste des entités cac à utiliser pour mettre à jour les positions de l'utilisateur courant.
     *
     * @return Cac[]
     */
    public function dataToCheck(): array
    {
        /** @var CacRepository $cacRepository */
        $cacRepository = $this->entityManager->getRepository(Cac::class);
        $user = $this->getCurrentUser();

        return $cacRepository->getDataToUpdateFromUser($user->getLastCacUpdated());
    }

    /**
     * Actualise le plus haut local et les positions d'une liste de données Cac.
     *
     * @param Cac[] $cacData
     */
    public function updateCacData(array $cacData): void
    {
        $lvcRepository = $this->entityManager->getRepository(Lvc::class);

        foreach ($cacData as $cac) {
            $this->checkLastHigh($cac);

            // Mise à jour de la date représentant la dernière visite de l'utilisateur.
            $this->updateLastCac($cac);

            // Récupération du lvc contemporain au cac pour mise à jour des positions.
            $lvcData = $lvcRepository->findOneBy(['createdAt' => $cac->getCreatedAt()]);
            if ($lvcData) {
                $this->checkLvcData($lvcData);
            } else {
                $date = $cac->getCreatedAt()?->format('D/M/Y');
                $this->logger->error(
                    sprintf('Pas de correspondance LVC pour le CAC fournit en date du %s', $date)
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
     * @param Cac             $cac       l'objet cac qui a fait le plus haut
     * @param array<Position> $positions
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
     * @param Cac      $cac      l'objet cac qui a fait le nouveau plus haut
     * @param LastHigh $lastHigh représente le plus haut à actualiser
     */
    public function updateHigher(Cac $cac, LastHigh $lastHigh): void
    {
        $positionsRepository = $this->entityManager->getRepository(Position::class);

        // J'actualise le plus haut.
        $newLastHigh = $this->setBuyLimitToNewLastHigh($cac, $lastHigh);

        // je mets à jour les positions en attente de l'utilisateur et liées au lastHigh (via la buyLimit).
        $positions = $positionsRepository->findBy(
            [
                'userPosition' => $this->getCurrentUser(),
                'isWaiting' => true,
                'buyLimit' => $newLastHigh->getId(),
            ]
        );
        $this->setPositions($newLastHigh, $positions);
    }

    /**
     * Affecte la buyLimit au nouveau plus haut.
     */
    public function setBuyLimitToNewLastHigh(Cac $cac, ?LastHigh $lastHigh = null): LastHigh
    {
        /** @var LastHighRepository $lastHighRepository */
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
        $lvc = $lvcRepository->findOneBy(['createdAt' => $cac->getCreatedAt()]);
        if (!$lvc) {
            $date = $cac->getCreatedAt()?->format('D/M/Y');
            $this->logger->error(sprintf('Pas de correspondance LVC pour le CAC fournit en date du %s', $date));
            throw new \RuntimeException('Aucune correspondance LVC trouvée pour le CAC.');
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
     *
     * @param Position[] $positions
     */
    public function setPositions(LastHigh $lastHigh, array $positions = []): void
    {
        // TODO : Les sommes nécessaires pour passer les ordres doivent être cohérents avec le montant.

        // Si le tableau des positions est vide, on crée 3 nouvelles positions
        if (0 === count($positions)) {
            $positions = array_map(static fn () => new Position(), range(1, 3));
        }

        /* Si la taille du tableau n'est pas égal à 3, c'est qu'une position du cycle d'achat
        a été passée en isRunning : les positions isWaiting de la même buyLimit sont alors gelées. */
        if (3 !== count($positions)) {
            $this->logger->info(sprintf(
                'Pas de mise à jour des positions. '
                    .'Au moins une position isRunning existe avec une buyLimit = %s',
                $lastHigh->getBuyLimit())
            );

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
            'lvc' => [0, 4, 8],
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
        $position->setQuantity((int) round(Position::LINE_VALUE / $positionDeltaLvc));

        // Définit la cible de revente
        $sellTarget = round($positionDeltaLvc * 1.2, 2);

        // Calcule le ratio d'investissement
        $ratio = $this->investmentRatio();

        $sellQuantity = $this->getSellQuantity($ratio, $position, $sellTarget);

        // Si $sellQuantity est null, $sellTarget et $cacSellTarget le seront aussi.
        $sellTarget = $sellQuantity ? $sellTarget : null;
        $cacSellTarget = $sellTarget ? round($position->getBuyTarget() * 1.1, 2) : null;

        $position->setLvcSellTarget($sellTarget);
        $position->setQuantityToSell($sellQuantity);
        $position->setSellTarget($cacSellTarget);

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

    public function updateIsWaitingPositions(Lvc $lvc): void
    {
        // Récupère les positions isWaiting de l'utilisateur.
        $positions = $this->getPositionsOfCurrentUser('isWaiting');

        // Pour chacune des positions en cours, je vérifie si lvc.lower < position.lvcBuyTarget.
        foreach ($positions as $position) {
            if ($lvc->getLower() <= $position->getLvcBuyTarget()) {
                // On passe le statut de la position à isRunning.
                $this->openPosition($lvc, $position);
                // Si la position mise à jour est la première de sa série...
                if ($this->checkIsFirst($position)) {
                    // ...on récupère le nouveau point haut en passant le cac contemporain du lvc courant.
                    $cac = $this->entityManager
                        ->getRepository(Cac::class)
                        ->findOneBy(['createdAt' => $lvc->getCreatedAt()]);

                    if (!$cac) {
                        $date = $lvc->getCreatedAt()?->format('D/M/Y');
                        $message = 'Impossible de récupérer le CAC correspondant au LVC en date du %s';
                        $this->logger->error(sprintf($message, $date));
                        throw new \RuntimeException('Impossible de récupérer le CAC correspondant au LVC.');
                    }
                    // On récupère toutes les positions en attente qui ont un point haut différent
                    $isWaitingPositions = $this->getIsWaitingPositions($position);

                    // On ne conserve que les positions au nombre de 3 pour une même buyLimit (non isRunning).
                    $isWaitingPositionsChecked = $this->checkIsWaitingPositions($isWaitingPositions);

                    // Si elles existent, on les met à jour, sinon on crée trois nouvelles positions.
                    $this->setHigher($cac, $isWaitingPositionsChecked);
                }
            }
        }
    }

    public function updateIsRunningPositions(Lvc $lvc): void
    {
        // TODO : il reste à traiter le solde des positions clôturées pour l'afficher sur le dashboard
        // Récupère les positions isRunning de l'utilisateur.
        $positions = $this->getPositionsOfCurrentUser('isRunning');

        /* Pour chacune des positions en cours, si une limite de vente lvcSellTarget est fixée,
        alors je vérifie si lvc.higher > position.lvcSellTarget. */
        foreach ($positions as $position) {
            if (null !== $position->getLvcSellTarget() && $lvc->getHigher() > $position->getLvcSellTarget()) {
                // On passe le statut de la position à isClosed.
                $this->closePosition($lvc, $position);

                // On met à jour le capital de l'utilisateur
                $this->addSellResultToCapital($position);
            }
        }
    }

    /**
     * Ajoute le résultat d'une vente au capital de l'utilisateur courant.
     */
    public function addSellResultToCapital(Position $position): ?float
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->entityManager->getRepository(User::class);

        $user = $userRepository->findOneBy(['id' => $this->getCurrentUser()]);

        if (!$user) {
            echo 'Utilisateur introuvable.';

            return null;
        }

        // Ajoute au capital le résultat de la +/- value.
        $capital = $user->getAmount() ?: 0;
        $capital += ($position->getLvcSellTarget() - $position->getLvcBuyTarget()) * $position->getQuantityToSell();

        // Mise à jour du capital de l'utilisateur
        $capital = round($capital, 2);
        $user->setAmount($capital);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $capital;
    }

    /**
     * Récupère les plus-values potentielles.
     */
    public function latentGainOrLoss(): int|float
    {
        /** @var PositionRepository $positionRepository */
        $positionRepository = $this->entityManager->getRepository(Position::class);

        return $positionRepository->getLatentGainOrLoss();
    }

    /**
     * Calcule la valorisation du capital.
     */
    public function getValorisation(): int|float
    {
        $latentGainOrLoss = $this->latentGainOrLoss();
        $capital = $this->getCurrentUser()->getAmount() ?: 0;
        $capital += $latentGainOrLoss;

        return $capital;
    }

    /**
     * Calcule le ratio des positions en cours sur le capital.
     */
    public function investmentRatio(): float
    {
        // Récupère les plus-values potentielles
        $latentGainOrLoss = $this->latentGainOrLoss();

        // Récupère le capital
        $capital = $this->getValorisation();

        return round($latentGainOrLoss * 100 / $capital, 2);
    }

    /**
     * Calcule la somme permettant de ramener l'investissement à 75% du capital.
     */
    public function targetRecoveryCapital(float|int $sellTarget, Position $position): float|int
    {
        // Récupère la valorisation du capital
        $capital = $this->getValorisation();

        // Calcule la valorisation des positions en cours
        $runningInvestment = $this->lvcRepository->getLvcClosingAndTotalQuantity();

        // Calcule 75% du capital
        $maxInvestment = $capital * 75 / 100;

        // Somme à vendre pour atteindre la cible minimale
        $minSell = $runningInvestment - $maxInvestment;

        // Valorisation du trade courant : valeur de la ligne / prix d'achat
        $trade = $sellTarget * $position->getQuantity();

        // On prend la plus grande des deux valeurs
        return max($minSell, $trade);
    }

    /**
     * Calcule la quantité de LVC à revendre en fonction du taux de position en cours par rapport à la valorisation.
     * Si ratio > 75, vente du max entre vente du trade et retour à 75%.
     * Si ratio > 50, vente de la totalité du trade.
     * Si ratio > 25, vente uniquement du capital investi (750 €).
     * Sinon, la position est conservée.
     *
     * NOTE : Le switch et le match ne réussissent pas à évaluer ratio...
     */
    public function getSellQuantity(float $ratio, Position $position, float $sellTarget): ?int
    {
        if ($ratio > 75) {
            return (int) round($this->targetRecoveryCapital($sellTarget, $position) / $sellTarget);
        }
        if ($ratio > 50) {
            return $position->getQuantity();
        }
        if ($ratio > 25) {
            return (int) round(Position::LINE_VALUE / $sellTarget);
        }

        return null;
    }

    /**
     * Retourne les positions en attente liées à l'utilisateur identifié.
     *
     * @param $status L'état de la position (isWaiting, isRunning ou isClosed)
     *
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
                    'isRunning' => true,
                    'buyLimit' => $position->getBuyLimit(),
                ]
            );

        return 1 === count($positions);
    }

    /**
     * Retourne les positions 'isWaiting' dont la buyLimit_id est différente de celle de la position courante.
     *
     * @return Position[]|null
     */
    public function getIsWaitingPositions(Position $position): ?array
    {
        /** @var PositionRepository $positionRepository */
        $positionRepository = $this->entityManager->getRepository(Position::class);

        return $positionRepository->getIsWaitingPositionsByBuyLimitID($position);
    }

    /**
     * Récupère les positions d'une même buyLimit lorsqu'elles sont au nombre de trois.
     *
     * @param Position[] $positions
     *
     * @return Position[]|null
     */
    public function checkIsWaitingPositions(array $positions): ?array
    {
        // On trie les positions en fonction de la propriété buyLimit.
        $results = array_reduce($positions, static function ($result, $position) {
            /** @var Position $position */
            // Je récupère l'id de la propriété buyLimit. S'il n'existe pas dans le tableau $result, je l'ajoute.
            $buyLimit = $position->getBuyLimit()?->getId();
            if (!isset($result[$buyLimit])) {
                $result[$buyLimit] = [];
            }
            // J'ajoute la position courante en valeur de la clé correspondant à sa buyLimit.
            $result[$buyLimit][] = $position;

            return $result;
        }, []);

        // On supprime les positions si elles ne sont pas au nombre de trois pour une même limite d'achat.
        foreach ($results as $buyLimit => $items) {
            if (3 !== count($items)) {
                foreach ($items as $position) {
                    $this->entityManager->remove($position);
                }
                unset($results[$buyLimit]);
            }
        }
        $this->entityManager->flush();

        // On retourne le reste du tableau, c'est-à-dire les positions au nombre de trois pour une même limite d'achat.
        return $results;
    }

    /**
     * Récupère les positions en attente liées à un lastHigh de l'utilisateur connecté.
     *
     * @return Position[]
     */
    public function getIsWaitingPositionsByLashHighId(int $userId, int $lastHighId): array
    {
        return $this->entityManager->getRepository(Position::class)
            ->findBy(
                [
                    'userPosition' => $userId,
                    'isWaiting' => true,
                    'buyLimit' => $lastHighId,
                ]
            );
    }

    /**
     * Suppression d'une liste de positions.
     *
     * @param array<Position> $positions
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
