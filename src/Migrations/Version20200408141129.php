<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200408141129 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE activity_user DROP FOREIGN KEY FK_8E570DDB81C06096');
        $this->addSql('DROP TABLE activity');
        $this->addSql('DROP TABLE activity_user');
        $this->addSql('ALTER TABLE device_instance DROP is_started');
        $this->addSql('ALTER TABLE lab_instance DROP is_started');
        $this->addSql('ALTER TABLE network_interface_instance DROP is_started');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE activity (id INT AUTO_INCREMENT NOT NULL, lab_id INT DEFAULT NULL, network_id INT DEFAULT NULL, author_id INT DEFAULT NULL, _group_id INT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, internet_allowed TINYINT(1) NOT NULL, interconnected TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, last_updated DATETIME DEFAULT NULL, INDEX IDX_AC74095AF675F31B (author_id), UNIQUE INDEX UNIQ_AC74095A34128B91 (network_id), INDEX IDX_AC74095A628913D5 (lab_id), INDEX IDX_AC74095AD0949C27 (_group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE activity_user (activity_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_8E570DDBA76ED395 (user_id), INDEX IDX_8E570DDB81C06096 (activity_id), PRIMARY KEY(activity_id, user_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A34128B91 FOREIGN KEY (network_id) REFERENCES network (id)');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id)');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AD0949C27 FOREIGN KEY (_group_id) REFERENCES _group (id)');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AF675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE activity_user ADD CONSTRAINT FK_8E570DDB81C06096 FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE activity_user ADD CONSTRAINT FK_8E570DDBA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE device_instance ADD is_started TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE lab_instance ADD is_started TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE network_interface_instance ADD is_started TINYINT(1) NOT NULL');
    }
}
