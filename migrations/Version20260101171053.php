<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260101171053 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE directory (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, path VARCHAR(1000) DEFAULT NULL, description LONGTEXT DEFAULT NULL, level INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, parent_id INT DEFAULT NULL, INDEX idx_directory_parent (parent_id), INDEX idx_directory_path (path), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE directory ADD CONSTRAINT FK_467844DA727ACA70 FOREIGN KEY (parent_id) REFERENCES directory (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE device ADD directory_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE device ADD CONSTRAINT FK_92FB68E2C94069F FOREIGN KEY (directory_id) REFERENCES directory (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_92FB68E2C94069F ON device (directory_id)');
        $this->addSql('ALTER TABLE iso ADD directory_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE iso ADD CONSTRAINT FK_61587F412C94069F FOREIGN KEY (directory_id) REFERENCES directory (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_61587F412C94069F ON iso (directory_id)');
        $this->addSql('ALTER TABLE lab CHANGE version version VARCHAR(10) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE operating_system ADD directory_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE operating_system ADD CONSTRAINT FK_BCF9A7812C94069F FOREIGN KEY (directory_id) REFERENCES directory (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_BCF9A7812C94069F ON operating_system (directory_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE directory DROP FOREIGN KEY FK_467844DA727ACA70');
        $this->addSql('DROP TABLE directory');
        $this->addSql('ALTER TABLE device DROP FOREIGN KEY FK_92FB68E2C94069F');
        $this->addSql('DROP INDEX IDX_92FB68E2C94069F ON device');
        $this->addSql('ALTER TABLE device DROP directory_id');
        $this->addSql('ALTER TABLE iso DROP FOREIGN KEY FK_61587F412C94069F');
        $this->addSql('DROP INDEX IDX_61587F412C94069F ON iso');
        $this->addSql('ALTER TABLE iso DROP directory_id');
        $this->addSql('ALTER TABLE lab CHANGE version version VARCHAR(10) DEFAULT \'1\' NOT NULL');
        $this->addSql('ALTER TABLE operating_system DROP FOREIGN KEY FK_BCF9A7812C94069F');
        $this->addSql('DROP INDEX IDX_BCF9A7812C94069F ON operating_system');
        $this->addSql('ALTER TABLE operating_system DROP directory_id');
    }
}
