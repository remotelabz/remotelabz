<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200408135233 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE proxy_redirection (id INT AUTO_INCREMENT NOT NULL, path VARCHAR(255) NOT NULL, target VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE device_instance DROP FOREIGN KEY FK_CC04E8FE628913D5');
        $this->addSql('ALTER TABLE device_instance DROP FOREIGN KEY FK_CC04E8FEB05CE826');
        $this->addSql('DROP INDEX IDX_CC04E8FE628913D5 ON device_instance');
        $this->addSql('ALTER TABLE device_instance DROP lab_id');
        $this->addSql('ALTER TABLE device_instance ADD CONSTRAINT FK_CC04E8FEB05CE826 FOREIGN KEY (lab_instance_id) REFERENCES lab_instance (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lab_instance DROP FOREIGN KEY FK_983C9A4381C06096');
        $this->addSql('DROP INDEX IDX_983C9A4381C06096 ON lab_instance');
        $this->addSql('ALTER TABLE lab_instance DROP activity_id, DROP scope');
        $this->addSql('ALTER TABLE network_interface_instance DROP FOREIGN KEY FK_245762D628913D5');
        $this->addSql('ALTER TABLE network_interface_instance DROP FOREIGN KEY FK_245762D48D126DB');
        $this->addSql('DROP INDEX IDX_245762D628913D5 ON network_interface_instance');
        $this->addSql('ALTER TABLE network_interface_instance DROP lab_id');
        $this->addSql('ALTER TABLE network_interface_instance ADD CONSTRAINT FK_245762D48D126DB FOREIGN KEY (device_instance_id) REFERENCES device_instance (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE proxy_redirection');
        $this->addSql('ALTER TABLE device_instance DROP FOREIGN KEY FK_CC04E8FEB05CE826');
        $this->addSql('ALTER TABLE device_instance ADD lab_id INT NOT NULL');
        $this->addSql('ALTER TABLE device_instance ADD CONSTRAINT FK_CC04E8FE628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id)');
        $this->addSql('ALTER TABLE device_instance ADD CONSTRAINT FK_CC04E8FEB05CE826 FOREIGN KEY (lab_instance_id) REFERENCES lab_instance (id)');
        $this->addSql('CREATE INDEX IDX_CC04E8FE628913D5 ON device_instance (lab_id)');
        $this->addSql('ALTER TABLE lab_instance ADD activity_id INT DEFAULT NULL, ADD scope VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE lab_instance ADD CONSTRAINT FK_983C9A4381C06096 FOREIGN KEY (activity_id) REFERENCES activity (id)');
        $this->addSql('CREATE INDEX IDX_983C9A4381C06096 ON lab_instance (activity_id)');
        $this->addSql('ALTER TABLE network_interface_instance DROP FOREIGN KEY FK_245762D48D126DB');
        $this->addSql('ALTER TABLE network_interface_instance ADD lab_id INT NOT NULL');
        $this->addSql('ALTER TABLE network_interface_instance ADD CONSTRAINT FK_245762D628913D5 FOREIGN KEY (lab_id) REFERENCES lab (id)');
        $this->addSql('ALTER TABLE network_interface_instance ADD CONSTRAINT FK_245762D48D126DB FOREIGN KEY (device_instance_id) REFERENCES device_instance (id)');
        $this->addSql('CREATE INDEX IDX_245762D628913D5 ON network_interface_instance (lab_id)');
    }
}
