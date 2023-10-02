<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230913123745 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE lab DROP FOREIGN KEY FK_61D6B1C49B9A36D0');
        $this->addSql('DROP INDEX UNIQ_61D6B1C49B9A36D0 ON lab');
        $this->addSql('ALTER TABLE lab DROP network_settings_id');
        $this->addSql('ALTER TABLE lab_instance ADD CONSTRAINT FK_983C9A4334128B91 FOREIGN KEY (network_id) REFERENCES network (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_983C9A4334128B91 ON lab_instance (network_id)');
        $this->addSql('ALTER TABLE network_interface ADD connection INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE lab ADD network_settings_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE lab ADD CONSTRAINT FK_61D6B1C49B9A36D0 FOREIGN KEY (network_settings_id) REFERENCES network_settings (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_61D6B1C49B9A36D0 ON lab (network_settings_id)');
        $this->addSql('ALTER TABLE lab_instance DROP FOREIGN KEY FK_983C9A4334128B91');
        $this->addSql('DROP INDEX UNIQ_983C9A4334128B91 ON lab_instance');
        $this->addSql('ALTER TABLE network_interface DROP connection');
    }
}
