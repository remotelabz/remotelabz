<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251004072943 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE device_template_usage (device_id INT NOT NULL, lab_id INT NOT NULL, INDEX IDX_45707C4F94A4C7D4 (device_id), INDEX IDX_45707C4F628913D5 (lab_id), PRIMARY KEY (device_id, lab_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE device_template_usage ADD CONSTRAINT FK_45707C4F94A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE device_template_usage ADD CONSTRAINT FK_45707C4F628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id) ON DELETE CASCADE');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('ALTER TABLE lab CHANGE version version VARCHAR(10) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE operating_system DROP arch');
        $this->addSql('ALTER TABLE user_group CHANGE permissions permissions JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE refresh_tokens (id INT AUTO_INCREMENT NOT NULL, refresh_token VARCHAR(128) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, username VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, valid DATETIME NOT NULL, UNIQUE INDEX UNIQ_9BACE7E1C74F2195 (refresh_token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE device_template_usage DROP FOREIGN KEY FK_45707C4F94A4C7D4');
        $this->addSql('ALTER TABLE device_template_usage DROP FOREIGN KEY FK_45707C4F628913D5');
        $this->addSql('DROP TABLE device_template_usage');
        $this->addSql('ALTER TABLE lab CHANGE version version VARCHAR(10) DEFAULT \'1\' NOT NULL');
        $this->addSql('ALTER TABLE operating_system ADD arch VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user_group CHANGE permissions permissions LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\'');
    }
}
