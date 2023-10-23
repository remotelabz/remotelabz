<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230922121127 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE exercise');
        $this->addSql('DROP TABLE ip');
        $this->addSql('ALTER TABLE lab ADD has_timer TINYINT(1) NOT NULL, ADD timer TIME DEFAULT NULL');
        $this->addSql('ALTER TABLE network_interface DROP connection, CHANGE vlan vlan INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE exercise (id INT AUTO_INCREMENT NOT NULL, lab_id INT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, document VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_AEDAD51C628913D5 (lab_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE ip (id INT AUTO_INCREMENT NOT NULL, network_id INT DEFAULT NULL, INDEX IDX_A5E3B32D34128B91 (network_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE exercise ADD CONSTRAINT FK_AEDAD51C628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE ip ADD CONSTRAINT FK_A5E3B32D34128B91 FOREIGN KEY (network_id) REFERENCES network (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE lab DROP has_timer, DROP timer');
        $this->addSql('ALTER TABLE network_interface ADD connection INT DEFAULT 0 NOT NULL, CHANGE vlan vlan INT DEFAULT NULL');
    }
}
