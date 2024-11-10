<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241012211856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE cac_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE last_high_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE lvc_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE position_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE cac (id INT NOT NULL, created_at DATE NOT NULL, closing DOUBLE PRECISION NOT NULL, opening DOUBLE PRECISION NOT NULL, lower DOUBLE PRECISION NOT NULL, higher DOUBLE PRECISION NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE last_high (id INT NOT NULL, daily_cac_id INT DEFAULT NULL, daily_lvc_id INT DEFAULT NULL, higher DOUBLE PRECISION NOT NULL, buy_limit DOUBLE PRECISION NOT NULL, lvc_higher DOUBLE PRECISION NOT NULL, lvc_buy_limit DOUBLE PRECISION NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_672E2009AA5DF1C9 ON last_high (daily_cac_id)');
        $this->addSql('CREATE INDEX IDX_672E200989CB088E ON last_high (daily_lvc_id)');
        $this->addSql('CREATE TABLE lvc (id INT NOT NULL, created_at DATE NOT NULL, closing DOUBLE PRECISION NOT NULL, opening DOUBLE PRECISION NOT NULL, higher DOUBLE PRECISION NOT NULL, lower DOUBLE PRECISION NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE position (id INT NOT NULL, buy_limit_id INT DEFAULT NULL, user_position_id INT DEFAULT NULL, buy_target DOUBLE PRECISION NOT NULL, sell_target DOUBLE PRECISION NOT NULL, buy_date DATE NOT NULL, sell_date DATE NOT NULL, is_active BOOLEAN NOT NULL, is_closed BOOLEAN NOT NULL, is_waiting BOOLEAN NOT NULL, is_running BOOLEAN NOT NULL, lvc_buy_target DOUBLE PRECISION NOT NULL, lvc_sell_target DOUBLE PRECISION NOT NULL, quantity INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_462CE4F55301742F ON position (buy_limit_id)');
        $this->addSql('CREATE INDEX IDX_462CE4F5749FE7D3 ON position (user_position_id)');
        $this->addSql('ALTER TABLE last_high ADD CONSTRAINT FK_672E2009AA5DF1C9 FOREIGN KEY (daily_cac_id) REFERENCES cac (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE last_high ADD CONSTRAINT FK_672E200989CB088E FOREIGN KEY (daily_lvc_id) REFERENCES lvc (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE position ADD CONSTRAINT FK_462CE4F55301742F FOREIGN KEY (buy_limit_id) REFERENCES last_high (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE position ADD CONSTRAINT FK_462CE4F5749FE7D3 FOREIGN KEY (user_position_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "user" ADD last_cac_updated_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD higher_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D6495D69E1F5 FOREIGN KEY (last_cac_updated_id) REFERENCES cac (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D6496D44FFD6 FOREIGN KEY (higher_id) REFERENCES last_high (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_8D93D6495D69E1F5 ON "user" (last_cac_updated_id)');
        $this->addSql('CREATE INDEX IDX_8D93D6496D44FFD6 ON "user" (higher_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D6495D69E1F5');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D6496D44FFD6');
        $this->addSql('DROP SEQUENCE cac_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE last_high_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE lvc_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE position_id_seq CASCADE');
        $this->addSql('ALTER TABLE last_high DROP CONSTRAINT FK_672E2009AA5DF1C9');
        $this->addSql('ALTER TABLE last_high DROP CONSTRAINT FK_672E200989CB088E');
        $this->addSql('ALTER TABLE position DROP CONSTRAINT FK_462CE4F55301742F');
        $this->addSql('ALTER TABLE position DROP CONSTRAINT FK_462CE4F5749FE7D3');
        $this->addSql('DROP TABLE cac');
        $this->addSql('DROP TABLE last_high');
        $this->addSql('DROP TABLE lvc');
        $this->addSql('DROP TABLE position');
        $this->addSql('DROP INDEX IDX_8D93D6495D69E1F5');
        $this->addSql('DROP INDEX IDX_8D93D6496D44FFD6');
        $this->addSql('ALTER TABLE "user" DROP last_cac_updated_id');
        $this->addSql('ALTER TABLE "user" DROP higher_id');
    }
}
