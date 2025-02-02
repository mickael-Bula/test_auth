<?php

namespace App\DataFixtures;

use App\Entity\Cac;
use App\Entity\Lvc;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class FirstFixtures extends Fixture
{
    /**
     * @throws \Exception
     */
    public function load(ObjectManager $manager): void
    {
        $data = json_decode(
            file_get_contents('data_export.json'),
            true, 512,
            JSON_THROW_ON_ERROR
        );

        // Crée un utilisateur
        $user = new User();
        $user->setUsername('testUser');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('testPassword');
        $manager->persist($user);

        // Charge les données Cac
        foreach ($data['cacData'] as $cacData) {
            $cac = new Cac();
            $cac->setCreatedAt(new \DateTime($cacData['createdAt']));
            $cac->setClosing($cacData['closing']);
            $cac->setOpening($cacData['opening']);
            $cac->setHigher($cacData['higher']);
            $cac->setLower($cacData['lower']);

            // Affichage des données avant la persistance
            $manager->persist($cac);
        }

        // Charge les données Lvc
        foreach ($data['lvcData'] as $lvcData) {
            $lvc = new Lvc();
            $lvc->setCreatedAt(new \DateTime($lvcData['createdAt']));
            $lvc->setClosing($lvcData['closing']);
            $lvc->setOpening($lvcData['opening']);
            $lvc->setHigher($lvcData['higher']);
            $lvc->setLower($lvcData['lower']);
            $manager->persist($lvc);
        }
        // Enregistre les données en base de test
        $manager->flush();
    }
}
