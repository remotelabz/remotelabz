<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190620131116 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE instance ADD network_interface_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE instance ADD CONSTRAINT FK_4230B1DECE793EEA FOREIGN KEY (network_interface_id) REFERENCES network_interface (id)');
        $this->addSql('CREATE INDEX IDX_4230B1DECE793EEA ON instance (network_interface_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE instance DROP FOREIGN KEY FK_4230B1DECE793EEA');
        $this->addSql('DROP INDEX IDX_4230B1DECE793EEA ON instance');
        $this->addSql('ALTER TABLE instance DROP network_interface_id');
    }
}
