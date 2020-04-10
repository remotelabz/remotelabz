<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200406080618 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE lab_connexion DROP FOREIGN KEY FK_1D0F1A4E8D566613');
        $this->addSql('ALTER TABLE activity_course DROP FOREIGN KEY FK_44E88FB9591CC992');
        $this->addSql('ALTER TABLE user_course DROP FOREIGN KEY FK_73CC7484591CC992');
        $this->addSql('CREATE TABLE activity_user (activity_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_8E570DDB81C06096 (activity_id), INDEX IDX_8E570DDBA76ED395 (user_id), PRIMARY KEY(activity_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE _group (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, visibility SMALLINT NOT NULL, picture_filename VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, uuid VARCHAR(255) NOT NULL, INDEX IDX_E7F8A859727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_group (id INT AUTO_INCREMENT NOT NULL, group_id INT DEFAULT NULL, user_id INT DEFAULT NULL, permissions LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', role VARCHAR(255) NOT NULL, created_at DATETIME DEFAULT NULL, INDEX IDX_8F02BF9DFE54D947 (group_id), INDEX IDX_8F02BF9DA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE activity_user ADD CONSTRAINT FK_8E570DDB81C06096 FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE activity_user ADD CONSTRAINT FK_8E570DDBA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE _group ADD CONSTRAINT FK_E7F8A859727ACA70 FOREIGN KEY (parent_id) REFERENCES _group (id)');
        $this->addSql('ALTER TABLE user_group ADD CONSTRAINT FK_8F02BF9DFE54D947 FOREIGN KEY (group_id) REFERENCES _group (id)');
        $this->addSql('ALTER TABLE user_group ADD CONSTRAINT FK_8F02BF9DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('DROP TABLE activity_course');
        $this->addSql('DROP TABLE connexion');
        $this->addSql('DROP TABLE course');
        $this->addSql('DROP TABLE hypervisor');
        $this->addSql('DROP TABLE hypervisor_settings');
        $this->addSql('DROP TABLE lab_connexion');
        $this->addSql('DROP TABLE user_course');
        $this->addSql('ALTER TABLE activity ADD author_id INT DEFAULT NULL, ADD _group_id INT NOT NULL, ADD created_at DATETIME NOT NULL, ADD last_updated DATETIME DEFAULT NULL, DROP scope');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AF675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AD0949C27 FOREIGN KEY (_group_id) REFERENCES _group (id)');
        $this->addSql('CREATE INDEX IDX_AC74095AF675F31B ON activity (author_id)');
        $this->addSql('CREATE INDEX IDX_AC74095AD0949C27 ON activity (_group_id)');
        $this->addSql('ALTER TABLE device_instance ADD _group_id INT DEFAULT NULL, ADD state VARCHAR(255) NOT NULL, ADD owned_by VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE device_instance ADD CONSTRAINT FK_CC04E8FED0949C27 FOREIGN KEY (_group_id) REFERENCES _group (id)');
        $this->addSql('CREATE INDEX IDX_CC04E8FED0949C27 ON device_instance (_group_id)');
        $this->addSql('ALTER TABLE lab_instance ADD _group_id INT DEFAULT NULL, ADD owned_by VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE lab_instance ADD CONSTRAINT FK_983C9A43D0949C27 FOREIGN KEY (_group_id) REFERENCES _group (id)');
        $this->addSql('CREATE INDEX IDX_983C9A43D0949C27 ON lab_instance (_group_id)');
        $this->addSql('ALTER TABLE network_interface_instance ADD _group_id INT DEFAULT NULL, ADD owned_by VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE network_interface_instance ADD CONSTRAINT FK_245762DD0949C27 FOREIGN KEY (_group_id) REFERENCES _group (id)');
        $this->addSql('CREATE INDEX IDX_245762DD0949C27 ON network_interface_instance (_group_id)');
        $this->addSql('ALTER TABLE user ADD uuid VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095AD0949C27');
        $this->addSql('ALTER TABLE device_instance DROP FOREIGN KEY FK_CC04E8FED0949C27');
        $this->addSql('ALTER TABLE _group DROP FOREIGN KEY FK_E7F8A859727ACA70');
        $this->addSql('ALTER TABLE user_group DROP FOREIGN KEY FK_8F02BF9DFE54D947');
        $this->addSql('ALTER TABLE lab_instance DROP FOREIGN KEY FK_983C9A43D0949C27');
        $this->addSql('ALTER TABLE network_interface_instance DROP FOREIGN KEY FK_245762DD0949C27');
        $this->addSql('CREATE TABLE activity_course (activity_id INT NOT NULL, course_id INT NOT NULL, INDEX IDX_44E88FB981C06096 (activity_id), INDEX IDX_44E88FB9591CC992 (course_id), PRIMARY KEY(activity_id, course_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE connexion (id INT AUTO_INCREMENT NOT NULL, network_interface1_id INT NOT NULL, network_interface2_id INT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, vlan1 INT NOT NULL, vlan2 INT NOT NULL, UNIQUE INDEX UNIQ_936BF99C84C8F025 (network_interface2_id), UNIQUE INDEX UNIQ_936BF99C967D5FCB (network_interface1_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE course (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE hypervisor (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, command VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, arguments VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE hypervisor_settings (id INT AUTO_INCREMENT NOT NULL, vncport_min INT NOT NULL, vncport_max INT NOT NULL, wsport_min INT NOT NULL, wsport_max INT NOT NULL, console_port_min INT NOT NULL, ip VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, ipv6 VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, interface_min_id INT NOT NULL, control_interface_id INT NOT NULL, interface_id INT NOT NULL, network_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE lab_connexion (lab_id INT NOT NULL, connexion_id INT NOT NULL, INDEX IDX_1D0F1A4E628913D5 (lab_id), INDEX IDX_1D0F1A4E8D566613 (connexion_id), PRIMARY KEY(lab_id, connexion_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE user_course (user_id INT NOT NULL, course_id INT NOT NULL, INDEX IDX_73CC7484A76ED395 (user_id), INDEX IDX_73CC7484591CC992 (course_id), PRIMARY KEY(user_id, course_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE activity_course ADD CONSTRAINT FK_44E88FB9591CC992 FOREIGN KEY (course_id) REFERENCES course (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE activity_course ADD CONSTRAINT FK_44E88FB981C06096 FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE connexion ADD CONSTRAINT FK_936BF99C84C8F025 FOREIGN KEY (network_interface2_id) REFERENCES network_interface (id)');
        $this->addSql('ALTER TABLE connexion ADD CONSTRAINT FK_936BF99C967D5FCB FOREIGN KEY (network_interface1_id) REFERENCES network_interface (id)');
        $this->addSql('ALTER TABLE lab_connexion ADD CONSTRAINT FK_1D0F1A4E628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lab_connexion ADD CONSTRAINT FK_1D0F1A4E8D566613 FOREIGN KEY (connexion_id) REFERENCES connexion (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_course ADD CONSTRAINT FK_73CC7484591CC992 FOREIGN KEY (course_id) REFERENCES course (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_course ADD CONSTRAINT FK_73CC7484A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('DROP TABLE activity_user');
        $this->addSql('DROP TABLE _group');
        $this->addSql('DROP TABLE user_group');
        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095AF675F31B');
        $this->addSql('DROP INDEX IDX_AC74095AF675F31B ON activity');
        $this->addSql('DROP INDEX IDX_AC74095AD0949C27 ON activity');
        $this->addSql('ALTER TABLE activity ADD scope VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, DROP author_id, DROP _group_id, DROP created_at, DROP last_updated');
        $this->addSql('DROP INDEX IDX_CC04E8FED0949C27 ON device_instance');
        $this->addSql('ALTER TABLE device_instance DROP _group_id, DROP state, DROP owned_by');
        $this->addSql('DROP INDEX IDX_983C9A43D0949C27 ON lab_instance');
        $this->addSql('ALTER TABLE lab_instance DROP _group_id, DROP owned_by');
        $this->addSql('DROP INDEX IDX_245762DD0949C27 ON network_interface_instance');
        $this->addSql('ALTER TABLE network_interface_instance DROP _group_id, DROP owned_by');
        $this->addSql('ALTER TABLE user DROP uuid');
    }
}
