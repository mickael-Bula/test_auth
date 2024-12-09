<?php

namespace App\Controller;

use App\Entity\Cac;
use App\Entity\Lvc;
use App\Entity\User;
use App\Entity\Position;
use App\Entity\LastHigh;
use Psr\Log\LoggerInterface;
use App\Services\PositionHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ApiController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private PositionHandler $positionHandler;

    public function __construct(
        EntityManagerInterface $entityManager,
        PositionHandler        $positionHandler,
        LoggerInterface        $myAppLogger)
    {
        $this->entityManager = $entityManager;
        $this->positionHandler = $positionHandler;
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
     * Retourne le plus haut et la limite d'achat de l'utilisateur courant.
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

        // Mise à jour des journées de cotation manquantes depuis la dernière visite de l'utilisateur.
        $cacList = $this->positionHandler->dataToCheck();
        $this->positionHandler->updateCacData($cacList);

        // On retourne les données de trading de l'utilisateur
        $lastHigh = $user->getHigher();
        $lastHigher = $lastHigh?->getHigher();
        $dateOfLastHigher = $lastHigh?->getDailyCac()?->getCreatedAt()?->format('Y-m-d\TH:i:s\Z');
        $positionRepository = $this->entityManager->getRepository(Position::class);
        $buyLimit = $lastHigh?->getBuyLimit();
        [$waitingPositions, $runningPositions, $closedPositions] = $positionRepository->getUserPositions($user->getId());

        return $this->json(
            [
                'lastHigher' => $lastHigher,
                'dateOfLastHigher' => $dateOfLastHigher,
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

        // On récupère le plus haut le plus récent du Cac et on en fait le plus haut de l'utilisateur.
        $cac = $this->entityManager->getRepository(Cac::class)->findOneBy([], ['id' => 'DESC']);

        $lastHighEntity = $this->setNewUserLastCacHigher($cac);

        $lastHighEntity = $this->setNewUserLastLvcHigher($lastHighEntity, $cac);

        // Je persiste les données pour créer l'id du lashHigh avant de l'assigner à l'utilisateur.
        $lastHighRepository = $this->entityManager->getRepository(LastHigh::class);
        $lastHighRepository->add($lastHighEntity, true);
        $user->setHigher($lastHighEntity);

        // On trace la dernière cotation utilisée pour le calcul des données de trading de l'utilisateur.
        $user->setLastCacUpdated($cac);

        $this->entityManager->flush();

        // je crée également les positions en rapport avec la nouvelle buyLimit
        $this->positionHandler->setPositions($lastHighEntity, []);
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
        // à partir de l'entité Cac, je récupère l'objet LVC contemporain
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
}
