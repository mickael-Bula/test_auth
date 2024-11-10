<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241019195545 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE position ALTER buy_date DROP NOT NULL');
        $this->addSql('ALTER TABLE position ALTER sell_date DROP NOT NULL');
        $this->addSql('ALTER TABLE position ALTER lvc_buy_target DROP NOT NULL');
        $this->addSql('ALTER TABLE position ALTER quantity DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE position ALTER buy_date SET NOT NULL');
        $this->addSql('ALTER TABLE position ALTER sell_date SET NOT NULL');
        $this->addSql('ALTER TABLE position ALTER lvc_buy_target SET NOT NULL');
        $this->addSql('ALTER TABLE position ALTER quantity SET NOT NULL');
    }
}
