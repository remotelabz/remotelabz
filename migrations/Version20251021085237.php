<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251021085237 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE device_iso (device_id INT NOT NULL, iso_id INT NOT NULL, INDEX IDX_8BA6A5D894A4C7D4 (device_id), INDEX IDX_8BA6A5D8DA0E9E59 (iso_id), PRIMARY KEY (device_id, iso_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE device_iso ADD CONSTRAINT FK_8BA6A5D894A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE device_iso ADD CONSTRAINT FK_8BA6A5D8DA0E9E59 FOREIGN KEY (iso_id) REFERENCES iso (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE device DROP cdrom_iso_filename');
        $this->addSql('ALTER TABLE lab CHANGE version version VARCHAR(10) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE device_iso DROP FOREIGN KEY FK_8BA6A5D894A4C7D4');
        $this->addSql('ALTER TABLE device_iso DROP FOREIGN KEY FK_8BA6A5D8DA0E9E59');
        $this->addSql('DROP TABLE device_iso');
        $this->addSql('ALTER TABLE device ADD cdrom_iso_filename VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE lab CHANGE version version VARCHAR(10) DEFAULT \'1\' NOT NULL');
    }
}
