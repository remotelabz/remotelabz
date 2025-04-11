<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200721115710 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE jitsi_call (id INT AUTO_INCREMENT NOT NULL, state VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE lab_instance ADD jitsi_call_id INT DEFAULT NULL, DROP is_call_started');
        $this->addSql('ALTER TABLE lab_instance ADD CONSTRAINT FK_983C9A436CA4214E FOREIGN KEY (jitsi_call_id) REFERENCES jitsi_call (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_983C9A436CA4214E ON lab_instance (jitsi_call_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE lab_instance DROP FOREIGN KEY FK_983C9A436CA4214E');
        $this->addSql('DROP TABLE jitsi_call');
        $this->addSql('DROP INDEX UNIQ_983C9A436CA4214E ON lab_instance');
        $this->addSql('ALTER TABLE lab_instance ADD is_call_started TINYINT(1) NOT NULL, DROP jitsi_call_id');
    }
}
