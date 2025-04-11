<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191008140509 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE network_interface ADD network_settings_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE network_interface ADD CONSTRAINT FK_B3518C349B9A36D0 FOREIGN KEY (network_settings_id) REFERENCES network_settings (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B3518C349B9A36D0 ON network_interface (network_settings_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE network_interface DROP FOREIGN KEY FK_B3518C349B9A36D0');
        $this->addSql('DROP INDEX UNIQ_B3518C349B9A36D0 ON network_interface');
        $this->addSql('ALTER TABLE network_interface DROP network_settings_id');
    }
}
