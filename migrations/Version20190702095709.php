<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;
/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190702095709 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE lab_instance (id INT AUTO_INCREMENT NOT NULL, instance_id INT DEFAULT NULL, user_id INT DEFAULT NULL, uuid VARCHAR(255) NOT NULL, is_started TINYINT(1) NOT NULL, INDEX IDX_983C9A433A51721D (instance_id), INDEX IDX_983C9A43A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE lab_instance ADD CONSTRAINT FK_983C9A433A51721D FOREIGN KEY (instance_id) REFERENCES lab (id)');
        $this->addSql('ALTER TABLE lab_instance ADD CONSTRAINT FK_983C9A43A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('DROP TABLE instance');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE instance (id INT AUTO_INCREMENT NOT NULL, lab_id INT DEFAULT NULL, device_id INT DEFAULT NULL, user_id INT DEFAULT NULL, network_interface_id INT DEFAULT NULL, uuid VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci, is_started TINYINT(1) NOT NULL, INDEX IDX_4230B1DE94A4C7D4 (device_id), INDEX IDX_4230B1DECE793EEA (network_interface_id), INDEX IDX_4230B1DE628913D5 (lab_id), INDEX IDX_4230B1DEA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE instance ADD CONSTRAINT FK_4230B1DE628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id)');
        $this->addSql('ALTER TABLE instance ADD CONSTRAINT FK_4230B1DE94A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id)');
        $this->addSql('ALTER TABLE instance ADD CONSTRAINT FK_4230B1DEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE instance ADD CONSTRAINT FK_4230B1DECE793EEA FOREIGN KEY (network_interface_id) REFERENCES network_interface (id)');
        $this->addSql('DROP TABLE lab_instance');
    }
}
