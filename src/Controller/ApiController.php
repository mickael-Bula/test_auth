<?php

namespace App\Controller;

use App\Entity\Cac;
use App\Entity\User;
use App\Entity\Position;
use App\Services\PositionHandler;
use App\Services\LastHighHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
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
        $amount = $user->getAmount();
        $lastHigh = $user->getHigher();
        $lastHigher = $lastHigh?->getHigher();
        $dateOfLastHigher = $lastHigh?->getDailyCac()?->getCreatedAt()?->format('Y-m-d\TH:i:s\Z');
        $positionRepository = $this->entityManager->getRepository(Position::class);
        $buyLimit = $lastHigh?->getBuyLimit();
        [$waitingPositions, $runningPositions, $closedPositions] = $positionRepository->getUserPositions($user->getId());

        return $this->json(
            [
                'amount' => $amount,
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

    /**
     * @throws \JsonException
     */
    #[Route('/api/config/amount', methods: ['POST'])]
    public function setUserAmount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (!isset($data['amount']) || !is_numeric($data['amount'])) {
            return new JsonResponse(['error' => 'Montant invalide.'], 400);
        }

        $amountValue = (float) $data['amount'];

        // Valide le montant
        if ($amountValue <= 0) {
            return $this->json(['error' => 'Le montant doit être supérieur à 0'], 400);
        }

        // Insère le montant dans la table User
        $user->setAmount($amountValue);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json(['success' => 'Montant mis à jour.', 'amount' => $user->getAmount()]);
    }
}
