<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241103191318 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute deux index pour accélérer la récupération des données quotidiennes.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_cac_created_at ON cac (created_at)');
        $this->addSql('CREATE INDEX idx_lvc_created_at ON lvc (created_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX idx_lvc_created_at');
        $this->addSql('DROP INDEX idx_cac_created_at');
    }
}
