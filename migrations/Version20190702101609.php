<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190702101609 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE network_interface_instance (id INT AUTO_INCREMENT NOT NULL, network_interface_id INT DEFAULT NULL, user_id INT DEFAULT NULL, remote_port INT NOT NULL, uuid VARCHAR(255) NOT NULL, is_started TINYINT(1) NOT NULL, INDEX IDX_245762DCE793EEA (network_interface_id), INDEX IDX_245762DA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE device_instance (id INT AUTO_INCREMENT NOT NULL, device_id INT DEFAULT NULL, user_id INT DEFAULT NULL, uuid VARCHAR(255) NOT NULL, is_started TINYINT(1) NOT NULL, INDEX IDX_CC04E8FE94A4C7D4 (device_id), INDEX IDX_CC04E8FEA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE network_interface_instance ADD CONSTRAINT FK_245762DCE793EEA FOREIGN KEY (network_interface_id) REFERENCES network_interface (id)');
        $this->addSql('ALTER TABLE network_interface_instance ADD CONSTRAINT FK_245762DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE device_instance ADD CONSTRAINT FK_CC04E8FE94A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id)');
        $this->addSql('ALTER TABLE device_instance ADD CONSTRAINT FK_CC04E8FEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE lab_instance DROP FOREIGN KEY FK_983C9A433A51721D');
        $this->addSql('DROP INDEX IDX_983C9A433A51721D ON lab_instance');
        $this->addSql('ALTER TABLE lab_instance CHANGE instance_id lab_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE lab_instance ADD CONSTRAINT FK_983C9A43628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id)');
        $this->addSql('CREATE INDEX IDX_983C9A43628913D5 ON lab_instance (lab_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE network_interface_instance');
        $this->addSql('DROP TABLE device_instance');
        $this->addSql('ALTER TABLE lab_instance DROP FOREIGN KEY FK_983C9A43628913D5');
        $this->addSql('DROP INDEX IDX_983C9A43628913D5 ON lab_instance');
        $this->addSql('ALTER TABLE lab_instance CHANGE lab_id instance_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE lab_instance ADD CONSTRAINT FK_983C9A433A51721D FOREIGN KEY (instance_id) REFERENCES lab (id)');
        $this->addSql('CREATE INDEX IDX_983C9A433A51721D ON lab_instance (instance_id)');
    }
}
