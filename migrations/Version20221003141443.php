<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221003141443 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE control_protocol_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE control_protocol_type_device (control_protocol_type_id INT NOT NULL, device_id INT NOT NULL, INDEX IDX_65BC8659B7C02C3E (control_protocol_type_id), INDEX IDX_65BC865994A4C7D4 (device_id), PRIMARY KEY(control_protocol_type_id, device_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE control_protocol_type_instance (id INT AUTO_INCREMENT NOT NULL, control_protocol_type_id INT DEFAULT NULL, device_instance_id INT DEFAULT NULL, user_id INT DEFAULT NULL, _group_id INT DEFAULT NULL, port INT NOT NULL, uuid VARCHAR(255) NOT NULL, owned_by VARCHAR(255) NOT NULL, INDEX IDX_AA90BE39B7C02C3E (control_protocol_type_id), INDEX IDX_AA90BE3948D126DB (device_instance_id), INDEX IDX_AA90BE39A76ED395 (user_id), INDEX IDX_AA90BE39D0949C27 (_group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE worker (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, ipv4 VARCHAR(255) NOT NULL, ipv6 VARCHAR(255) DEFAULT NULL, available TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE control_protocol_type_device ADD CONSTRAINT FK_65BC8659B7C02C3E FOREIGN KEY (control_protocol_type_id) REFERENCES control_protocol_type (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE control_protocol_type_device ADD CONSTRAINT FK_65BC865994A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE control_protocol_type_instance ADD CONSTRAINT FK_AA90BE39B7C02C3E FOREIGN KEY (control_protocol_type_id) REFERENCES control_protocol_type (id)');
        $this->addSql('ALTER TABLE control_protocol_type_instance ADD CONSTRAINT FK_AA90BE3948D126DB FOREIGN KEY (device_instance_id) REFERENCES device_instance (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE control_protocol_type_instance ADD CONSTRAINT FK_AA90BE39A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE control_protocol_type_instance ADD CONSTRAINT FK_AA90BE39D0949C27 FOREIGN KEY (_group_id) REFERENCES _group (id)');
        $this->addSql('ALTER TABLE device ADD nb_cpu INT NOT NULL, ADD nb_core INT DEFAULT NULL, ADD nb_socket INT DEFAULT NULL, ADD nb_thread INT DEFAULT NULL, DROP vnc');
        $this->addSql('ALTER TABLE device_instance ADD nb_cpu INT NOT NULL, ADD nb_socket INT DEFAULT NULL, ADD nb_thread INT DEFAULT NULL, CHANGE remote_port nb_core INT DEFAULT NULL');
        $this->addSql('UPDATE device SET nb_cpu = 1');
        $this->addSql('INSERT INTO control_protocol_type (id, name) VALUES (NULL, \'vnc\')');
        $this->addSql('INSERT INTO control_protocol_type (id, name) VALUES (NULL, \'serial\')');
        $this->addSql('INSERT INTO control_protocol_type (id, name) VALUES (NULL, \'login\')');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE control_protocol_type_device DROP FOREIGN KEY FK_65BC8659B7C02C3E');
        $this->addSql('ALTER TABLE control_protocol_type_instance DROP FOREIGN KEY FK_AA90BE39B7C02C3E');
        $this->addSql('DROP TABLE control_protocol_type');
        $this->addSql('DROP TABLE control_protocol_type_device');
        $this->addSql('DROP TABLE control_protocol_type_instance');
        $this->addSql('DROP TABLE worker');
        $this->addSql('ALTER TABLE device ADD vnc TINYINT(1) DEFAULT \'1\' NOT NULL, DROP nb_cpu, DROP nb_core, DROP nb_socket, DROP nb_thread');
        $this->addSql('ALTER TABLE device_instance ADD remote_port INT DEFAULT NULL, DROP nb_cpu, DROP nb_core, DROP nb_socket, DROP nb_thread');
    }
}
