<?php

namespace App\DataFixtures;

use App\Entity\Cac;
use App\Entity\Lvc;
use App\Entity\User;
use App\Entity\LastHigh;
use App\Entity\Position;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SecondFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $firstCac = $manager->getRepository(Cac::class)->findOneBy([], ['id' => 'ASC']);
        $firstLvc = $manager->getRepository(Lvc::class)->findOneBy([], ['id' => 'ASC']);
        $user = $manager->getRepository(User::class)->findOneBy([], ['id' => 'DESC']);

        // Crée un LastHigh
        $lastHigh = new LastHigh();
        $lastHigher = $firstCac->getHigher();
        $lastHigh->setHigher($lastHigher);
        $buyLimit = $lastHigher - ($lastHigher * Position::SPREAD);
        $lastHigh->setBuyLimit(round($buyLimit, 2));
        $lastHigh->setDailyCac($firstCac);

        $lvcHigher = $firstLvc->getHigher();
        $lastHigh->setLvcHigher($lvcHigher);
        $lvcBuyLimit = $lvcHigher - ($lvcHigher * (Position::SPREAD * 2));
        $lastHigh->setLvcBuyLimit(round($lvcBuyLimit, 2));
        $lastHigh->setDailyLvc($firstLvc);
        $manager->persist($lastHigh);

        $user->setLastCacUpdated($firstCac);
        $user->setHigher($lastHigh);
        $manager->persist($user);

        $manager->flush();
        $manager->getConnection()->commit();
    }
}
