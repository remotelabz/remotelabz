<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230412132032 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE network_device (id INT AUTO_INCREMENT NOT NULL, lab_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, count INT NOT NULL, type VARCHAR(255) DEFAULT NULL, top INT NOT NULL, left_position INT NOT NULL, visibility INT NOT NULL, postfix INT NOT NULL, INDEX IDX_6B3CB1E8628913D5 (lab_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE text_object (id INT AUTO_INCREMENT NOT NULL, lab_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) DEFAULT NULL, data VARCHAR(1500) DEFAULT NULL, newdata VARCHAR(255) DEFAULT NULL, INDEX IDX_BD21321F628913D5 (lab_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE network_device ADD CONSTRAINT FK_6B3CB1E8628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE text_object ADD CONSTRAINT FK_BD21321F628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE device ADD template VARCHAR(255) DEFAULT NULL, ADD config INT DEFAULT 0 NOT NULL, ADD status INT DEFAULT 0 NOT NULL, ADD ethernet INT DEFAULT 1 NOT NULL, CHANGE config_data config_data VARCHAR(255) DEFAULT \'\' NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE network_device');
        $this->addSql('DROP TABLE text_object');
        $this->addSql('ALTER TABLE device DROP template, DROP config, DROP status, DROP ethernet, CHANGE config_data config_data VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`');
    }
}
