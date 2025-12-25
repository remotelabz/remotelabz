<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251222161722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_notifications (id INT AUTO_INCREMENT NOT NULL, user_id VARCHAR(255) DEFAULT NULL, type VARCHAR(50) NOT NULL, message LONGTEXT NOT NULL, related_uuid VARCHAR(255) DEFAULT NULL, context JSON DEFAULT NULL, is_read TINYINT NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE lab CHANGE version version VARCHAR(10) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE user_notifications');
        $this->addSql('ALTER TABLE lab CHANGE version version VARCHAR(10) DEFAULT \'1\' NOT NULL');
    }
}
