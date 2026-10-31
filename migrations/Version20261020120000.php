<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Glossaire des correspondances entre les images EVE-NG (attribut `image` d'un
 * <node> du fichier .unl) et les OperatingSystem RemoteLabz.
 *
 * Cette table rend les imports suivants "one-click" : des qu'un admin a resolu
 * une image, la decision est memorisee puis proposee automatiquement.
 */
final class Version20261020120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create eve_ng_image_mapping table (EVE-NG image -> RemoteLabz operating system glossary)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE eve_ng_image_mapping (id INT AUTO_INCREMENT NOT NULL, eve_image VARCHAR(255) NOT NULL, operating_system_id INT NOT NULL, created_at DATETIME NOT NULL, created_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_30C042BBD8B6270 (eve_image), INDEX IDX_30C042BA391D4AD (operating_system_id), INDEX IDX_30C042BB03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE eve_ng_image_mapping ADD CONSTRAINT FK_EVE_NG_OS FOREIGN KEY (operating_system_id) REFERENCES operating_system (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE eve_ng_image_mapping ADD CONSTRAINT FK_EVE_NG_USER FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE eve_ng_image_mapping DROP FOREIGN KEY FK_EVE_NG_OS');
        $this->addSql('ALTER TABLE eve_ng_image_mapping DROP FOREIGN KEY FK_EVE_NG_USER');
        $this->addSql('DROP TABLE eve_ng_image_mapping');
    }
}
