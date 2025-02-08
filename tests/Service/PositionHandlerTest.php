<?php

namespace App\Tests\Service;

use App\Entity\Cac;
use App\Entity\LastHigh;
use App\Entity\Position;
use App\Entity\User;
use App\Repository\LvcRepository;
use App\Services\PositionHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class PositionHandlerTest extends TestCase
{
    /**
     * @dataProvider getRatio
     */
    public function testSetPosition(float $ratio, ?int $sellTarget, ?int $quantity): void
    {
        // Création des mocks pour les dépendances
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $securityMock = $this->createMock(Security::class);
        $loggerMock = $this->createMock(LoggerInterface::class);

        $lvcRepository = $this->createMock(LvcRepository::class);
        $lvcRepository->method('getLvcClosingAndTotalQuantity')->willReturn(800);

        // Création de l'instance de PositionHandler en passant les mocks et en forçant le retour de getCurrentUser
        $positionHandler = $this->getMockBuilder(PositionHandler::class)
            ->onlyMethods(['getCurrentUser', 'investmentRatio', 'latentGainOrLoss'])
            ->setConstructorArgs([$entityManagerMock, $securityMock, $loggerMock, $lvcRepository])
            ->getMock();

        // Configure le mock de l'utilisateur courant
        $userMock = $this->createMock(User::class);
        $userMock->method('getAmount')->willReturn(1000.0);

        $positionHandler->method('getCurrentUser')->willReturn($userMock);
        $positionHandler->method('investmentRatio')->willReturn($ratio);
        $positionHandler->method('latentGainOrLoss')->willReturn(10);

        // Création des mocks pour les entités LastHigh et Position
        $lastHighMock = $this->createMock(LastHigh::class);
        $lastHighMock->method('getBuyLimit')->willReturn(100.0);
        $lastHighMock->method('getLvcBuyLimit')->willReturn(50.0);
        $dailyCacMock = $this->createMock(Cac::class);
        $dailyCacMock->method('getCreatedAt')->willReturn(new \DateTime('2023-01-01'));
        $lastHighMock->method('getDailyCac')->willReturn($dailyCacMock);

        $positionMock = $this->createMock(Position::class);
        $positionMock->method('getQuantity')->willReturn(15);

        // Définit les attentes sur le mock de Position avant d'appeler setPosition
        $positionMock->expects($this->once())
            ->method('setBuyLimit')
            ->with($lastHighMock);
        $positionMock->expects($this->once())
            ->method('setBuyTarget')
            ->with(100.0); // Pour `key = 0`, delta 'cac' est 0%
        $positionMock->expects($this->once())
            ->method('setWaiting')
            ->with(true);
        $positionMock->expects($this->once())
            ->method('setBuyDate')
            ->with(new \DateTime('2023-01-01'));
        $positionMock->expects($this->once())
            ->method('setUserPosition')
            ->with($userMock);
        $positionMock->expects($this->once())
            ->method('setLvcBuyTarget')
            ->with(50.0); // Pour `key = 0`, delta 'lvc' est 0%
        $positionMock->expects($this->once())
            ->method('setQuantity')
            ->with((int) round(Position::LINE_VALUE / 50.0)); // 750 / 50 = 15

        if ($ratio > 25) {
            $positionMock->expects($this->once())
                ->method('setLvcSellTarget')
                ->with($sellTarget); // 50.0 * 1.2 = 60.0
            $positionMock->expects($this->once())
                ->method('setQuantityToSell')
                ->with($quantity);
        }

        // Simulation de l'appel à `persist`
        $entityManagerMock->expects($this->once())
            ->method('persist')
            ->with($positionMock);

        // Définit la clé pour les delta
        $key = 0;

        // Appel de la méthode à tester
        $positionHandler->setPosition($lastHighMock, $positionMock, $key);
    }

    /**
     * NOTE : La déclaration des types correspond à un tableau avec en clé un int et pour valeurs int|float|null.
     *
     * @return array<int, array<int, int|float|null>>
     */
    public function getRatio(): array
    {
        return [
            [0.0, null, null],
            [1.0, null, null],
            [25.0, null, null],
            [50.0, 60, 13],
            [75.0, 60, 15],
            [100.0, 60, 15],
        ];
    }
}
