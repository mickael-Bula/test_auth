<?php

namespace App\Tests\Functional;

use App\Kernel;
use App\Entity\Cac;
use App\Entity\Lvc;
use JetBrains\PhpStorm\NoReturn;
use App\DataFixtures\FirstFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FixturesTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    /**
     * @return string
     */
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    /**
     * @throws \Exception
     */
    #[NoReturn] protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // Charger les `fixtures`
        $fixture = new FirstFixtures();
        $fixture->load($this->entityManager);
    }

    // Test minimal pour vérifier l'insertion
    public function testCacAndLvcInserted(): void
    {
        // Vérifiez que les données "Cac" sont insérées
        $cac = $this->entityManager->getRepository(Cac::class)->findOneBy(['id' => 1]);
        $this->assertNotNull($cac);
        $this->assertInstanceOf(Cac::class, $cac);

        // Vérifiez que les données "Lvc" sont insérées
        $lvc = $this->entityManager->getRepository(Lvc::class)->findOneBy(['id' => 1]);
        $this->assertNotNull($lvc);
        $this->assertInstanceOf(Lvc::class, $lvc);
    }
}