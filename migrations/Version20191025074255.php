<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191025074255 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE editor_data ADD device_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE editor_data ADD CONSTRAINT FK_473263AA94A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_473263AA94A4C7D4 ON editor_data (device_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE editor_data DROP FOREIGN KEY FK_473263AA94A4C7D4');
        $this->addSql('DROP INDEX UNIQ_473263AA94A4C7D4 ON editor_data');
        $this->addSql('ALTER TABLE editor_data DROP device_id');
    }
}
