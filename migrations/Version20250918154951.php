<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250918154951 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE arch (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(20) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE iso ADD arch_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE iso ADD CONSTRAINT FK_61587F414F47FAB6 FOREIGN KEY (arch_id) REFERENCES arch (id)');
        $this->addSql('CREATE INDEX IDX_61587F414F47FAB6 ON iso (arch_id)');
        $this->addSql('ALTER TABLE lab CHANGE version version VARCHAR(10) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE operating_system ADD CONSTRAINT FK_BCF9A7814F47FAB6 FOREIGN KEY (arch_id) REFERENCES arch (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE arch');
        $this->addSql('ALTER TABLE iso DROP FOREIGN KEY FK_61587F414F47FAB6');
        $this->addSql('DROP INDEX IDX_61587F414F47FAB6 ON iso');
        $this->addSql('ALTER TABLE iso DROP arch_id');
        $this->addSql('ALTER TABLE lab CHANGE version version VARCHAR(10) DEFAULT \'1\' NOT NULL');
        $this->addSql('ALTER TABLE operating_system DROP FOREIGN KEY FK_BCF9A7814F47FAB6');
    }
}
