<?php declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181205104924 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE network_interface (id INT AUTO_INCREMENT NOT NULL, settings_id INT DEFAULT NULL, device_id INT DEFAULT NULL, type VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_B3518C3459949888 (settings_id), INDEX IDX_B3518C3494A4C7D4 (device_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE connexion (id INT AUTO_INCREMENT NOT NULL, pod_id INT DEFAULT NULL, device1_id INT DEFAULT NULL, device2_id INT DEFAULT NULL, network_interface1_id INT NOT NULL, network_interface2_id INT NOT NULL, name VARCHAR(255) NOT NULL, vlan1 INT NOT NULL, vlan2 INT NOT NULL, INDEX IDX_936BF99C8CC63088 (pod_id), INDEX IDX_936BF99C57469F99 (device1_id), INDEX IDX_936BF99C45F33077 (device2_id), UNIQUE INDEX UNIQ_936BF99C967D5FCB (network_interface1_id), UNIQUE INDEX UNIQ_936BF99C84C8F025 (network_interface2_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lab (id INT AUTO_INCREMENT NOT NULL, pod_id INT NOT NULL, name VARCHAR(255) NOT NULL, INDEX IDX_61D6B1C48CC63088 (pod_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lab_connexion (lab_id INT NOT NULL, connexion_id INT NOT NULL, INDEX IDX_1D0F1A4E628913D5 (lab_id), INDEX IDX_1D0F1A4E8D566613 (connexion_id), PRIMARY KEY(lab_id, connexion_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, first_name VARCHAR(255) NOT NULL, enabled TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_course (user_id INT NOT NULL, course_id INT NOT NULL, INDEX IDX_73CC7484A76ED395 (user_id), INDEX IDX_73CC7484591CC992 (course_id), PRIMARY KEY(user_id, course_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE operating_system (id INT AUTO_INCREMENT NOT NULL, hypervisor_id INT DEFAULT NULL, flavor_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, path VARCHAR(255) NOT NULL, INDEX IDX_BCF9A7814F2DF8A7 (hypervisor_id), INDEX IDX_BCF9A781FDDA6450 (flavor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE flavor (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, memory BIGINT NOT NULL, disk BIGINT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE hypervisor (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, command VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE course (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE pod (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE network_settings (id INT AUTO_INCREMENT NOT NULL, ip VARCHAR(255) DEFAULT NULL, ipv6 VARCHAR(255) DEFAULT NULL, prefix4 INT DEFAULT NULL, prefix6 INT DEFAULT NULL, gateway VARCHAR(255) DEFAULT NULL, protocol VARCHAR(255) DEFAULT NULL, port INT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE exercise (id INT AUTO_INCREMENT NOT NULL, lab_id INT NOT NULL, name VARCHAR(255) NOT NULL, document VARCHAR(255) NOT NULL, INDEX IDX_AEDAD51C628913D5 (lab_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE device (id INT AUTO_INCREMENT NOT NULL, pod_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, brand VARCHAR(255) DEFAULT NULL, model VARCHAR(255) DEFAULT NULL, launch_order INT NOT NULL, launch_script VARCHAR(255) NOT NULL, INDEX IDX_92FB68E8CC63088 (pod_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE network_interface ADD CONSTRAINT FK_B3518C3459949888 FOREIGN KEY (settings_id) REFERENCES network_settings (id)');
        $this->addSql('ALTER TABLE network_interface ADD CONSTRAINT FK_B3518C3494A4C7D4 FOREIGN KEY (device_id) REFERENCES device (id)');
        $this->addSql('ALTER TABLE connexion ADD CONSTRAINT FK_936BF99C8CC63088 FOREIGN KEY (pod_id) REFERENCES pod (id)');
        $this->addSql('ALTER TABLE connexion ADD CONSTRAINT FK_936BF99C57469F99 FOREIGN KEY (device1_id) REFERENCES device (id)');
        $this->addSql('ALTER TABLE connexion ADD CONSTRAINT FK_936BF99C45F33077 FOREIGN KEY (device2_id) REFERENCES device (id)');
        $this->addSql('ALTER TABLE connexion ADD CONSTRAINT FK_936BF99C967D5FCB FOREIGN KEY (network_interface1_id) REFERENCES network_interface (id)');
        $this->addSql('ALTER TABLE connexion ADD CONSTRAINT FK_936BF99C84C8F025 FOREIGN KEY (network_interface2_id) REFERENCES network_interface (id)');
        $this->addSql('ALTER TABLE lab ADD CONSTRAINT FK_61D6B1C48CC63088 FOREIGN KEY (pod_id) REFERENCES pod (id)');
        $this->addSql('ALTER TABLE lab_connexion ADD CONSTRAINT FK_1D0F1A4E628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lab_connexion ADD CONSTRAINT FK_1D0F1A4E8D566613 FOREIGN KEY (connexion_id) REFERENCES connexion (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_course ADD CONSTRAINT FK_73CC7484A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_course ADD CONSTRAINT FK_73CC7484591CC992 FOREIGN KEY (course_id) REFERENCES course (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE operating_system ADD CONSTRAINT FK_BCF9A7814F2DF8A7 FOREIGN KEY (hypervisor_id) REFERENCES hypervisor (id)');
        $this->addSql('ALTER TABLE operating_system ADD CONSTRAINT FK_BCF9A781FDDA6450 FOREIGN KEY (flavor_id) REFERENCES flavor (id)');
        $this->addSql('ALTER TABLE exercise ADD CONSTRAINT FK_AEDAD51C628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id)');
        $this->addSql('ALTER TABLE device ADD CONSTRAINT FK_92FB68E8CC63088 FOREIGN KEY (pod_id) REFERENCES pod (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE connexion DROP FOREIGN KEY FK_936BF99C967D5FCB');
        $this->addSql('ALTER TABLE connexion DROP FOREIGN KEY FK_936BF99C84C8F025');
        $this->addSql('ALTER TABLE lab_connexion DROP FOREIGN KEY FK_1D0F1A4E8D566613');
        $this->addSql('ALTER TABLE lab_connexion DROP FOREIGN KEY FK_1D0F1A4E628913D5');
        $this->addSql('ALTER TABLE exercise DROP FOREIGN KEY FK_AEDAD51C628913D5');
        $this->addSql('ALTER TABLE user_course DROP FOREIGN KEY FK_73CC7484A76ED395');
        $this->addSql('ALTER TABLE operating_system DROP FOREIGN KEY FK_BCF9A781FDDA6450');
        $this->addSql('ALTER TABLE operating_system DROP FOREIGN KEY FK_BCF9A7814F2DF8A7');
        $this->addSql('ALTER TABLE user_course DROP FOREIGN KEY FK_73CC7484591CC992');
        $this->addSql('ALTER TABLE connexion DROP FOREIGN KEY FK_936BF99C8CC63088');
        $this->addSql('ALTER TABLE lab DROP FOREIGN KEY FK_61D6B1C48CC63088');
        $this->addSql('ALTER TABLE device DROP FOREIGN KEY FK_92FB68E8CC63088');
        $this->addSql('ALTER TABLE network_interface DROP FOREIGN KEY FK_B3518C3459949888');
        $this->addSql('ALTER TABLE network_interface DROP FOREIGN KEY FK_B3518C3494A4C7D4');
        $this->addSql('ALTER TABLE connexion DROP FOREIGN KEY FK_936BF99C57469F99');
        $this->addSql('ALTER TABLE connexion DROP FOREIGN KEY FK_936BF99C45F33077');
        $this->addSql('DROP TABLE network_interface');
        $this->addSql('DROP TABLE connexion');
        $this->addSql('DROP TABLE lab');
        $this->addSql('DROP TABLE lab_connexion');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_course');
        $this->addSql('DROP TABLE operating_system');
        $this->addSql('DROP TABLE flavor');
        $this->addSql('DROP TABLE hypervisor');
        $this->addSql('DROP TABLE course');
        $this->addSql('DROP TABLE pod');
        $this->addSql('DROP TABLE network_settings');
        $this->addSql('DROP TABLE exercise');
        $this->addSql('DROP TABLE device');
    }
}
