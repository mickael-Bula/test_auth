<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241019193144 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE position ALTER sell_target DROP NOT NULL');
        $this->addSql('ALTER TABLE position ALTER is_active DROP NOT NULL');
        $this->addSql('ALTER TABLE position ALTER is_closed DROP NOT NULL');
        $this->addSql('ALTER TABLE position ALTER is_waiting DROP NOT NULL');
        $this->addSql('ALTER TABLE position ALTER is_running DROP NOT NULL');
        $this->addSql('ALTER TABLE position ALTER lvc_sell_target DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE position ALTER sell_target SET NOT NULL');
        $this->addSql('ALTER TABLE position ALTER is_active SET NOT NULL');
        $this->addSql('ALTER TABLE position ALTER is_closed SET NOT NULL');
        $this->addSql('ALTER TABLE position ALTER is_waiting SET NOT NULL');
        $this->addSql('ALTER TABLE position ALTER is_running SET NOT NULL');
        $this->addSql('ALTER TABLE position ALTER lvc_sell_target SET NOT NULL');
    }
}
