<?php

namespace App\Controller;

use App\Entity\Cac;
use App\Entity\User;
use App\Entity\Position;
use App\Services\PositionHandler;
use App\Services\LastHighHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ApiController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private PositionHandler $positionHandler;

    private LastHighHandler $lastHighHandler;

    public function __construct(
        EntityManagerInterface $entityManager,
        PositionHandler        $positionHandler,
        LastHighHandler       $lastHighHandler
    )
    {
        $this->entityManager = $entityManager;
        $this->positionHandler = $positionHandler;
        $this->lastHighHandler = $lastHighHandler;
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
            $this->lastHighHandler->setHigherToNewRegisteredUser();
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
}
