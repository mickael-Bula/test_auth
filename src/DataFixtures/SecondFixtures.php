<?php

namespace App\DataFixtures;

use App\Entity\Cac;
use App\Entity\LastHigh;
use App\Entity\Lvc;
use App\Entity\Position;
use App\Entity\User;
use App\Repository\CacRepository;
use App\Repository\LvcRepository;
use App\Repository\UserRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

class SecondFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @throws Exception
     */
    public function load(ObjectManager $manager): void
    {
        /** @var CacRepository $cacRepository */
        $cacRepository = $manager->getRepository(Cac::class);
        $firstCac = $cacRepository->findOneBy([], ['id' => 'ASC']);

        /** @var LvcRepository $lvcRepository */
        $lvcRepository = $manager->getRepository(Lvc::class);
        $firstLvc = $lvcRepository->findOneBy([], ['id' => 'ASC']);

        /** @var UserRepository $userRepository */
        $userRepository = $manager->getRepository(User::class);
        $lastUser = $userRepository->findOneBy([], ['id' => 'DESC']);

        // Crée un LastHigh
        $lastHigh = new LastHigh();
        $lastHigher = $firstCac?->getHigher();
        $lastHigh->setHigher($lastHigher);
        $buyLimit = $lastHigher - ($lastHigher * Position::SPREAD);
        $lastHigh->setBuyLimit(round($buyLimit, 2));
        $lastHigh->setDailyCac($firstCac);

        $lvcHigher = $firstLvc?->getHigher();
        $lastHigh->setLvcHigher($lvcHigher);
        $lvcBuyLimit = $lvcHigher - ($lvcHigher * (Position::SPREAD * 2));
        $lastHigh->setLvcBuyLimit(round($lvcBuyLimit, 2));
        $lastHigh->setDailyLvc($firstLvc);
        $manager->persist($lastHigh);

        if (null !== $lastUser) {
            $lastUser->setLastCacUpdated($firstCac);
            $lastUser->setHigher($lastHigh);
            $manager->persist($lastUser);
        } else {
            throw new \RuntimeException('Aucun utilisateur trouvé pour la mise à jour.');
        }

        $manager->flush();

        $this->entityManager->getConnection()->commit();
    }

    public function getDependencies(): array
    {
        return [
            FirstFixtures::class,
        ];
    }
}
