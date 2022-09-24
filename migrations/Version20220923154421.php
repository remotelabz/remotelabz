<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220923154421 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE control_protocol_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE control_protocol_type_device (control_protocol_type_id INT NOT NULL, device_id INT NOT NULL, INDEX IDX_65BC8659B7C02C3E (control_protocol_type_id), INDEX IDX_65BC865994A4C7D4 (device_id), PRIMARY KEY(control_protocol_type_id, device_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE control_protocol_type_instance (id INT AUTO_INCREMENT NOT NULL, control_protocol_type_id INT DEFAULT NULL, device_instance_id INT DEFAULT NULL, user_id INT DEFAULT NULL, _group_id INT DEFAULT NULL, port INT NOT NULL, uuid VARCHAR(255) NOT NULL, owned_by VARCHAR(255) NOT NULL, INDEX IDX_AA90BE39B7C02C3E (control_protocol_type_id), INDEX IDX_AA90BE3948D126DB (device_instance_id), INDEX IDX_AA90BE39A76ED395 (user_id), INDEX IDX_AA90BE39D0949C27 (_group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE control_protocol_type_device ADD CONSTRAINT FK_65BC8659B7C02C3E FOREIGN KEY (control_protocol_type_id) REFERENCES control_protocol_type (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE control_protocol_type_device ADD CONSTRAINT FK_65BC865994A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE control_protocol_type_instance ADD CONSTRAINT FK_AA90BE39B7C02C3E FOREIGN KEY (control_protocol_type_id) REFERENCES control_protocol_type (id)');
        $this->addSql('ALTER TABLE control_protocol_type_instance ADD CONSTRAINT FK_AA90BE3948D126DB FOREIGN KEY (device_instance_id) REFERENCES device_instance (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE control_protocol_type_instance ADD CONSTRAINT FK_AA90BE39A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE control_protocol_type_instance ADD CONSTRAINT FK_AA90BE39D0949C27 FOREIGN KEY (_group_id) REFERENCES _group (id)');
        $this->addSql('ALTER TABLE device DROP vnc');
        $this->addSql('ALTER TABLE device_instance DROP remote_port');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE control_protocol_type_device DROP FOREIGN KEY FK_65BC8659B7C02C3E');
        $this->addSql('ALTER TABLE control_protocol_type_instance DROP FOREIGN KEY FK_AA90BE39B7C02C3E');
        $this->addSql('DROP TABLE control_protocol_type');
        $this->addSql('DROP TABLE control_protocol_type_device');
        $this->addSql('DROP TABLE control_protocol_type_instance');
        $this->addSql('ALTER TABLE device ADD vnc TINYINT(1) DEFAULT \'1\' NOT NULL');
        $this->addSql('ALTER TABLE device_instance ADD remote_port INT DEFAULT NULL');
    }
}
