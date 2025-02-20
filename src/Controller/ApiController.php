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
     * Retourne les dernières cotations du Cac et les derniers cours de clôture du Lvc.
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
    public function getUserPositions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // On vérifie si une notification est disponible.
        $notification = $this->getNotification($request);

        // Si aucun plus haut n'est affecté à l'utilisateur, on le crée
        if (is_null($user->getHigher())) {
            $this->lastHighHandler->setHigherToNewRegisteredUser($user);
        }

        $data = $this->positionHandler->getUserData($user, $notification);

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

        // Récupère la propriété amount de l'utilisateur enregistré en base
        $userAmount = $user->getAmount();

        $isFirstPayment = true;

        // Si un versement préalable existe, on cumule les montants.
        if (null !== $userAmount) {
            $amountValue += $userAmount;
            $isFirstPayment = false;
        }

        // Enregistrement du versement
        $user->setAmount($amountValue);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Si c'est un premier versement, on crée les positions.
        $param = $isFirstPayment
            ? ['positionsCreated' => $this->positionHandler->setPositions($user->getHigher())]
            : ['amountUpdated' => true];

        // Redirige vers la route api_get_positions pour transmettre les informations de l'utilisateur.
        return $this->redirectToRoute('api_get_positions', $param);
    }

    /**
     * @return array<string, string>|null
     */
    public function getNotification(Request $request): ?array
    {
        /** @var User $user */
        $user = $this->getUser();

        // Si l'on vient de la route setAmount avec un premier versement, le nombre de positions créées est disponible
        if ($positionsCreated = $request->query->get('positionsCreated')) {
            return [
                'message' => "$positionsCreated positions créées suite au versement initial.",
                'type' => 'success',
            ];
        }

        // Si l'on vient de la route setAmount avec un nouveau versement.
        if ($request->query->get('amountUpdated')) {
            return [
                'message' => 'Le nouveau versement a été pris en compte.',
                'type' => 'success',
            ];
        }

        // Si aucun versement n'a été effectué.
        if (null === $user->getAmount()) {
            return [
                'message' => 'Pour profiter du suivi des positions, '
                    .'veuillez saisir un versement initial depuis le menu Paramètres.',
                'type' => 'warning',
            ];
        }

        return null;
    }
}
