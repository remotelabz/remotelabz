<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230929094839 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE invitation_code (id INT AUTO_INCREMENT NOT NULL, lab_id INT DEFAULT NULL, code VARCHAR(8) NOT NULL, mail VARCHAR(180) NOT NULL, expiry_date DATETIME NOT NULL, UNIQUE INDEX UNIQ_BA14FCCC77153098 (code), INDEX IDX_BA14FCCC628913D5 (lab_id), UNIQUE INDEX IDX_LAB_MAIL (lab_id, mail), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE invitation_code ADD CONSTRAINT FK_BA14FCCC628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id)');
        //$this->addSql('ALTER TABLE lab DROP has_timer, DROP timer');
        //$this->addSql('ALTER TABLE lab_instance DROP timer_end');
        //$this->addSql('ALTER TABLE network_interface ADD connection INT DEFAULT 0 NOT NULL, CHANGE vlan vlan INT DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE invitation_code');
        //$this->addSql('ALTER TABLE lab ADD has_timer TINYINT(1) NOT NULL, ADD timer VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`');
        //$this->addSql('ALTER TABLE lab_instance ADD timer_end DATETIME DEFAULT NULL');
        //$this->addSql('ALTER TABLE network_interface DROP connection, CHANGE vlan vlan INT DEFAULT 0 NOT NULL');
    }
}
