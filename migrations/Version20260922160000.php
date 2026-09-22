<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the chat feature: chat_enabled flag on labs and chat_message table.
 */
final class Version20260922160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add chat_enabled column to lab and create chat_message table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lab ADD chat_enabled TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('CREATE TABLE chat_message (id INT AUTO_INCREMENT NOT NULL, lab_id INT NOT NULL, user_id INT DEFAULT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_CHAT_MESSAGE_LAB_ID (lab_id), INDEX idx_chat_message_lab (lab_id, id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE chat_message ADD CONSTRAINT FK_CHAT_MESSAGE_LAB FOREIGN KEY (lab_id) REFERENCES lab (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_message ADD CONSTRAINT FK_CHAT_MESSAGE_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chat_message DROP FOREIGN KEY FK_CHAT_MESSAGE_LAB');
        $this->addSql('ALTER TABLE chat_message DROP FOREIGN KEY FK_CHAT_MESSAGE_USER');
        $this->addSql('DROP TABLE chat_message');
        $this->addSql('ALTER TABLE lab DROP chat_enabled');
    }
}
