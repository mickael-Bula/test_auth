<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class RegistrationControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        parent::setUp();
    }

    /**
     * @throws \JsonException
     */
    public function testRegisterSuccess(): void
    {
        $data = [
            'username' => 'testuser',
            'password' => 'testpassword',
        ];

        $this->client->request(
            'POST',
            '/api/register',
            [], // Tableau des paramètres qui reste ici vide
            [], // Pas de fichiers envoyés
            ['CONTENT_TYPE' => 'application/json'], // Définition du header
            json_encode($data, JSON_THROW_ON_ERROR) // Encodage JSON des données
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $responseData = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Utilisateur créé avec succès', $responseData['message']);

        // Optionnel : Vérifier l'utilisateur dans la base de données
        $user = $this->getUserFromDatabase(); // Méthode à implémenter
        $this->assertNotNull($user);
        $this->assertEquals('testuser', $user->getUsername());
        $this->assertTrue(password_verify('testpassword', $user->getPassword())); // Vérifier le hash du mot de passe
    }

    /**
     * @throws \JsonException
     */
    public function testRegisterInvalidData(): void
    {
        $client = static::createClient();

        $data = [
            'username' => 'testuser', // Mot de passe manquant
        ];

        $client->request('POST', '/api/register', [], (array) json_encode($data, JSON_THROW_ON_ERROR), [
            'CONTENT_TYPE' => 'application/json',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseData = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Données invalides', $responseData['error']);
    }

    // Méthode optionnelle pour récupérer l'utilisateur depuis la base de données
    private function getUserFromDatabase(): ?User
    {
        $entityManager = $this->client->getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager->getRepository(User::class)->findOneBy(['username' => 'testuser']);
    }
}
