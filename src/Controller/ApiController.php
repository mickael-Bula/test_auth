<?php

namespace App\Controller;

use App\Entity\Cac;
use App\Entity\Lvc;
use App\Entity\User;
use App\Entity\Position;
use App\Entity\LastHigh;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ApiController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $myAppLogger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $myAppLogger;
    }

    /**
     * Retourne les 10 dernières cotations du Cac et les derniers cours de clôture du Lvc
     *
     * @return JsonResponse
     */
    #[Route('/api/stocks/dashboard', methods: ['GET'])]
    public function getDashboardData(): JsonResponse
    {
        $data = $this->entityManager->getRepository(Cac::class)->getCacAndLvcData();

        return $this->json($data);
    }

    /**
     * Retourne le plus haut et la milite d'achat de l'utilisateur courant.
     *
     * @return JsonResponse
     */
    #[Route('api/stocks/dashboard/positions', methods: ['GET'])]
    public function getUserPositions(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Si aucun plus haut n'est affecté à l'utilisateur, on le crée
        if (is_null($user->getHigher())) {
            $this->setHigherToNewRegisteredUser();
        }

        // On retourne les données de trading de l'utilisateur
        $lastHigh = $user->getHigher();
        $lastHigher = $lastHigh?->getHigher();
        $buyLimit = $lastHigh?->getBuyLimit();
        $positionRepository = $this->entityManager->getRepository(Position::class);
        [$waitingPositions, $runningPositions, $closedPositions] = $positionRepository->getUserPositions($user->getId());

        return $this->json(
            [
                'lastHigher' => $lastHigher,
                'buyLimit' => $buyLimit,
                'waitingPositions' => $waitingPositions,
                'runningPositions' => $runningPositions,
                'closedPositions' => $closedPositions,
            ],
            200,
            [],
            ['groups' => 'position_read'],
        );

    }

    public function setHigherToNewRegisteredUser(): void
    {
        /** @var User $user */
        $user = $this->getUser();

        $lastHighRepository = $this->entityManager->getRepository(LastHigh::class);

        // On récupère le plus haut le plus récent du Cac et on en fait le plus haut de l'utilisateur.
        $cac = $this->entityManager->getRepository(Cac::class)->findOneBy([], ['id' => 'DESC']);

        $lastHighEntity = $this->setNewUserLastCacHigher($cac);

        $lastHighEntity = $this->setNewUserLastLvcHigher($lastHighEntity, $cac);

        /* Je persiste les données et je les insère en base.
        Je le fais avant de le transmettre à l'user pour qu'un id soit créé */
        $lastHighRepository->add($lastHighEntity, true);

        // J'assigne ce plus haut à l'utilisateur courant et j'enregistre à nouveau en base
        $user->setHigher($lastHighEntity);

        $this->entityManager->flush();

        // je crée également les positions en rapport avec la nouvelle buyLimit
        $this->setPositions($lastHighEntity, []);
    }

    public function setNewUserLastCacHigher(?Cac $cac): LastHigh
    {
        $lastHigher = $cac?->getHigher();

        // je crée une nouvelle instance de LastHigh et je l'hydrate
        $lastHighEntity = new LastHigh();
        $lastHighEntity->setHigher($lastHigher);
        $buyLimit = $lastHigher - ($lastHigher * Position::SPREAD);    // buyLimit se situe 6 % sous higher
        $lastHighEntity->setBuyLimit(round($buyLimit, 2));
        $lastHighEntity->setDailyCac($cac);

        return $lastHighEntity;
    }

    public function setNewUserLastLvcHigher(LastHigh $lastHighEntity, ?Cac $cac): LastHigh
    {
        // à partir de l'entity Cac, je récupère l'objet LVC contemporain
        $lvcRepository = $this->entityManager->getRepository(Lvc::class);
        $lvc = $lvcRepository->findOneBy(["createdAt" => $cac?->getCreatedAt()]);
        if (!$lvc) {
            $date = $cac?->getCreatedAt() !== null ? $cac?->getCreatedAt()?->format("D/M/Y") : null;
            $this->logger->error(sprintf("Pas de LVC correpondant pour le CAC fournit en date du %s", $date));
        }
        $lvcHigher = $lvc->getHigher();

        // j'hydrate l'instance LastHigh avec les données de l'objet Lvc récupéré
        $lastHighEntity->setLvcHigher($lvcHigher);

        // lvcBuyLimit fixée au double du SPREAD en raison d'un levier x2
        $lvcBuyLimit = $lvcHigher - ($lvcHigher * (Position::SPREAD * 2));
        $lastHighEntity->setLvcBuyLimit(round($lvcBuyLimit, 2));
        $lastHighEntity->setDailyLvc($lvc);

        return $lastHighEntity;
    }

    /**
     * Met à jour les positions en attente d'un utilisateur dont la buyLimit n'a pas été touchée
     * @param LastHigh $lastHigh
     * @param array<Position> $positions
     * @return void
     */
    public function setPositions(LastHigh $lastHigh, array $positions = []): void
    {
        // je récupère l'user en session
        $user = $this->getUser();

        // Je compte les positions passées en paramètre
        $nbPositions = count($positions);

        /* Si la taille du tableau n'est pas égal à 0 ou 3, c'est qu'une position du cycle d'achat
        a été passée en isRunning : les positions isWaiting de la même buyLimit sont alors gelées */
        if (!in_array($nbPositions, [0, 3], true)) {
            $this->logger->info(
                sprintf(
                    "Pas de mise à jour des positions. Au moins une position isRunning existe avec une buyLimit = %s",
                    $lastHigh->getBuyLimit()
                )
            );

            return;
        }

        // je fixe les % d'écart entre les lignes pour le cac et pour le lvc (qui a un levier x2)
        $delta = [
            'cac' => [0, 2, 4],
            'lvc' => [0, 4, 8]
        ];

        // je boucle sur les positions existantes, sinon j'en crée 3 nouvelles
        for ($i = 0; $i < 3; $i++) {
            $position = $nbPositions === 0 ? new Position() : $positions[$i];
            $position->setBuyLimit($lastHigh);
            $buyLimit = $lastHigh->getBuyLimit();

            // positions prises à 0, -2 et -4 %
            $positionDeltaCac = $buyLimit - ($buyLimit * $delta['cac'][$i] / 100);
            $position->setBuyTarget(round($positionDeltaCac, 2));

            // la revente d'une position est fixée à +10 %
            $position->setLvcSellTarget(round($positionDeltaCac * 1.1, 2));
            $position->setWaiting(true);
            $position->setUserPosition($user);
            $lvcBuyLimit = $lastHigh->getLvcBuyLimit();

            // positions prises à 0, -4 et -8 %
            $positionDeltaLvc = $lvcBuyLimit - ($lvcBuyLimit * $delta['lvc'][$i] / 100);
            $position->setLvcBuyTarget(round($positionDeltaLvc, 2));
            $position->setQuantity((int)round(Position::LINE_VALUE / $positionDeltaLvc));

            // revente d'une position à +20 %
            $position->setLvcSellTarget(round($positionDeltaLvc * 1.2, 2));

            $this->entityManager->persist($position);
        }
        $this->entityManager->flush();

        // NOTE : 100 mails par mois dans le cadre du plan gratuit proposé par Mailtrap
        // $this->mailer->sendEmail($positions);
    }
}
