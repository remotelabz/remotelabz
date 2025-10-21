<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251020201609 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE flavor_disk (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, disk BIGINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE flavor DROP disk');
        $this->addSql('ALTER TABLE lab CHANGE version version VARCHAR(10) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE operating_system ADD flavor_disk_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE operating_system ADD CONSTRAINT FK_BCF9A7817D65F3B5 FOREIGN KEY (flavor_disk_id) REFERENCES flavor_disk (id)');
        $this->addSql('CREATE INDEX IDX_BCF9A7817D65F3B5 ON operating_system (flavor_disk_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE flavor_disk');
        $this->addSql('ALTER TABLE flavor ADD disk BIGINT NOT NULL');
        $this->addSql('ALTER TABLE lab CHANGE version version VARCHAR(10) DEFAULT \'1\' NOT NULL');
        $this->addSql('ALTER TABLE operating_system DROP FOREIGN KEY FK_BCF9A7817D65F3B5');
        $this->addSql('DROP INDEX IDX_BCF9A7817D65F3B5 ON operating_system');
        $this->addSql('ALTER TABLE operating_system DROP flavor_disk_id');
    }
}
