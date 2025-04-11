<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200826095407 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE exercise');
        $this->addSql('DROP TABLE ip');
        $this->addSql('ALTER TABLE network_settings DROP prefix4, DROP prefix6');
        $this->addSql('ALTER TABLE lab_instance DROP FOREIGN KEY FK_983C9A439B9A36D0');
        $this->addSql('DROP INDEX UNIQ_983C9A439B9A36D0 ON lab_instance');
        $this->addSql('ALTER TABLE lab_instance CHANGE network_settings_id network_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE lab_instance ADD CONSTRAINT FK_983C9A4334128B91 FOREIGN KEY (network_id) REFERENCES network (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_983C9A4334128B91 ON lab_instance (network_id)');
        $this->addSql('ALTER TABLE lab DROP FOREIGN KEY FK_61D6B1C49B9A36D0');
        $this->addSql('DROP INDEX UNIQ_61D6B1C49B9A36D0 ON lab');
        $this->addSql('ALTER TABLE lab DROP network_settings_id');
        $this->addSql('ALTER TABLE network ADD ip__long BIGINT NOT NULL, ADD netmask_addr VARCHAR(255) NOT NULL, ADD netmask__long BIGINT NOT NULL, CHANGE cidr ip_addr VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE exercise (id INT AUTO_INCREMENT NOT NULL, lab_id INT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, document VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_AEDAD51C628913D5 (lab_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE ip (id INT AUTO_INCREMENT NOT NULL, network_id INT DEFAULT NULL, INDEX IDX_A5E3B32D34128B91 (network_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE exercise ADD CONSTRAINT FK_AEDAD51C628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id)');
        $this->addSql('ALTER TABLE ip ADD CONSTRAINT FK_A5E3B32D34128B91 FOREIGN KEY (network_id) REFERENCES network (id)');
        $this->addSql('ALTER TABLE lab ADD network_settings_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE lab ADD CONSTRAINT FK_61D6B1C49B9A36D0 FOREIGN KEY (network_settings_id) REFERENCES network_settings (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_61D6B1C49B9A36D0 ON lab (network_settings_id)');
        $this->addSql('ALTER TABLE lab_instance DROP FOREIGN KEY FK_983C9A4334128B91');
        $this->addSql('DROP INDEX UNIQ_983C9A4334128B91 ON lab_instance');
        $this->addSql('ALTER TABLE lab_instance CHANGE network_id network_settings_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE lab_instance ADD CONSTRAINT FK_983C9A439B9A36D0 FOREIGN KEY (network_settings_id) REFERENCES network_settings (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_983C9A439B9A36D0 ON lab_instance (network_settings_id)');
        $this->addSql('ALTER TABLE network ADD cidr VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, DROP ip_addr, DROP ip__long, DROP netmask_addr, DROP netmask__long');
        $this->addSql('ALTER TABLE network_settings ADD prefix4 INT DEFAULT NULL, ADD prefix6 INT DEFAULT NULL');
    }
}
