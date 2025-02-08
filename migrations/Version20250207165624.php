<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250207165624 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AJoute le champ quantity_to_sell représentant la quantité à revendre à la clôture d"une position.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE position ADD quantity_to_sell INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE position DROP quantity_to_sell');
    }
}
