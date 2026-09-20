<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260920091657 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add site_messages table for general login page messages and information messages';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE site_messages (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, title VARCHAR(255) DEFAULT NULL, message LONGTEXT NOT NULL, active TINYINT NOT NULL, target_user_ids JSON DEFAULT NULL, target_group_ids JSON DEFAULT NULL, created_by VARCHAR(180) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE site_messages');

    }
}
