<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230406093317 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE device CHANGE delay delay INT NOT NULL, CHANGE console console VARCHAR(50) NOT NULL, CHANGE image image VARCHAR(255) NOT NULL, CHANGE url url VARCHAR(70) NOT NULL, CHANGE icon icon VARCHAR(100) NOT NULL, CHANGE count count INT NOT NULL, CHANGE postfix postfix INT NOT NULL');
        $this->addSql('ALTER TABLE network_device DROP FOREIGN KEY network_device_ibfk_1');
        $this->addSql('ALTER TABLE network_device CHANGE lab_id lab_id INT DEFAULT NULL, CHANGE type type VARCHAR(255) DEFAULT NULL, CHANGE top top INT NOT NULL, CHANGE left_position left_position INT NOT NULL, CHANGE visibility visibility INT NOT NULL');
        $this->addSql('ALTER TABLE network_device ADD CONSTRAINT FK_6B3CB1E8628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE network_device RENAME INDEX lab_id TO IDX_6B3CB1E8628913D5');
        $this->addSql('ALTER TABLE text_object DROP FOREIGN KEY text_object_ibfk_1');
        $this->addSql('ALTER TABLE text_object CHANGE type type VARCHAR(255) DEFAULT NULL, CHANGE data data VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE text_object ADD CONSTRAINT FK_BD21321F628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE text_object RENAME INDEX lab_id TO IDX_BD21321F628913D5');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE device CHANGE delay delay INT DEFAULT 0, CHANGE console console VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE icon icon VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE url url VARCHAR(70) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE image image VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE count count INT DEFAULT NULL, CHANGE postfix postfix INT DEFAULT NULL');
        $this->addSql('ALTER TABLE network_device DROP FOREIGN KEY FK_6B3CB1E8628913D5');
        $this->addSql('ALTER TABLE network_device CHANGE lab_id lab_id INT NOT NULL, CHANGE type type VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, CHANGE top top INT DEFAULT NULL, CHANGE left_position left_position INT DEFAULT NULL, CHANGE visibility visibility INT DEFAULT NULL');
        $this->addSql('ALTER TABLE network_device ADD CONSTRAINT network_device_ibfk_1 FOREIGN KEY (lab_id) REFERENCES lab (id)');
        $this->addSql('ALTER TABLE network_device RENAME INDEX idx_6b3cb1e8628913d5 TO lab_id');
        $this->addSql('ALTER TABLE text_object DROP FOREIGN KEY FK_BD21321F628913D5');
        $this->addSql('ALTER TABLE text_object CHANGE type type VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, CHANGE data data VARCHAR(1000) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('ALTER TABLE text_object ADD CONSTRAINT text_object_ibfk_1 FOREIGN KEY (lab_id) REFERENCES lab (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE text_object RENAME INDEX idx_bd21321f628913d5 TO lab_id');
    }
}
