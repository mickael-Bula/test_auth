<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ApiController extends AbstractController
{
    /**
     * @throws \JsonException
     */
    #[Route('/api/dashboard', name: 'api_dashboard', methods: ['POST'])]
    public function getDashboard(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $username = $user->getUsername();

        $username = json_encode($username, JSON_THROW_ON_ERROR);

        return $this->json(
            [
                'message' => 'Voici des données sécurisées',
                'user' => $username,
            ]
        );
    }
}
