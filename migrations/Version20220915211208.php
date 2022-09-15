<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220915211208 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE control_protocol (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE control_protocol_device (control_protocol_id INT NOT NULL, device_id INT NOT NULL, INDEX IDX_82E89E4833973C4A (control_protocol_id), INDEX IDX_82E89E4894A4C7D4 (device_id), PRIMARY KEY(control_protocol_id, device_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE control_protocol_device ADD CONSTRAINT FK_82E89E4833973C4A FOREIGN KEY (control_protocol_id) REFERENCES control_protocol (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE control_protocol_device ADD CONSTRAINT FK_82E89E4894A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE device DROP vnc');
        $this->addSql('ALTER TABLE device_instance ADD serial_port INT DEFAULT NULL');
        $this->addSql('INSERT INTO control_protocol (id, name) VALUES (NULL, \'vnc\'), (NULL, \'login\'), (NULL, \'serial\')');

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE control_protocol_device DROP FOREIGN KEY FK_82E89E4833973C4A');
        $this->addSql('DROP TABLE control_protocol');
        $this->addSql('DROP TABLE control_protocol_device');
        $this->addSql('ALTER TABLE device ADD vnc TINYINT(1) DEFAULT \'1\' NOT NULL');
        $this->addSql('ALTER TABLE device_instance DROP serial_port');
    }
}
