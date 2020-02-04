<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200121121401 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE activity ADD _group_id INT NOT NULL, DROP scope, DROP _group');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AD0949C27 FOREIGN KEY (_group_id) REFERENCES _group (id)');
        $this->addSql('CREATE INDEX IDX_AC74095AD0949C27 ON activity (_group_id)');
        $this->addSql('ALTER TABLE user_group CHANGE created_at created_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095AD0949C27');
        $this->addSql('DROP INDEX IDX_AC74095AD0949C27 ON activity');
        $this->addSql('ALTER TABLE activity ADD scope VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, ADD _group VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, DROP _group_id');
        $this->addSql('ALTER TABLE user_group CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP');
    }
}
