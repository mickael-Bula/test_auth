<?php

namespace App\Command;

use App\Entity\Cac;
use App\Entity\Lvc;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:export-data',
    description: 'Exporte dans un fichier JSON les données de la base de développement afin de créer les fixtures.',
)]
class ExportDataCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    /**
     * @throws \JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Récupère les 20 premières lignes de la table Cac
        $cacRepository = $this->entityManager->getRepository(Cac::class);
        $data = $cacRepository->findBy([], null, 20);

        // Transforme les objets en tableaux pour faciliter l'exportation
        $cacData = array_map(static function (Cac $cac) {
            return [
                'id' => $cac->getId(),
                'createdAt' => $cac->getCreatedAt()?->format('Y-m-d H:i:s'),
                'closing' => $cac->getClosing(),
                'opening' => $cac->getOpening(),
                'lower' => $cac->getLower(),
                'higher' => $cac->getHigher(),
            ];
        }, $data);

        // Récupère les 20 premières lignes de la table Lvc
        $lvcRepository = $this->entityManager->getRepository(Lvc::class);
        $data = $lvcRepository->findBy([], null, 20);

        // Transforme les objets en tableaux pour faciliter l'exportation
        $lvcData = array_map(static function (Lvc $lvc) {
            return [
                'id' => $lvc->getId(),
                'createdAt' => $lvc->getCreatedAt()?->format('Y-m-d H:i:s'),
                'closing' => $lvc->getClosing(),
                'opening' => $lvc->getOpening(),
                'lower' => $lvc->getLower(),
                'higher' => $lvc->getHigher(),
            ];
        }, $data);

        // Organise les données dans un tableau global
        $data = [
            'cacData' => $cacData,
            'lvcData' => $lvcData,
        ];

        $dataJson = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        // Sauvegarde dans un fichier JSON
        file_put_contents('data_export.json', $dataJson);

        $io->success('Données exportées avec succès !');

        return Command::SUCCESS;
    }
}
