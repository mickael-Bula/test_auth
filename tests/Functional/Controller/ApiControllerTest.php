<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class ApiControllerTest extends WebTestCase
{
    private UserRepository $userRepository;
    private KernelBrowser $client;
    private Router $router;

    protected function setUp(): void
    {
        // Appel de KernelTestCase::bootKernel() et création d'un "client" qui agit comme un navigateur.
        $this->client = static::createClient();
        $this->router = $this->client->getContainer()->get('router.default');
        $entityManager = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $this->userRepository = $entityManager->getRepository(User::class);
        parent::setUp();
    }

    /**
     * Ce test retourne un tableau de positions vides en raison d'un versement manquant.
     *
     * @throws \JsonException
     */
    public function testGetPositionsWithoutAmount(): void
    {
        // Simule l'authentification de l'utilisateur récupéré en base, même avec un token JWT
        $user = $this->userRepository->find(1);
        $this->client->loginUser($user);

        // Appelle de la route
        $this->client->request(Request::METHOD_GET, $this->router->generate('api_get_positions'));

        // Vérifie que la requête aboutit
        self::assertResponseIsSuccessful();

        // Vérifie que le type de contenu est du JSON
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $response = $this->client->getResponse();

        // Décode le JSON en tableau associatif
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        // Assertions sur le contenu du JSON
        $this->assertArrayHasKey('waitingPositions', $data, 'La clé "waitingPositions" doit être présente');
        $this->assertIsArray($data['waitingPositions'], 'La valeur de "waitingPositions" doit être un tableau');

        // Vérifie si le tableau "wallet" n'est pas vide
        $this->assertNotEmpty($data['wallet'], 'Le tableau "wallet" ne doit pas être vide');

        // Compte le nombre de positions
        $positionCount = count($data['waitingPositions']);
        $this->assertEquals(0, $positionCount, 'Il doit y avoir zéro positions');

        // Vérifie le code HTTP de la réponse
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Ce test retourne un tableau de positions vides en raison d'un versement manquant.
     *
     * @throws \JsonException
     */
    public function testGetPositionsWithAmount(): void
    {
        // Simule l'authentification de l'utilisateur récupéré en base, même avec un token JWT
        $user = $this->userRepository->find(2);
        $this->client->loginUser($user);

        // Appelle de la route
        $crawler = $this->client->request('GET', '/api/stocks/dashboard/positions');

        // Vérifie que la requête aboutit
        self::assertResponseIsSuccessful();

        // Vérifie que le type de contenu est du JSON
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $response = $this->client->getResponse();

        // Décode le JSON en tableau associatif
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        // Assertions sur le contenu du JSON
        $this->assertArrayHasKey('waitingPositions', $data, 'La clé "waitingPositions" doit être présente');
        $this->assertIsArray($data['waitingPositions'], 'La valeur de "waitingPositions" doit être un tableau');

        // Vérifie si le tableau "wallet" n'est pas vide
        $this->assertNotEmpty($data['wallet'], 'Le tableau "wallet" ne doit pas être vide');

        // Vérifie le premier élément du tableau "positions"
        if (!empty($data['waitingPositions'])) {
            $firstPosition = $data['waitingPositions'][0];
            $this->assertArrayHasKey('buyTarget', $firstPosition, 'La clé "buyTarget" doit être présente dans une position');
        }

        // Vérifie le code HTTP de la réponse
        $this->assertEquals(200, $response->getStatusCode());

        // Compte le nombre de positions
        $positionCount = count($data['waitingPositions']);
        $this->assertGreaterThan(0, $positionCount, 'Il doit y avoir au moins une position');
        $this->assertEquals(3, $positionCount, 'Il doit y avoir 3 positions en cours');

        // Parcourt le tableau "waitingPositions" et vérifie chaque élément.
        foreach ($data['waitingPositions'] as $position) {
            $this->assertArrayHasKey('buyTarget', $position);
            $this->assertArrayHasKey('sellTarget', $position);
            $this->assertArrayHasKey('buyDate', $position);
            $this->assertArrayHasKey('buyLimit', $position);
            $this->assertNull($position['sellTarget']);
            $this->assertIsNumeric($position['buyTarget']);
            $this->assertIsString($position['buyDate']);
            $this->assertIsNumeric($position['lvcBuyTarget']);
            $this->assertIsNumeric($position['quantity']);
            $this->assertNull($position['lvcSellTarget']);
        }
    }
}
