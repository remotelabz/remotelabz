<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230412135129 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE network_device (id INT AUTO_INCREMENT NOT NULL, lab_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, count INT NOT NULL, type VARCHAR(255) DEFAULT NULL, top INT NOT NULL, left_position INT NOT NULL, visibility INT NOT NULL, postfix INT NOT NULL, INDEX IDX_6B3CB1E8628913D5 (lab_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE text_object (id INT AUTO_INCREMENT NOT NULL, lab_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) DEFAULT NULL, data VARCHAR(1500) DEFAULT NULL, newdata VARCHAR(255) DEFAULT NULL, INDEX IDX_BD21321F628913D5 (lab_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE network_device ADD CONSTRAINT FK_6B3CB1E8628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE text_object ADD CONSTRAINT FK_BD21321F628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE device ADD delay INT NOT NULL, ADD console VARCHAR(50) DEFAULT NULL, ADD icon VARCHAR(100) DEFAULT NULL, ADD url VARCHAR(70) DEFAULT NULL, ADD template VARCHAR(255) DEFAULT NULL, ADD image VARCHAR(255) DEFAULT NULL, ADD count INT DEFAULT NULL, ADD postfix INT DEFAULT NULL, ADD port INT DEFAULT NULL, ADD config INT DEFAULT 0 NOT NULL, ADD config_data VARCHAR(255) DEFAULT \'\' NOT NULL, ADD status INT DEFAULT 0 NOT NULL, ADD ethernet INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE network_device');
        $this->addSql('DROP TABLE text_object');
        $this->addSql('ALTER TABLE device DROP delay, DROP console, DROP icon, DROP url, DROP template, DROP image, DROP count, DROP postfix, DROP port, DROP config, DROP config_data, DROP status, DROP ethernet');
    }
}
