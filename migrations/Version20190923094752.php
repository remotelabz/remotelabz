<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190923094752 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE network_interface_instance ADD device_instance_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE network_interface_instance ADD CONSTRAINT FK_245762D48D126DB FOREIGN KEY (device_instance_id) REFERENCES device_instance (id)');
        $this->addSql('CREATE INDEX IDX_245762D48D126DB ON network_interface_instance (device_instance_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE network_interface_instance DROP FOREIGN KEY FK_245762D48D126DB');
        $this->addSql('DROP INDEX IDX_245762D48D126DB ON network_interface_instance');
        $this->addSql('ALTER TABLE network_interface_instance DROP device_instance_id');
    }
}
