<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201116082219 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE device_instance_log (id INT AUTO_INCREMENT NOT NULL, device_instance_id INT DEFAULT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, type VARCHAR(255) NOT NULL, scope VARCHAR(255) NOT NULL, INDEX IDX_F5A2DA2C48D126DB (device_instance_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE device_instance_log ADD CONSTRAINT FK_F5A2DA2C48D126DB FOREIGN KEY (device_instance_id) REFERENCES device_instance (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE device_instance_log');
    }
}
