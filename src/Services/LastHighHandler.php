<?php

namespace App\Services;

use App\Entity\Cac;
use App\Entity\Lvc;
use App\Entity\User;
use App\Entity\LastHigh;
use App\Entity\Position;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;

class LastHighHandler
{
    private EntityManagerInterface $entityManager;
    private PositionHandler $positionHandler;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        PositionHandler        $positionHandler,
        LoggerInterface        $myAppLogger
    )
    {
        $this->entityManager = $entityManager;
        $this->positionHandler = $positionHandler;
        $this->logger = $myAppLogger;
    }

    public function setHigherToNewRegisteredUser(User $user): void
    {
        // On récupère le plus haut le plus récent du Cac et on en fait le plus haut de l'utilisateur.
        $cac = $this->entityManager->getRepository(Cac::class)->findOneBy([], ['id' => 'DESC']);

        $lastHighEntity = $this->setNewUserLastCacHigher($cac);

        $lastHighEntity = $this->setNewUserLastLvcHigher($lastHighEntity, $cac);

        // Je persiste les données pour créer l'id du lashHigh avant de l'assigner à l'utilisateur.
        $lastHighRepository = $this->entityManager->getRepository(LastHigh::class);
        $lastHighRepository->add($lastHighEntity, true);
        $user->setHigher($lastHighEntity);

        // On trace la dernière cotation utilisée pour le calcul des données de trading de l'utilisateur.
        $user->setLastCacUpdated($cac);

        $this->entityManager->flush();

        // je crée également les positions en rapport avec la nouvelle buyLimit
        $this->positionHandler->setPositions($lastHighEntity, []);
    }

    public function setNewUserLastCacHigher(?Cac $cac): LastHigh
    {
        $lastHigher = $cac?->getHigher();

        // je crée une nouvelle instance de LastHigh et je l'hydrate
        $lastHighEntity = new LastHigh();
        $lastHighEntity->setHigher($lastHigher);
        $buyLimit = $lastHigher - ($lastHigher * Position::SPREAD);    // buyLimit se situe 6 % sous higher
        $lastHighEntity->setBuyLimit(round($buyLimit, 2));
        $lastHighEntity->setDailyCac($cac);

        return $lastHighEntity;
    }

    public function setNewUserLastLvcHigher(LastHigh $lastHighEntity, ?Cac $cac): LastHigh
    {
        // à partir de l'entité Cac, je récupère l'objet LVC contemporain
        $lvcRepository = $this->entityManager->getRepository(Lvc::class);
        $lvc = $lvcRepository->findOneBy(["createdAt" => $cac?->getCreatedAt()]);
        if (!$lvc) {
            $date = $cac?->getCreatedAt() !== null ? $cac?->getCreatedAt()?->format("D/M/Y") : null;
            $this->logger->error(sprintf("Pas de LVC correspondant au CAC fournit en date du %s", $date));
        }
        $lvcHigher = $lvc->getHigher();

        // j'hydrate l'instance LastHigh avec les données de l'objet Lvc récupéré
        $lastHighEntity->setLvcHigher($lvcHigher);

        // lvcBuyLimit fixée au double du SPREAD en raison d'un levier x2
        $lvcBuyLimit = $lvcHigher - ($lvcHigher * (Position::SPREAD * 2));
        $lastHighEntity->setLvcBuyLimit(round($lvcBuyLimit, 2));
        $lastHighEntity->setDailyLvc($lvc);

        return $lastHighEntity;
    }
}