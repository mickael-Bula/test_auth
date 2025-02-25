<?php

namespace App\Tests\Unit;

use App\Entity\LastHigh;
use App\Entity\Lvc;
use App\Entity\Position;
use App\Entity\User;
use App\Repository\LvcRepository;
use App\Repository\PositionRepository;
use App\Repository\UserRepository;
use App\Services\PositionHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class PositionHandlerTest extends TestCase
{
    private MockObject $userRepository;
    private MockObject $positionRepository;

    private MockObject&PositionHandler $positionHandler;
    private MockObject $lvcRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);
        $logger = $this->createMock(LoggerInterface::class);
        $this->lvcRepository = $this->createMock(LvcRepository::class);
        $this->positionRepository = $this->createMock(PositionRepository::class);

        $user = new User();
        $user->setAmount(1000);

        // Mock du UserRepository
        $this->userRepository = $this->createMock(UserRepository::class);
        $entityManager->method('getRepository')->willReturn($this->userRepository);

        $this->positionHandler = $this->getMockBuilder(PositionHandler::class)
            ->setConstructorArgs([$entityManager, $security, $logger, $this->lvcRepository, $this->positionRepository])
            ->onlyMethods(['getCurrentUser', 'latentGainOrLoss'])
            ->getMock();

        $this->positionHandler->method('getCurrentUser')
            ->willReturn($user);

        $this->positionHandler->method('latentGainOrLoss')
            ->willReturn(10);
    }

    public function testAddSellResultToCapitalReturnsNumber(): void
    {
        // Arrange : configure les données
        $position = new Position();
        $position->setLvcSellTarget(12);
        $position->setLvcBuyTarget(10);
        $position->setQuantityToSell(5);

        $user = new User();
        $user->setAmount(1000);

        $this->userRepository->method('findOneBy')->willReturn($user);

        // Act : appel de la méthode à tester
        $capital = $this->positionHandler->addSellResultToCapital($position);

        // Assert : vérifie les résultats
        $this->assertIsNumeric($capital);
        $this->assertGreaterThan(0, $capital);
        $this->assertSame(1010.0, $capital);
        $this->assertEquals(1010, $capital);
    }

    public function testAddSellResultToCapitalUserNotFound(): void
    {
        // Configuration du mock UserRepository pour simuler l'absence d'utilisateur
        $this->userRepository->method('findOneBy')->willReturn(null);

        // Appel de la méthode
        $capital = $this->positionHandler->addSellResultToCapital(new Position());

        // Assertions
        $this->assertNull($capital);
    }

    public function testGetValorisation(): void
    {
        $capital = $this->positionHandler->getValorisation();
        $this->assertSame(1010.0, $capital);
    }

    public function testInvestmentRatio(): void
    {
        $capital = $this->positionHandler->investmentRatio();

        $this->assertSame(0.99, $capital);
    }

    public function testTargetRecoveryCapital(): void
    {
        $lvc = new Lvc();
        $lvc->setClosing(12);

        $position = new Position();
        $position->setQuantity(15);

        $sellTarget = 12;

        $this->lvcRepository->method('findOneBy')->willReturn($lvc);
        $this->lvcRepository->method('getLvcClosingAndTotalQuantity')->willReturn(800);

        $ratio = $this->positionHandler->targetRecoveryCapital($sellTarget, $position);

        $this->assertSame(800, $ratio);
    }

    /**
     * @dataProvider getQuantityToSell
     */
    public function testGetSellQuantity(float $ratio, ?int $sellTarget, ?int $expected, ?int $quantity): void
    {
        $position = new Position();
        $position->setQuantity($quantity);

        $sellTarget = 12;

        $result = $this->positionHandler->getSellQuantity($ratio, $position, $sellTarget);

        $this->assertSame($expected, $result);
    }

    /**
     * @return array<int, array<int, int|float|null>>
     */
    public function getQuantityToSell(): array
    {
        return [
            [0.0, null, null, 11],
            [1.0, null, null, 12],
            [25.0, null, null, 13],
            [26.0, 15, 63, 13],
            [50.0, 15, 63, 13],
            [51.0, 15, 15, 15],
            [75.0, 15, 15, 15],
            [76.0, 15, 15, 15],
            [99.0, 15, 15, 15],
        ];
    }

    public function testNewTradePositionsWithAmountNullReturnsEmptyArray(): void
    {
        $user = $this->positionHandler->getCurrentUser();
        $user->setAmount(null);
        $positions = $this->positionHandler->createNewTradePositions();

        $this->assertCount(0, $positions);
    }

    public function testNewTradePositionsWithTenThousandAsAmountReturnsArrayWithThreePositions(): void
    {
        $user = $this->positionHandler->getCurrentUser();
        $user->setAmount(10000);
        $positions = $this->positionHandler->createNewTradePositions();

        $this->assertCount(3, $positions);
    }

    public function testNewTradePositionsWithOneThousandAsAmountReturnsArrayWithOnePosition(): void
    {
        $user = $this->positionHandler->getCurrentUser();
        $user->setAmount(1000);
        $positions = $this->positionHandler->createNewTradePositions();

        $this->assertCount(1, $positions);
    }

    public function testGetPositionsToUpdateWithCurrentByLimitSetReturnsAnArrayOfThreePositions(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);
        $logger = $this->createMock(LoggerInterface::class);
        $lvcRepository = $this->createMock(LvcRepository::class);
        $positionRepository = $this->createMock(PositionRepository::class);

        $positionHandler = $this->getMockBuilder(PositionHandler::class)
            ->setConstructorArgs([$entityManager, $security, $logger, $lvcRepository, $positionRepository])
            ->onlyMethods(['isCurrentBuyLimitHasRunningPosition'])
            ->getMock();

        $positionHandler->method('isCurrentBuyLimitHasRunningPosition')->willReturn(true);

        $lastHigh = $this->createMock(LastHigh::class);
        $positions = [];
        for ($i = 0; $i < 3; ++$i) {
            $positions[$i] = new Position();
        }

        $result = $positionHandler->getPositionsToUpdate($lastHigh, $positions);

        $this->assertCount(3, $result);
    }

    public function testGetPositionsToUpdateWithCurrentByLimitNotSetReturnsEmptyArray(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);
        $logger = $this->createMock(LoggerInterface::class);
        $lvcRepository = $this->createMock(LvcRepository::class);
        $positionRepository = $this->createMock(PositionRepository::class);

        $positionHandler = $this->getMockBuilder(PositionHandler::class)
            ->setConstructorArgs([$entityManager, $security, $logger, $lvcRepository, $positionRepository])
            ->onlyMethods(['isCurrentBuyLimitHasRunningPosition'])
            ->getMock();

        $positionHandler->method('isCurrentBuyLimitHasRunningPosition')->willReturn(false);

        $lastHigh = $this->createMock(LastHigh::class);
        $positions = [];
        for ($i = 0; $i < 3; ++$i) {
            $positions[$i] = new Position();
        }

        $result = $positionHandler->getPositionsToUpdate($lastHigh, $positions);

        $this->assertCount(0, $result);
    }

    public function testIsCurrentByLimitHasRunningPositionReturnsFalse(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);
        $logger = $this->createMock(LoggerInterface::class);
        $lvcRepository = $this->createMock(LvcRepository::class);
        $positionRepository = $this->createMock(PositionRepository::class);

        $positionHandler = $this->getMockBuilder(PositionHandler::class)
            ->setConstructorArgs([$entityManager, $security, $logger, $lvcRepository, $positionRepository])
            ->onlyMethods(['getPositionsOfCurrentUser'])
            ->getMock();

        $buyLimit = new LastHigh();
        $buyLimit->setBuyLimit(7000);

        $buyLimitToCheck = new LastHigh();
        $buyLimitToCheck->setBuyLimit(7200);

        $positions = [];
        for ($i = 0; $i < 3; ++$i) {
            $positions[$i] = new Position();
            $positions[$i]->setBuyLimit($buyLimit);
        }

        $positionsToCheck = [];
        for ($i = 0; $i < 3; ++$i) {
            $positionsToCheck[$i] = new Position();
            $positionsToCheck[$i]->setBuyLimit($buyLimitToCheck);
        }

        $positionHandler->method('getPositionsOfCurrentUser')
            ->with('isRunning')
            ->willReturn($positions);

        $this->assertFalse($positionHandler->isCurrentBuyLimitHasRunningPosition($positionsToCheck));
    }

    public function testIsCurrentByLimitHasRunningPositionReturnsTrue(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);
        $logger = $this->createMock(LoggerInterface::class);
        $lvcRepository = $this->createMock(LvcRepository::class);
        $positionRepository = $this->createMock(PositionRepository::class);

        $positionHandler = $this->getMockBuilder(PositionHandler::class)
            ->setConstructorArgs([$entityManager, $security, $logger, $lvcRepository, $positionRepository])
            ->onlyMethods(['getPositionsOfCurrentUser'])
            ->getMock();

        $buyLimit = new LastHigh();
        $buyLimit->setBuyLimit(7000);

        $positions = [];
        for ($i = 0; $i < 3; ++$i) {
            $positions[$i] = new Position();
            $positions[$i]->setBuyLimit($buyLimit);
        }

        $positionsToCheck = [];
        for ($i = 0; $i < 3; ++$i) {
            $positionsToCheck[$i] = new Position();
            $positionsToCheck[$i]->setBuyLimit($buyLimit);
        }

        $positionHandler->method('getPositionsOfCurrentUser')
            ->with('isRunning')
            ->willReturn($positions);

        $this->assertTrue($positionHandler->isCurrentBuyLimitHasRunningPosition($positionsToCheck));
    }
}
