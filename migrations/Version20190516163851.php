<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Platforms\MySQLPlatform;


/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190516163851 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');
 


        $this->addSql('CREATE TABLE operating_system (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, image VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, first_name VARCHAR(255) NOT NULL, enabled TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_course (user_id INT NOT NULL, course_id INT NOT NULL, INDEX IDX_73CC7484A76ED395 (user_id), INDEX IDX_73CC7484591CC992 (course_id), PRIMARY KEY(user_id, course_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE hypervisor_settings (id INT AUTO_INCREMENT NOT NULL, vncport_min INT NOT NULL, vncport_max INT NOT NULL, wsport_min INT NOT NULL, wsport_max INT NOT NULL, console_port_min INT NOT NULL, ip VARCHAR(255) NOT NULL, ipv6 VARCHAR(255) NOT NULL, interface_min_id INT NOT NULL, control_interface_id INT NOT NULL, interface_id INT NOT NULL, network_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE course (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lab (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, author_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, is_started TINYINT(1) NOT NULL, uuid VARCHAR(255) NOT NULL, INDEX IDX_61D6B1C4A76ED395 (user_id), INDEX IDX_61D6B1C4F675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lab_device (lab_id INT NOT NULL, device_id INT NOT NULL, INDEX IDX_9CF730DA628913D5 (lab_id), INDEX IDX_9CF730DA94A4C7D4 (device_id), PRIMARY KEY(lab_id, device_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lab_connexion (lab_id INT NOT NULL, connexion_id INT NOT NULL, INDEX IDX_1D0F1A4E628913D5 (lab_id), INDEX IDX_1D0F1A4E8D566613 (connexion_id), PRIMARY KEY(lab_id, connexion_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE network_settings (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, ip VARCHAR(255) DEFAULT NULL, ipv6 VARCHAR(255) DEFAULT NULL, prefix4 INT DEFAULT NULL, prefix6 INT DEFAULT NULL, gateway VARCHAR(255) DEFAULT NULL, protocol VARCHAR(255) DEFAULT NULL, port INT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ip (id INT AUTO_INCREMENT NOT NULL, network_id INT DEFAULT NULL, INDEX IDX_A5E3B32D34128B91 (network_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE instance (id INT AUTO_INCREMENT NOT NULL, lab_id INT DEFAULT NULL, device_id INT DEFAULT NULL, user_id INT DEFAULT NULL, uuid VARCHAR(255) NOT NULL, INDEX IDX_4230B1DE628913D5 (lab_id), INDEX IDX_4230B1DE94A4C7D4 (device_id), INDEX IDX_4230B1DEA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE flavor (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, memory BIGINT NOT NULL, disk BIGINT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE network (id INT AUTO_INCREMENT NOT NULL, cidr VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE exercise (id INT AUTO_INCREMENT NOT NULL, lab_id INT NOT NULL, name VARCHAR(255) NOT NULL, document VARCHAR(255) NOT NULL, INDEX IDX_AEDAD51C628913D5 (lab_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE activity (id INT AUTO_INCREMENT NOT NULL, lab_id INT NOT NULL, network_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, shared TINYINT(1) NOT NULL, supervised TINYINT(1) NOT NULL, access_type VARCHAR(3) NOT NULL, INDEX IDX_AC74095A628913D5 (lab_id), UNIQUE INDEX UNIQ_AC74095A34128B91 (network_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE activity_course (activity_id INT NOT NULL, course_id INT NOT NULL, INDEX IDX_44E88FB981C06096 (activity_id), INDEX IDX_44E88FB9591CC992 (course_id), PRIMARY KEY(activity_id, course_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE connexion (id INT AUTO_INCREMENT NOT NULL, network_interface1_id INT NOT NULL, network_interface2_id INT NOT NULL, name VARCHAR(255) NOT NULL, vlan1 INT NOT NULL, vlan2 INT NOT NULL, UNIQUE INDEX UNIQ_936BF99C967D5FCB (network_interface1_id), UNIQUE INDEX UNIQ_936BF99C84C8F025 (network_interface2_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE device (id INT AUTO_INCREMENT NOT NULL, operating_system_id INT DEFAULT NULL, control_interface_id INT DEFAULT NULL, flavor_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, brand VARCHAR(255) DEFAULT NULL, model VARCHAR(255) DEFAULT NULL, launch_order INT NOT NULL, launch_script VARCHAR(255) DEFAULT NULL, type VARCHAR(255) NOT NULL, virtuality INT NOT NULL, hypervisor VARCHAR(255) NOT NULL, uuid VARCHAR(255) NOT NULL, INDEX IDX_92FB68EA391D4AD (operating_system_id), UNIQUE INDEX UNIQ_92FB68E584DDA64 (control_interface_id), INDEX IDX_92FB68EFDDA6450 (flavor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE network_interface (id INT AUTO_INCREMENT NOT NULL, settings_id INT DEFAULT NULL, device_id INT DEFAULT NULL, type VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, mac_address VARCHAR(17) NOT NULL, UNIQUE INDEX UNIQ_B3518C3459949888 (settings_id), INDEX IDX_B3518C3494A4C7D4 (device_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE hypervisor (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, command VARCHAR(255) NOT NULL, arguments VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_course ADD CONSTRAINT FK_73CC7484A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_course ADD CONSTRAINT FK_73CC7484591CC992 FOREIGN KEY (course_id) REFERENCES course (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lab ADD CONSTRAINT FK_61D6B1C4A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE lab ADD CONSTRAINT FK_61D6B1C4F675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE lab_device ADD CONSTRAINT FK_9CF730DA628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lab_device ADD CONSTRAINT FK_9CF730DA94A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lab_connexion ADD CONSTRAINT FK_1D0F1A4E628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lab_connexion ADD CONSTRAINT FK_1D0F1A4E8D566613 FOREIGN KEY (connexion_id) REFERENCES connexion (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ip ADD CONSTRAINT FK_A5E3B32D34128B91 FOREIGN KEY (network_id) REFERENCES network (id)');
        $this->addSql('ALTER TABLE instance ADD CONSTRAINT FK_4230B1DE628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id)');
        $this->addSql('ALTER TABLE instance ADD CONSTRAINT FK_4230B1DE94A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id)');
        $this->addSql('ALTER TABLE instance ADD CONSTRAINT FK_4230B1DEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE exercise ADD CONSTRAINT FK_AEDAD51C628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id)');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id)');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A34128B91 FOREIGN KEY (network_id) REFERENCES network (id)');
        $this->addSql('ALTER TABLE activity_course ADD CONSTRAINT FK_44E88FB981C06096 FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE activity_course ADD CONSTRAINT FK_44E88FB9591CC992 FOREIGN KEY (course_id) REFERENCES course (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE connexion ADD CONSTRAINT FK_936BF99C967D5FCB FOREIGN KEY (network_interface1_id) REFERENCES network_interface (id)');
        $this->addSql('ALTER TABLE connexion ADD CONSTRAINT FK_936BF99C84C8F025 FOREIGN KEY (network_interface2_id) REFERENCES network_interface (id)');
        $this->addSql('ALTER TABLE device ADD CONSTRAINT FK_92FB68EA391D4AD FOREIGN KEY (operating_system_id) REFERENCES operating_system (id)');
        $this->addSql('ALTER TABLE device ADD CONSTRAINT FK_92FB68E584DDA64 FOREIGN KEY (control_interface_id) REFERENCES network_interface (id)');
        $this->addSql('ALTER TABLE device ADD CONSTRAINT FK_92FB68EFDDA6450 FOREIGN KEY (flavor_id) REFERENCES flavor (id)');
        $this->addSql('ALTER TABLE network_interface ADD CONSTRAINT FK_B3518C3459949888 FOREIGN KEY (settings_id) REFERENCES network_settings (id)');
        $this->addSql('ALTER TABLE network_interface ADD CONSTRAINT FK_B3518C3494A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof MySQLPlatform, 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE device DROP FOREIGN KEY FK_92FB68EA391D4AD');
        $this->addSql('ALTER TABLE user_course DROP FOREIGN KEY FK_73CC7484A76ED395');
        $this->addSql('ALTER TABLE lab DROP FOREIGN KEY FK_61D6B1C4A76ED395');
        $this->addSql('ALTER TABLE lab DROP FOREIGN KEY FK_61D6B1C4F675F31B');
        $this->addSql('ALTER TABLE instance DROP FOREIGN KEY FK_4230B1DEA76ED395');
        $this->addSql('ALTER TABLE user_course DROP FOREIGN KEY FK_73CC7484591CC992');
        $this->addSql('ALTER TABLE activity_course DROP FOREIGN KEY FK_44E88FB9591CC992');
        $this->addSql('ALTER TABLE lab_device DROP FOREIGN KEY FK_9CF730DA628913D5');
        $this->addSql('ALTER TABLE lab_connexion DROP FOREIGN KEY FK_1D0F1A4E628913D5');
        $this->addSql('ALTER TABLE instance DROP FOREIGN KEY FK_4230B1DE628913D5');
        $this->addSql('ALTER TABLE exercise DROP FOREIGN KEY FK_AEDAD51C628913D5');
        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095A628913D5');
        $this->addSql('ALTER TABLE network_interface DROP FOREIGN KEY FK_B3518C3459949888');
        $this->addSql('ALTER TABLE device DROP FOREIGN KEY FK_92FB68EFDDA6450');
        $this->addSql('ALTER TABLE ip DROP FOREIGN KEY FK_A5E3B32D34128B91');
        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095A34128B91');
        $this->addSql('ALTER TABLE activity_course DROP FOREIGN KEY FK_44E88FB981C06096');
        $this->addSql('ALTER TABLE lab_connexion DROP FOREIGN KEY FK_1D0F1A4E8D566613');
        $this->addSql('ALTER TABLE lab_device DROP FOREIGN KEY FK_9CF730DA94A4C7D4');
        $this->addSql('ALTER TABLE instance DROP FOREIGN KEY FK_4230B1DE94A4C7D4');
        $this->addSql('ALTER TABLE network_interface DROP FOREIGN KEY FK_B3518C3494A4C7D4');
        $this->addSql('ALTER TABLE connexion DROP FOREIGN KEY FK_936BF99C967D5FCB');
        $this->addSql('ALTER TABLE connexion DROP FOREIGN KEY FK_936BF99C84C8F025');
        $this->addSql('ALTER TABLE device DROP FOREIGN KEY FK_92FB68E584DDA64');
        $this->addSql('DROP TABLE operating_system');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_course');
        $this->addSql('DROP TABLE hypervisor_settings');
        $this->addSql('DROP TABLE course');
        $this->addSql('DROP TABLE lab');
        $this->addSql('DROP TABLE lab_device');
        $this->addSql('DROP TABLE lab_connexion');
        $this->addSql('DROP TABLE network_settings');
        $this->addSql('DROP TABLE ip');
        $this->addSql('DROP TABLE instance');
        $this->addSql('DROP TABLE flavor');
        $this->addSql('DROP TABLE network');
        $this->addSql('DROP TABLE exercise');
        $this->addSql('DROP TABLE activity');
        $this->addSql('DROP TABLE activity_course');
        $this->addSql('DROP TABLE connexion');
        $this->addSql('DROP TABLE device');
        $this->addSql('DROP TABLE network_interface');
        $this->addSql('DROP TABLE hypervisor');
    }
}
