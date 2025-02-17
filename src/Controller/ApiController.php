<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CacRepository;
use App\Services\LastHighHandler;
use App\Services\PositionHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CacRepository $cacRepository,
        private readonly PositionHandler $positionHandler,
        private readonly LastHighHandler $lastHighHandler,
    ) {
    }

    /**
     * Retourne les 10 dernières cotations du Cac et les derniers cours de clôture du Lvc.
     */
    #[Route('/api/stocks/dashboard', name: 'api_dashboard', methods: ['GET'])]
    public function getDashboardData(): JsonResponse
    {
        $data = $this->cacRepository->getCacAndLvcData();

        return $this->json($data);
    }

    /**
     * Retourne le plus haut et la limite d'achat de l'utilisateur courant.
     */
    #[Route('api/stocks/dashboard/positions', name: 'api_get_positions', methods: ['GET'])]
    public function getUserPositions(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Si aucun plus haut n'est affecté à l'utilisateur, on le crée
        if (is_null($user->getHigher())) {
            $this->lastHighHandler->setHigherToNewRegisteredUser($user);
        }

        // Récupère les données de l'utilisateur
        $data = $this->positionHandler->getUserData($user);

        return $this->json($data, Response::HTTP_ACCEPTED, [], ['groups' => 'position_read']);
    }

    /**
     * Enregistre en base le versement de l'utilisateur et crée ses positions.
     *
     * @throws \JsonException
     */
    #[Route('/api/config/amount', name: 'api_set_amount', methods: ['POST'])]
    public function setUserAmount(Request $request): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        // Si le versement n'est pas valide, on retourne une erreur.
        if (!isset($data['amount']) || !is_numeric($data['amount'])) {
            return new JsonResponse(['error' => 'Montant invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $amountValue = (float) $data['amount'];

        // Vérifie que le montant est un nombre positif.
        if ($amountValue <= 0) {
            return $this->json(['error' => 'Le montant doit être supérieur à 0'], Response::HTTP_BAD_REQUEST);
        }

        // Insère le montant dans la table User
        $user->setAmount($amountValue);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Crée les positions de l'utilisateur.
        $this->positionHandler->setPositions($user->getHigher(), []);

        // Redirige vers la route api_get_positions pour transmettre les informations de l'utilisateur.
        return $this->redirectToRoute('api_get_positions');
    }
}
