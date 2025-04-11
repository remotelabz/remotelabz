<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240612120952 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pdu (id INT AUTO_INCREMENT NOT NULL, brand VARCHAR(255) NOT NULL, model VARCHAR(255) NOT NULL, number_of_outlets INT NOT NULL, ip VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE pdu_outlet_device (id INT AUTO_INCREMENT NOT NULL, pdu_id INT DEFAULT NULL, device_id INT DEFAULT NULL, outlet INT NOT NULL, INDEX IDX_47BB0C7013B33163 (pdu_id), UNIQUE INDEX UNIQ_47BB0C7094A4C7D4 (device_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE pdu_outlet_device ADD CONSTRAINT FK_47BB0C7013B33163 FOREIGN KEY (pdu_id) REFERENCES pdu (id)');
        $this->addSql('ALTER TABLE pdu_outlet_device ADD CONSTRAINT FK_47BB0C7094A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id)');
        $this->addSql('ALTER TABLE device ADD ip VARCHAR(255) DEFAULT NULL, ADD port INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pdu_outlet_device DROP FOREIGN KEY FK_47BB0C7013B33163');
        $this->addSql('ALTER TABLE pdu_outlet_device DROP FOREIGN KEY FK_47BB0C7094A4C7D4');
        $this->addSql('DROP TABLE pdu');
        $this->addSql('DROP TABLE pdu_outlet_device');
        $this->addSql('ALTER TABLE device DROP ip, DROP port');
    }
}
