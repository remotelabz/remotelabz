<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201104105639 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE network_interface ADD vlan INT DEFAULT 0 NOT NULL');
        $this->addSql('SET SQL_SAFE_UPDATES = 0');
        $this->addSql('ALTER TABLE device ADD vnc TINYINT(1) DEFAULT \'1\' NOT NULL');
        $this->addSql('ALTER TABLE device_instance ADD remote_port INT DEFAULT NULL');
        $this->addSql(
            'UPDATE device_instance AS a
            SET remote_port = (SELECT DISTINCT remote_port
            FROM network_interface_instance AS b
            WHERE b.device_instance_id = a.id)'
        );
        $this->addSql('ALTER TABLE network_interface_instance DROP remote_port');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE device DROP vnc');
        $this->addSql('ALTER TABLE network_interface_instance ADD remote_port INT DEFAULT NULL');
        $this->addSql(
            'UPDATE network_interface_instance AS a
            SET remote_port = (SELECT remote_port
            FROM device_instance AS b
            WHERE a.device_instance_id = b.id)'
        );
        $this->addSql('ALTER TABLE device_instance DROP remote_port');
        $this->addSql('ALTER TABLE network_interface DROP vlan');
    }
}
