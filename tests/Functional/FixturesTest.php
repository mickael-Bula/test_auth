<?php

namespace App\Tests\Functional;

use App\DataFixtures\FirstFixtures;
use App\Entity\Cac;
use App\Entity\Lvc;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FixturesTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    /**
     * @throws \Exception
     */
    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // Chargement des fixtures une seule fois dans setUp()
        $fixture = new FirstFixtures();
        $fixture->load($this->entityManager);
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();
    }

    public function testCacAndLvcInserted(): void
    {
        $this->entityManager->beginTransaction();

        // Vérifie que les données "Cac" sont insérées
        $cac = $this->entityManager->getRepository(Cac::class)->findOneBy(['id' => 1]);
        $this->assertNotNull($cac);
        $this->assertInstanceOf(Cac::class, $cac);

        // Vérifie que les données "Lvc" sont insérées
        $lvc = $this->entityManager->getRepository(Lvc::class)->findOneBy(['id' => 1]);
        $this->assertNotNull($lvc);
        $this->assertInstanceOf(Lvc::class, $lvc);

        $this->entityManager->rollback();
    }
}
