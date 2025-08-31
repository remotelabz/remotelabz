<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250831194129 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('ALTER TABLE device ADD cdrom_iso_filename VARCHAR(255) DEFAULT NULL, ADD cdrom_bus_type VARCHAR(255) DEFAULT NULL, ADD bios_filename VARCHAR(255) DEFAULT NULL, ADD bios_type VARCHAR(255) DEFAULT NULL, ADD other_options VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE lab ADD shared TINYINT(1) NOT NULL, CHANGE version version VARCHAR(10) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE operating_system ADD description LONGTEXT DEFAULT NULL, ADD arch VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user_group CHANGE permissions permissions JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE refresh_tokens (id INT AUTO_INCREMENT NOT NULL, refresh_token VARCHAR(128) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, username VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, valid DATETIME NOT NULL, UNIQUE INDEX UNIQ_9BACE7E1C74F2195 (refresh_token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE device DROP cdrom_iso_filename, DROP cdrom_bus_type, DROP bios_filename, DROP bios_type, DROP other_options');
        $this->addSql('ALTER TABLE lab DROP shared, CHANGE version version VARCHAR(10) DEFAULT \'1\' NOT NULL');
        $this->addSql('ALTER TABLE operating_system DROP description, DROP arch');
        $this->addSql('ALTER TABLE user_group CHANGE permissions permissions LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\'');
    }
}
