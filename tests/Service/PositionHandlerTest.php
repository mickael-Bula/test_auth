<?php

namespace App\Tests\Service;

use App\Entity\Cac;
use App\Entity\User;
use App\Entity\LastHigh;
use App\Entity\Position;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use App\Services\PositionHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class PositionHandlerTest extends TestCase
{
    public function testSetPosition(): void
    {
        // Création des mocks pour les dépendances
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $securityMock = $this->createMock(Security::class);
        $loggerMock = $this->createMock(LoggerInterface::class);

        // Création de l'instance de PositionHandler en passant les mocks et en forçant le retour de getCurrentUser
        $positionHandler = $this->getMockBuilder(PositionHandler::class)
            ->onlyMethods(['getCurrentUser'])
            ->setConstructorArgs([$entityManagerMock, $securityMock, $loggerMock])
            ->getMock();

        // Configurer le mock de l'utilisateur courant
        $userMock = $this->createMock(User::class);
        $positionHandler->method('getCurrentUser')->willReturn($userMock);

        // Création des mocks pour les entités LastHigh et Position
        $lastHighMock = $this->createMock(LastHigh::class);
        $lastHighMock->method('getBuyLimit')->willReturn(100.0);
        $lastHighMock->method('getLvcBuyLimit')->willReturn(50.0);
        $dailyCacMock = $this->createMock(Cac::class);
        $dailyCacMock->method('getCreatedAt')->willReturn(new \DateTime('2023-01-01'));
        $lastHighMock->method('getDailyCac')->willReturn($dailyCacMock);

        $positionMock = $this->createMock(Position::class);

        // Définir les expectations sur le mock de Position avant d'appeler setPosition
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
            ->with((int)round(Position::LINE_VALUE / 50.0));
        $positionMock->expects($this->once())
            ->method('setLvcSellTarget')
            ->with(60.0); // 50.0 * 1.2 = 60.0

        // Simulation de l'appel à `persist`
        $entityManagerMock->expects($this->once())
            ->method('persist')
            ->with($positionMock);

        // Définir la clé pour les delta
        $key = 0;

        // Appel de la méthode à tester
        $result = $positionHandler->setPosition($lastHighMock, $positionMock, $key);

        // Vérification que le résultat est bien une instance de Position
        $this->assertNotEmpty($result);
    }

}
