<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250922154114 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE arch (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(20) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE iso (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, filename VARCHAR(255) DEFAULT NULL, filename_url VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, arch_id INT DEFAULT NULL, INDEX IDX_61587F414F47FAB6 (arch_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE iso ADD CONSTRAINT FK_61587F414F47FAB6 FOREIGN KEY (arch_id) REFERENCES arch (id)');
        $this->addSql('ALTER TABLE lab CHANGE version version VARCHAR(10) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE operating_system ADD arch_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE operating_system ADD CONSTRAINT FK_BCF9A7814F47FAB6 FOREIGN KEY (arch_id) REFERENCES arch (id)');
        $this->addSql('CREATE INDEX IDX_BCF9A7814F47FAB6 ON operating_system (arch_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE iso DROP FOREIGN KEY FK_61587F414F47FAB6');
        $this->addSql('ALTER TABLE operating_system DROP FOREIGN KEY FK_BCF9A7814F47FAB6');
        $this->addSql('ALTER TABLE operating_system DROP arch_id');
        $this->addSql('DROP TABLE arch');
        $this->addSql('DROP TABLE iso');
        $this->addSql('ALTER TABLE lab CHANGE version version VARCHAR(10) DEFAULT \'1\' NOT NULL');
        //$this->addSql('DROP INDEX IDX_BCF9A7814F47FAB6 ON operating_system');
    }
}
