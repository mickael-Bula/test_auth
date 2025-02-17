<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Position;
use App\Entity\User;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    /** @var EntityRepository <User> */
    private EntityRepository $userRepository;

    /** @var EntityRepository <Position> */
    private EntityRepository $positionRepository;
    private KernelBrowser $client;
    private Router $router;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Boot Kernel et création du client
        $this->client = static::createClient();

        // Récupération de l'EntityManager
        $this->entityManager = self::$kernel->getContainer()->get('doctrine')->getManager();

        // Démarre une transaction
        $this->entityManager->getConnection()->beginTransaction();

        // Récupération du router et de UserRepository
        $this->router = $this->client->getContainer()->get('router.default');
        $this->userRepository = $this->entityManager->getRepository(User::class);
        $this->positionRepository = $this->entityManager->getRepository(Position::class);
    }

    /**
     * @throws Exception
     */
    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->rollback();
        $this->entityManager->getConnection()->close();
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
        $this->assertIsArray($data['wallet'], 'La valeur de "wallet" doit être un tableau');
        $this->assertArrayHasKey('amount', $data['wallet'],
            'La clé "amount" doit être présente dans le tableau "wallet"');
        $this->assertArrayHasKey('runningPRU', $data['wallet'],
            'La clé "runningPRU" doit être présente dans le tableau "wallet"');
        $this->assertArrayHasKey('waitingPRU', $data['wallet'],
            'La clé "waitingPRU" doit être présente dans le tableau "wallet"');
        $this->assertNull($data['wallet']['amount'], 'La valeur de "amount" doit être nulle');

        // Compte le nombre de positions
        $positionCount = count($data['waitingPositions']);
        $this->assertEquals(0, $positionCount, 'Il doit y avoir zéro positions');

        // Vérifie le code HTTP de la réponse
        $this->assertEquals(Response::HTTP_ACCEPTED, $response->getStatusCode());
    }

    /**
     * Ce test retourne un tableau de 3 positions en cours.
     *
     * @throws \JsonException
     */
    public function testGetPositionsWithAmount(): void
    {
        // Simule l'authentification de l'utilisateur récupéré en base, même avec un token JWT
        $user = $this->userRepository->find(2);
        $this->client->loginUser($user);

        // Appelle de la route (sans passer par le router défini, mais en renseignant l'URI)
        $this->client->request('GET', '/api/stocks/dashboard/positions');

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
        $this->assertIsArray($data['wallet'], 'La valeur de "wallet" doit être un tableau');
        $this->assertArrayHasKey('amount', $data['wallet'],
            'La clé "amount" doit être présente dans le tableau "wallet"');
        $this->assertArrayHasKey('runningPRU', $data['wallet'],
            'La clé "runningPRU" doit être présente dans le tableau "wallet"');
        $this->assertArrayHasKey('waitingPRU', $data['wallet'],
            'La clé "waitingPRU" doit être présente dans le tableau "wallet"');
        $this->assertNotNull($data['wallet']['amount'], 'La valeur de "amount" ne doit pas être nulle');

        // Vérifie le premier élément du tableau "positions"
        if (!empty($data['waitingPositions'])) {
            $firstPosition = $data['waitingPositions'][0];
            $this->assertArrayHasKey('buyTarget', $firstPosition,
                'La clé "buyTarget" doit être présente dans une position');
        }

        // Vérifie le code HTTP de la réponse
        $this->assertEquals(Response::HTTP_ACCEPTED, $response->getStatusCode());

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

    public function testGetPositionsWithNewUser(): void
    {
        // Récupère l'utilisateur sans plus haut affecté.
        $user = $this->userRepository->find(3);
        $this->client->loginUser($user);

        // Vérifie qu'il n'y a pas de montant affecté à l'utilisateur avant lancement de la requête.
        $this->assertNull($user->getAmount());

        // Appelle de la route (sans passer par le router défini, mais en renseignant l'URI)
        $this->client->request('GET', '/api/stocks/dashboard/positions');

        // Vérifie que la requête aboutit
        self::assertResponseIsSuccessful();

        // Vérifie qu'aucun montant n'est affecté à l'utilisateur
        $this->assertNull($user->getAmount());

        // Vérifie qu'un plus haut est affecté à l'utilisateur.
        $this->assertNotNull($user->getHigher());

        // Vérifie qu'aucune position n'a été assignée
        $positions = $this->entityManager->getRepository(Position::class)->findBy(
            [
                'userPosition' => $user->getId(),
                'isWaiting' => true,
            ]
        );
        $this->assertCount(0, $positions);
    }

    /**
     * @throws \JsonException
     */
    public function testSetUserAmount(): void
    {
        // Récupère un utilisateur avec un plus haut renseigné, mais sans versement effectué.
        $user = $this->userRepository->find(1);

        // Authentifie l'utilisateur dans le client de test
        $this->client->loginUser($user);

        // Vérifie qu'il n'y a pas de montant affecté à l'utilisateur avant lancement de la requête.
        $this->assertNull($user->getAmount());

        // Vérifie que l'utilisateur n'a pas de positions
        $positions = $this->positionRepository->findBy(['userPosition' => $user->getId()]);
        $this->assertCount(0, $positions);

        // Envoie la requête POST avec le montant
        $this->client->request(
            'POST',
            '/api/config/amount',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['amount' => 100.5], JSON_THROW_ON_ERROR)
        );

        // Vérifie qu'un montant est assigné
        $this->assertNotNull($user->getAmount());

        // Vérifie que 3 positions en attente sont créées.
        $positions = $this->positionRepository->findBy(['userPosition' => $user->getId()]);
        $this->assertCount(3, $positions);

        // Vérifie que la requête redirige bien
        self::assertResponseRedirects('/api/stocks/dashboard/positions');
    }
}
